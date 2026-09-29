<?php
/**
 * Diploma integration checks — the enrolment and grading rules, through the real services.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-diploma/tests/integration/checks.php
 *
 * The request-level half (a signed-in learner attacking the actual endpoints) is http.php.
 * Self-cleaning: everything is named `Diploma check …` and swept at the start and the end.
 */

$root = getcwd();
require $root . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
require __DIR__ . '/_fixtures.php';

use justinholtweb\diploma\records\EnrollmentRecord;
use justinholtweb\diploma\records\QuizAttemptRecord;

$settings = dc()->getSettings();
$originalMapping = $settings->headcountPlanMapping;

sweep();

$learner = makeUser('a');
$other = makeUser('b');

$open = makeCourse('open');
$manual = makeCourse('manual', 'manual');
$purchase = makeCourse('purchase', 'purchase');
$draft = makeCourse('draft', 'open', 'draft');
$full = makeCourse('full');
$full->enrollmentLimit = 1;
dc()->courses->save($full);

// -----------------------------------------------------------------------------------------------
section('Self-enrolment');

check('an open, published course can be joined', function() use ($open, $learner) {
    return dc()->enrollments->selfEnrollmentProblem($open->id, $learner->id) === null;
});

check('manual and purchase courses cannot', function() use ($manual, $purchase, $learner) {
    return dc()->enrollments->selfEnrollmentProblem($manual->id, $learner->id) !== null
        && dc()->enrollments->selfEnrollmentProblem($purchase->id, $learner->id) !== null;
});

check('an unpublished course cannot', function() use ($draft, $learner) {
    return dc()->enrollments->selfEnrollmentProblem($draft->id, $learner->id) !== null;
});

check('a disabled course cannot', function() use ($learner) {
    $c = makeCourse('disabled');
    $c->enabled = false;
    dc()->courses->save($c);

    return dc()->enrollments->selfEnrollmentProblem($c->id, $learner->id) !== null;
});

check('a missing course cannot', function() use ($learner) {
    return dc()->enrollments->selfEnrollmentProblem(999999999, $learner->id) !== null;
});

check('an "open" course mapped to a Headcount plan is treated as sold', function() use ($learner, $settings) {
    $c = makeCourse('mapped');
    $settings->headcountPlanMapping = ['plan_x' => (string)$c->id];
    $sold = dc()->enrollments->isSold($c->id) && dc()->enrollments->selfEnrollmentProblem($c->id, $learner->id) !== null;
    $settings->headcountPlanMapping = [];

    return $sold;
});

check('an "open" course related from a diplomaCourses field (Commerce) is treated as sold', function() use ($learner) {
    $c = makeCourse('commerce-linked');
    $fields = Craft::$app->getFields();
    $field = $fields->getFieldByHandle('diplomaCourses');
    $made = false;

    if ($field === null) {
        $field = new craft\fields\Entries(['name' => 'Diploma courses (check)', 'handle' => 'diplomaCourses']);
        $fields->saveField($field);
        $made = true;
    }

    Craft::$app->getDb()->createCommand()->insert(craft\db\Table::RELATIONS, [
        'fieldId' => $field->id, 'sourceId' => $learner->id, 'targetId' => $c->id, 'sortOrder' => 1,
    ])->execute();

    $sold = dc()->enrollments->isSold($c->id) && dc()->enrollments->selfEnrollmentProblem($c->id, $learner->id) !== null;

    Craft::$app->getDb()->createCommand()->delete(craft\db\Table::RELATIONS, ['fieldId' => $field->id, 'targetId' => $c->id])->execute();
    if ($made) {
        $fields->deleteField($field);
    }

    return $sold;
});

check('a full course cannot be joined', function() use ($full, $learner, $other) {
    dc()->enrollments->enroll($full->id, $other->id, 'manual');

    return dc()->enrollments->selfEnrollmentProblem($full->id, $learner->id) !== null;
});

// -----------------------------------------------------------------------------------------------
section('Enrolment and reactivation');

check('enrolling twice returns the same enrolment, not a second row', function() use ($open, $learner) {
    $a = dc()->enrollments->enroll($open->id, $learner->id, 'self');
    $b = dc()->enrollments->enroll($open->id, $learner->id, 'self');

    return $a && $b && $a->id === $b->id && (int)EnrollmentRecord::find()->where(['courseId' => $open->id, 'userId' => $learner->id])->count() === 1;
});

check('a lapsed enrolment is not reactivated by self-enrolling', function() use ($open, $learner) {
    $e = dc()->enrollments->getEnrollment($open->id, $learner->id);
    dc()->enrollments->updateStatus($e->id, 'expired');

    return dc()->enrollments->selfEnrollmentProblem($open->id, $learner->id) !== null
        && dc()->enrollments->enroll($open->id, $learner->id, 'self') === null
        && dc()->enrollments->getEnrollment($open->id, $learner->id)->enrollmentStatus === 'expired';
});

check('a membership or admin reactivates it — the same row, now active', function() use ($open, $learner) {
    $before = dc()->enrollments->getEnrollment($open->id, $learner->id)->id;
    $e = dc()->enrollments->enroll($open->id, $learner->id, 'headcount', 42);

    return $e && $e->id === $before && $e->enrollmentStatus === 'active' && $e->source === 'headcount';
});

check('a completed enrolment is returned, not reset', function() use ($purchase, $learner) {
    $e = dc()->enrollments->enroll($purchase->id, $learner->id, 'commerce');
    dc()->enrollments->updateStatus($e->id, 'completed');
    $again = dc()->enrollments->enroll($purchase->id, $learner->id, 'commerce');

    return $again && $again->enrollmentStatus === 'completed';
});

// -----------------------------------------------------------------------------------------------
section('Grading');

$q = makeQuiz($open, 'main', 3);
$other2 = makeCourse('other');
$foreign = makeQuiz($other2, 'foreign');
$enrollment = dc()->enrollments->getEnrollment($open->id, $learner->id);

check('all four right scores 100 and passes', function() use ($q, $enrollment, $learner) {
    $a = dc()->quizGrader->startAttempt($q['quiz']->id, $enrollment->id, $learner->id);
    $r = dc()->quizGrader->gradeAttempt($a->id, responses(array_combine($q['questions'], $q['correct'])), $learner->id);

    return $r->score == 100 && $r->passed && $r->pointsPossible === 4;
});

check('answering one question right scores 25, not 100', function() use ($q, $enrollment, $learner) {
    $a = dc()->quizGrader->startAttempt($q['quiz']->id, $enrollment->id, $learner->id);
    $r = dc()->quizGrader->gradeAttempt($a->id, responses([$q['questions'][0] => $q['correct'][0]]), $learner->id);

    return $r->score == 25 && !$r->passed && $r->pointsPossible === 4 ?: json_encode([$r->score, $r->pointsPossible]);
});

check('a correct answer from another question scores nothing', function() use ($q, $foreign, $enrollment, $learner) {
    // Every question answered with the first question's correct answer ID...
    $a = dc()->quizGrader->startAttempt($q['quiz']->id, $enrollment->id, $learner->id);
    $same = array_fill_keys($q['questions'], $q['correct'][0]);
    $r1 = dc()->quizGrader->gradeAttempt($a->id, responses($same), $learner->id);

    // ...or with correct answer IDs from a different quiz entirely.
    $a2 = dc()->quizGrader->startAttempt($q['quiz']->id, $enrollment->id, $learner->id);
    $r2 = $a2 ? dc()->quizGrader->gradeAttempt($a2->id, responses(array_combine($q['questions'], $foreign['correct'])), $learner->id) : null;

    return $r1->score == 25 && ($r2 === null || $r2->score == 0) ?: json_encode([$r1->score, $r2?->score]);
});

check('a graded attempt cannot be graded again', function() use ($learner) {
    $done = QuizAttemptRecord::find()->where(['userId' => $learner->id])->andWhere(['not', ['completedAt' => null]])->one();

    return dc()->quizGrader->gradeAttempt((int)$done->id, [], $learner->id) === null;
});

check("another learner's attempt cannot be graded", function() use ($q, $other, $learner, $open) {
    $e = dc()->enrollments->enroll($open->id, $other->id, 'manual');
    $a = dc()->quizGrader->startAttempt($q['quiz']->id, $e->id, $other->id);

    return dc()->quizGrader->gradeAttempt($a->id, responses(array_combine($q['questions'], $q['correct'])), $learner->id) === null
        && QuizAttemptRecord::findOne($a->id)->completedAt === null;
});

check('findOpenAttempt only finds the learner\'s own, open attempt on that quiz', function() use ($q, $other, $learner, $open) {
    $e = dc()->enrollments->getEnrollment($open->id, $other->id);
    $open1 = QuizAttemptRecord::find()->where(['userId' => $other->id, 'completedAt' => null])->one();

    return dc()->quizGrader->findOpenAttempt((int)$open1->id, $other->id, $q['quiz']->id, $e->id) !== null
        && dc()->quizGrader->findOpenAttempt((int)$open1->id, $learner->id, $q['quiz']->id, $e->id) === null
        && dc()->quizGrader->findOpenAttempt((int)$open1->id, $other->id, $q['quiz']->id + 1, $e->id) === null;
});

check('maxAttempts holds (3 used, the 4th is refused)', function() use ($q, $enrollment, $learner) {
    return dc()->quizGrader->startAttempt($q['quiz']->id, $enrollment->id, $learner->id) === null;
});

// -----------------------------------------------------------------------------------------------
section('Schema');

check('the enrollmentMode column exists and round-trips', function() use ($manual) {
    $reloaded = dc()->courses->getById($manual->id);

    return $reloaded?->enrollmentMode === 'manual';
});

check('an invalid mode does not validate', function() {
    $c = new justinholtweb\diploma\elements\Course();
    $c->title = DC_PREFIX . ' invalid';
    $c->enrollmentMode = 'free-for-all';

    return !$c->validate() && $c->hasErrors('enrollmentMode');
});

// -----------------------------------------------------------------------------------------------
sweep();
$settings->headcountPlanMapping = $originalMapping;
Craft::$app->getProjectConfig()->flush();

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
