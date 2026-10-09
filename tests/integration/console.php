<?php
/**
 * Diploma console-command checks: CSV import, expiry, certificate reissue and drip unlocks —
 * through the services and through the real `php craft diploma/…` commands.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-diploma/tests/integration/console.php
 *
 * Self-cleaning: everything is named `Diploma check …` / `diplomacheck…` and swept at both ends,
 * along with the activity-log rows the checks wrote.
 */

$root = getcwd();
require $root . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
require __DIR__ . '/_fixtures.php';

use craft\elements\User;
use craft\helpers\Db;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\events\EnrollmentEvent;
use justinholtweb\diploma\events\LessonUnlockEvent;
use justinholtweb\diploma\records\ActivityLogRecord;
use justinholtweb\diploma\records\CertificateRecord;
use justinholtweb\diploma\records\EnrollmentRecord;
use justinholtweb\diploma\services\DripContent;
use justinholtweb\diploma\services\Enrollments;
use yii\base\Event;

/** Runs a real console command; returns [exit code, output]. */
function craftCmd(string $args): array
{
    exec('php craft ' . $args . ' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
}

function csvFile(string $contents): string
{
    $file = tempnam(sys_get_temp_dir(), 'dccsv') . '.csv';
    file_put_contents($file, $contents);

    return $file;
}

function enrollmentRecord(int $courseId, int $userId): ?EnrollmentRecord
{
    return EnrollmentRecord::find()->where(['courseId' => $courseId, 'userId' => $userId])->one();
}

function cleanLog(): void
{
    $userIds = User::find()->username(DC_USER . '*')->status(null)->ids();

    if ($userIds) {
        ActivityLogRecord::deleteAll(['userId' => $userIds]);
    }
}

cleanLog();
sweep();

$a = makeUser('ca');
$b = makeUser('cb');
$c = makeUser('cc');
$course = makeCourse('console');
$course->slug = 'diploma-check-console';
dc()->courses->save($course);
$other = makeCourse('console other');
$tmpFiles = [];

// -----------------------------------------------------------------------------------------------
section('Course lookup');

check('--course resolves an ID or an exact slug, not a wildcard', function() use ($course) {
    return dc()->courses->resolve((string)$course->id)?->id === $course->id
        && dc()->courses->resolve('diploma-check-console')?->id === $course->id
        && dc()->courses->resolve('diploma-check-*') === null
        && dc()->courses->resolve('') === null;
});

// -----------------------------------------------------------------------------------------------
section('diploma/enrollments/import');

$csv = "\xEF\xBB\xBFEmail,Status,Date,Completed,Expires\n"
    . "{$a->email},active,2025-01-15,,2099-01-01\n"
    . "{$b->email},completed,2025-02-01,2025-03-01,\n"
    . DC_USER . "new@example.test,,,,\n"
    . "not-an-email,active,,,\n"
    . "{$c->email},graduated,,,\n"
    . "{$c->email},active,yesterday-ish,,\n";
$tmpFiles[] = $file = csvFile($csv);

check('a missing --course is a usage error', function() use ($file) {
    [$code] = craftCmd('diploma/enrollments/import ' . escapeshellarg($file));

    return $code === 64 ?: "exit $code";
});

check('an unknown course is a usage error', function() use ($file) {
    [$code, $out] = craftCmd('diploma/enrollments/import ' . escapeshellarg($file) . ' --course=no-such-course-xyz');

    return $code === 64 && str_contains($out, 'No course') ?: "exit $code: $out";
});

check('an unreadable file is refused', function() use ($course) {
    [$code] = craftCmd('diploma/enrollments/import /nonexistent.csv --course=' . $course->id);

    return $code === 66 ?: "exit $code";
});

check('a file with no email column is refused', function() use ($course, &$tmpFiles) {
    $tmpFiles[] = $f = csvFile("name,status\nx,active\n");
    [$code, $out] = craftCmd('diploma/enrollments/import ' . escapeshellarg($f) . ' --course=' . $course->id);

    return $code === 66 && str_contains($out, 'email') ?: "exit $code: $out";
});

check('--dry-run reports and writes nothing', function() use ($file, $course, $a, $b) {
    [$code, $out] = craftCmd('diploma/enrollments/import ' . escapeshellarg($file) . ' --course=diploma-check-console --dry-run');

    return $code === 65
        && str_contains($out, 'Dry run')
        && enrollmentRecord($course->id, $a->id) === null
        && enrollmentRecord($course->id, $b->id) === null
        ?: "exit $code: $out";
});

check('the import enrols good rows and reports each bad one (no --create-users)', function() use ($file, $course, $a, $b, $c) {
    [$code, $out] = craftCmd('diploma/enrollments/import ' . escapeshellarg($file) . ' --course=' . $course->id);

    $ra = enrollmentRecord($course->id, $a->id);
    $rb = enrollmentRecord($course->id, $b->id);

    $ok = $code === 65
        && $ra?->enrollmentStatus === 'active' && $ra->source === 'import'
        && str_starts_with((string)$ra->expiresAt, '2099-01-0') && str_starts_with((string)$ra->enrolledAt, '2025-01-1')
        && $rb?->enrollmentStatus === 'completed'
        && str_starts_with((string)$rb->completedAt, '2025-0') && str_starts_with((string)$rb->enrolledAt, '2025-0')
        && enrollmentRecord($course->id, $c->id) === null
        && str_contains($out, 'no user has the email ' . DC_USER . 'new@example.test')
        && str_contains($out, '“not-an-email” is not an email address')
        && str_contains($out, 'unknown status “graduated”')
        && str_contains($out, 'is not a date')
        && User::find()->email(DC_USER . 'new@example.test')->status(null)->one() === null;

    return $ok ?: "exit $code: $out";
});

check('the imported enrolment is in the activity log with source "import"', function() use ($course, $a) {
    $row = ActivityLogRecord::find()->where(['eventType' => 'enrolled', 'userId' => $a->id, 'courseId' => $course->id])->one();

    return $row !== null && json_decode($row->data, true)['source'] === 'import';
});

check('importing the same file again skips everyone already enrolled', function() use ($file, $course) {
    $before = (int)EnrollmentRecord::find()->where(['courseId' => $course->id])->count();
    $result = dc()->enrollments->import($course->id, [['email' => DC_USER . 'ca@example.test'], ['email' => DC_USER . 'cb@example.test']]);
    $after = (int)EnrollmentRecord::find()->where(['courseId' => $course->id])->count();

    return count($result->skipped) === 2 && $result->done === [] && $before === $after;
});

check('--create-users creates a pending user and enrols them', function() use ($course, &$tmpFiles) {
    $tmpFiles[] = $f = csvFile("email\n" . DC_USER . "new@example.test\n");
    [$code, $out] = craftCmd('diploma/enrollments/import ' . escapeshellarg($f) . ' --course=' . $course->id . ' --create-users');
    $user = User::find()->email(DC_USER . 'new@example.test')->status(null)->one();

    return $code === 0 && $user !== null && $user->pending && enrollmentRecord($course->id, $user->id)?->enrollmentStatus === 'active'
        ?: "exit $code: $out";
});

check('a lapsed enrolment is reactivated by an import', function() use ($other, $c) {
    $e = dc()->enrollments->enroll($other->id, $c->id, 'manual');
    dc()->enrollments->updateStatus($e->id, 'cancelled');
    $result = dc()->enrollments->import($other->id, [['email' => $c->email, 'status' => 'active']]);

    return count($result->done) === 1 && str_contains($result->done[0], 'reactivated')
        && enrollmentRecord($other->id, $c->id)->enrollmentStatus === 'active';
});

check('a full course refuses the row, the rest carry on', function() use ($c, $a) {
    $full = makeCourse('console full');
    $full->enrollmentLimit = 1;
    dc()->courses->save($full);
    $result = dc()->enrollments->import($full->id, [['email' => $a->email], ['email' => $c->email]]);

    return count($result->done) === 1 && count($result->failures) === 1 && str_contains($result->failures[0], 'full');
});

// -----------------------------------------------------------------------------------------------
section('diploma/enrollments/expire');

check('an active enrolment past its expiry date stops counting at once', function() use ($course, $a) {
    $r = enrollmentRecord($course->id, $a->id);
    $r->expiresAt = Db::prepareDateForDb(new DateTime('-1 hour'));
    $r->save(false);
    $model = dc()->enrollments->getEnrollment($course->id, $a->id);

    return !dc()->enrollments->isEnrolled($course->id, $a->id) && $model->hasExpired() && $model->enrollmentStatus === 'active';
});

check('an enrolment expiring in the future still counts', function() use ($other, $c) {
    $r = enrollmentRecord($other->id, $c->id);
    $r->expiresAt = Db::prepareDateForDb(new DateTime('+1 day'));
    $r->save(false);

    return dc()->enrollments->isEnrolled($other->id, $c->id) && !dc()->enrollments->getEnrollment($other->id, $c->id)->hasExpired();
});

check('--dry-run lists it and changes nothing', function() use ($course, $a) {
    [$code, $out] = craftCmd('diploma/enrollments/expire --dry-run --course=' . $course->id);

    return $code === 0 && str_contains($out, 'Would be expired: 1') && enrollmentRecord($course->id, $a->id)->enrollmentStatus === 'active'
        ?: "exit $code: $out";
});

check('--course limits the run to one course', function() use ($other) {
    [$code, $out] = craftCmd('diploma/enrollments/expire --course=' . $other->id);

    return $code === 0 && str_contains($out, 'Expired: 0') ?: "exit $code: $out";
});

check('expire marks overdue enrolments expired, logs them, and leaves the rest', function() use ($course, $other, $a, $b, $c) {
    // A completed enrolment past its date is a record, not access: it must stay completed.
    $rb = enrollmentRecord($course->id, $b->id);
    $rb->expiresAt = Db::prepareDateForDb(new DateTime('-2 days'));
    $rb->save(false);

    $fired = [];
    Event::on(Enrollments::class, Enrollments::EVENT_AFTER_EXPIRE, function(EnrollmentEvent $e) use (&$fired) {
        $fired[] = $e->enrollment->id;
    });

    $result = dc()->enrollments->expireDue($course->id);
    $ra = enrollmentRecord($course->id, $a->id);

    $logged = ActivityLogRecord::find()->where(['eventType' => 'enrollmentExpired', 'enrollmentId' => $ra->id])->exists();

    return count($result->done) === 1 && $ra->enrollmentStatus === 'expired' && $fired === [$ra->id] && $logged
        && enrollmentRecord($course->id, $b->id)->enrollmentStatus === 'completed'
        && enrollmentRecord($other->id, $c->id)->enrollmentStatus === 'active'
        ?: json_encode($result->toArray()) . ' fired ' . json_encode($fired);
});

check('running it again finds nothing', function() use ($course) {
    [$code, $out] = craftCmd('diploma/enrollments/expire --course=' . $course->id);

    return $code === 0 && str_contains($out, 'Expired: 0') ?: "exit $code: $out";
});

check('reactivating an expired enrolment clears its expiry date', function() use ($course, $a) {
    dc()->enrollments->enroll($course->id, $a->id, 'manual');
    $r = enrollmentRecord($course->id, $a->id);

    return $r->enrollmentStatus === 'active' && $r->expiresAt === null && dc()->enrollments->isEnrolled($course->id, $a->id);
});

// -----------------------------------------------------------------------------------------------
section('diploma/certificates/reissue');

check('--missing issues the certificate an imported completion never got (dry run first)', function() use ($course, $b) {
    $rb = enrollmentRecord($course->id, $b->id);
    [$dryCode, $dryOut] = craftCmd('diploma/certificates/reissue --missing --dry-run --course=' . $course->id);
    $none = !CertificateRecord::find()->where(['enrollmentId' => $rb->id])->exists();
    [$code, $out] = craftCmd('diploma/certificates/reissue --missing --course=' . $course->id);
    $cert = CertificateRecord::find()->where(['enrollmentId' => $rb->id])->one();

    return $dryCode === 0 && $none && str_contains($dryOut, 'would be issued') && $code === 0 && $cert !== null
        ?: "dry $dryCode: $dryOut\nrun $code: $out";
});

check('reissue rebuilds the snapshot after a rename, keeping the code and issue date', function() use ($course, $b) {
    $cert = CertificateRecord::find()->where(['courseId' => $course->id, 'userId' => $b->id])->one();
    $code0 = $cert->verificationCode;
    $issued0 = json_decode($cert->metadata, true)['issuedDate'];

    $course->title = DC_PREFIX . ' console renamed';
    dc()->courses->save($course);

    $dry = dc()->certificates->reissue($course->id, true);
    $unchanged = json_decode(CertificateRecord::findOne($cert->id)->metadata, true)['courseTitle'] !== $course->title;

    [$exit, $out] = craftCmd('diploma/certificates/reissue --course=' . $course->id);
    $after = CertificateRecord::findOne($cert->id);
    $meta = json_decode($after->metadata, true);
    $logged = ActivityLogRecord::find()->where(['eventType' => 'certificateReissued', 'userId' => $b->id])->exists();

    return count($dry->done) === 1 && $unchanged && $exit === 0
        && $meta['courseTitle'] === DC_PREFIX . ' console renamed'
        && $after->verificationCode === $code0 && $meta['issuedDate'] === $issued0 && $logged
        ?: "exit $exit: $out " . json_encode($meta);
});

check('a second reissue finds them up to date', function() use ($course) {
    $result = dc()->certificates->reissue($course->id);

    return $result->done === [] && count($result->skipped) === 1 && str_contains($result->skipped[0], 'up to date');
});

// -----------------------------------------------------------------------------------------------
section('diploma/drip/unlock');

$lesson = new Lesson();
$lesson->title = DC_PREFIX . ' drip lesson';
$lesson->courseId = $other->id;
$lesson->dripDays = 3;
dc()->lessons->save($lesson);

$plain = new Lesson();
$plain->title = DC_PREFIX . ' plain lesson';
$plain->courseId = $other->id;
dc()->lessons->save($plain);

$dripUser = makeUser('cdrip');
$dripEnrollment = dc()->enrollments->enroll($other->id, $dripUser->id, 'manual');

function setEnrolledAt(int $enrollmentId, string $modify): void
{
    $r = EnrollmentRecord::findOne($enrollmentId);
    $r->enrolledAt = Db::prepareDateForDb(new DateTime($modify));
    $r->save(false);
}

check('a lesson not yet due is not announced', function() use ($other, $dripEnrollment) {
    setEnrolledAt($dripEnrollment->id, '-1 day');
    // Learner C's enrolment in this course is 'now'-ish too: nothing is due.
    $result = dc()->dripContent->unlockDue($other->id);

    return $result->done === [];
});

check('an unlock older than the lookback is ignored', function() use ($other, $dripEnrollment) {
    setEnrolledAt($dripEnrollment->id, '-30 days');

    return dc()->dripContent->unlockDue($other->id, 7)->done === [];
});

check('a due lesson is announced once: one event, one log row, and the plain lesson never', function() use ($other, $dripEnrollment, $lesson, $plain, $dripUser) {
    setEnrolledAt($dripEnrollment->id, '-4 days');
    $events = [];
    $handler = function(LessonUnlockEvent $e) use (&$events) {
        $events[] = [$e->enrollmentId, $e->lessonId, $e->userId];
    };
    Event::on(DripContent::class, DripContent::EVENT_AFTER_UNLOCK_LESSON, $handler);

    $dry = dc()->dripContent->unlockDue($other->id, 7, true);
    $first = dc()->dripContent->unlockDue($other->id);
    $second = dc()->dripContent->unlockDue($other->id);
    Event::off(DripContent::class, DripContent::EVENT_AFTER_UNLOCK_LESSON, $handler);

    $rows = (int)ActivityLogRecord::find()->where(['eventType' => 'lessonUnlocked', 'enrollmentId' => $dripEnrollment->id])->count();

    return count($dry->done) === 1 && count($first->done) === 1 && $second->done === [] && count($second->skipped) === 1
        && $events === [[$dripEnrollment->id, $lesson->id, $dripUser->id]] && $rows === 1
        ?: json_encode([$dry->toArray(), $first->toArray(), $second->toArray(), $events, $rows]);
});

check('a learner who re-enrols after the announcement is told again', function() use ($other, $dripEnrollment) {
    ActivityLogRecord::updateAll(
        ['dateCreated' => Db::prepareDateForDb(new DateTime('-10 days'))],
        ['eventType' => 'lessonUnlocked', 'enrollmentId' => $dripEnrollment->id],
    );

    return count(dc()->dripContent->unlockDue($other->id)->done) === 1;
});

check('an expired or overdue enrolment is not announced', function() use ($other, $dripEnrollment) {
    ActivityLogRecord::deleteAll(['eventType' => 'lessonUnlocked', 'enrollmentId' => $dripEnrollment->id]);
    $r = EnrollmentRecord::findOne($dripEnrollment->id);
    $r->expiresAt = Db::prepareDateForDb(new DateTime('-1 hour'));
    $r->save(false);
    $result = dc()->dripContent->unlockDue($other->id);
    $r->expiresAt = null;
    $r->save(false);

    return $result->done === [];
});

check('the command is Pro-only, and runs on Pro', function() use ($other) {
    [$code, $out] = craftCmd('diploma/drip/unlock --dry-run --course=' . $other->id);

    if (!dc()->is(justinholtweb\diploma\Plugin::EDITION_PRO)) {
        return $code === 69 && str_contains($out, 'Pro') ?: "Lite: exit $code: $out";
    }

    return $code === 0 && str_contains($out, 'Would be announced: 1') ?: "Pro: exit $code: $out";
});

check('on Lite the command refuses and announces nothing', function() use ($other) {
    $plugin = dc();
    $edition = $plugin->edition;
    $plugin->edition = justinholtweb\diploma\Plugin::EDITION_LITE; // in memory only

    try {
        ob_start();
        $code = Craft::$app->runAction('diploma/drip/unlock', ['course' => (string)$other->id, 'interactive' => false]);
        ob_end_clean();
    } finally {
        $plugin->edition = $edition;
    }

    return $code === 69 ?: "exit $code";
});

// -----------------------------------------------------------------------------------------------
section('Help');

check('every command is listed by craft help', function() {
    [, $e] = craftCmd('help diploma/enrollments');
    [, $c] = craftCmd('help diploma/certificates');
    [, $d] = craftCmd('help diploma/drip');

    return str_contains($e, 'diploma/enrollments/import') && str_contains($e, 'diploma/enrollments/expire')
        && str_contains($c, 'diploma/certificates/reissue') && str_contains($d, 'diploma/drip/unlock');
});

// -----------------------------------------------------------------------------------------------
foreach ($tmpFiles as $f) {
    @unlink($f);
}

cleanLog();
sweep();

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
