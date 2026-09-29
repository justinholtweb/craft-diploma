<?php
/**
 * Diploma request-level checks: a signed-in learner attacking the real front-end endpoints.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-diploma/tests/integration/http.php
 *
 * Covers self-enrolment into closed and sold courses, forged quiz submissions (another learner's
 * attempt, re-grading, answer IDs from other questions, the one-question-scores-100 trick), a
 * lapsed enrolment still sitting quizzes, and a non-admin saving plugin settings.
 */

$root = getcwd();
require $root . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
require __DIR__ . '/_fixtures.php';

use justinholtweb\diploma\records\QuizAttemptRecord;

$base = getenv('DC_CP_BASE') ?: null;
$user = getenv('DC_CP_USER') ?: 'admin';
$pass = getenv('DC_CP_PASS') ?: 'claudepassword';
$host = parse_url(Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), PHP_URL_HOST) ?: 'localhost';
// The site's own host, resolved to this container.
$base ??= 'http://' . $host;
$jar = tempnam(sys_get_temp_dir(), 'dc');

function http(string $method, string $path, array $post = [], array $headers = []): array
{
    global $base, $jar, $host;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => $headers,
        // Craft's `requireUserAgentAndIpForSession` refuses a login with no User-Agent, and PHP's
        // curl sends none — the login answers “Invalid username or password.”
        CURLOPT_USERAGENT => 'diploma-http-checks',
        CURLOPT_RESOLVE => ["$host:443:127.0.0.1", "$host:80:127.0.0.1"],

        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => false,
    ]);
    global $lastLocation;
    $lastLocation = null;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $line) {
        global $lastLocation;
        if (stripos($line, 'Location:') === 0) {
            $lastLocation = trim(substr($line, 9));
        }
        return strlen($line);
    });
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string)curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
}

function csrf(string $html): ?string
{
    return preg_match('/name="CRAFT_CSRF_TOKEN" value="([^"]+)"/', $html, $m)
        || preg_match('/"csrfTokenValue":"([^"]+)"/', $html, $m) ? stripcslashes($m[1]) : null;
}

/** A fresh CSRF token for the current session, from Craft's own endpoint. */
function token(): string
{
    [, $body] = http('GET', '/actions/users/session-info', [], ['Accept: application/json']);
    $json = json_decode($body, true);

    return (string)($json['csrfTokenValue'] ?? '');
}

function post(string $action, array $data): array
{
    [$code, $body] = http('POST', '/actions/' . $action, ['CRAFT_CSRF_TOKEN' => token()] + $data, ['Accept: application/json']);

    return [$code, json_decode($body, true) ?? $body];
}

sweep();

$learner = makeUser('web');
$victim = makeUser('victim');
$openCourse = makeCourse('web open');
$manualCourse = makeCourse('web manual', 'manual');
$purchaseCourse = makeCourse('web purchase', 'purchase');
$quiz = makeQuiz($openCourse, 'web', 5);

// The victim has a started, ungraded attempt the learner will try to grade.
$victimEnrollment = dc()->enrollments->enroll($openCourse->id, $victim->id, 'manual');
$victimAttempt = dc()->quizGrader->startAttempt($quiz['quiz']->id, $victimEnrollment->id, $victim->id);

echo "\nSigning in as a learner\n";

check('the learner signs in on the front end', function() use ($learner) {
    [$code, $body] = http('POST', '/actions/users/login', [
        'CRAFT_CSRF_TOKEN' => token(), 'loginName' => $learner->username, 'password' => DC_PASSWORD,
    ], ['Accept: application/json']);

    return $code === 200 && str_contains($body, 'returnUrl') ?: "HTTP $code $body";
});

echo "\nSelf-enrolment\n";

foreach (['manual' => $manualCourse, 'purchase' => $purchaseCourse] as $mode => $course) {
    check("a $mode course refuses self-enrolment", function() use ($course, $learner) {
        [$code, $json] = post('diploma/progress/enroll', ['courseId' => $course->id]);

        return $code === 400 && dc()->enrollments->getEnrollment($course->id, $learner->id) === null ?: "HTTP $code " . json_encode($json);
    });
}

check('an open course accepts it', function() use ($openCourse, $learner) {
    [$code, $json] = post('diploma/progress/enroll', ['courseId' => $openCourse->id]);

    return $code === 200 && dc()->enrollments->isEnrolled($openCourse->id, $learner->id) ?: "HTTP $code " . json_encode($json);
});

echo "\nQuiz submissions\n";

check("grading the victim's attempt is refused and leaves it untouched", function() use ($quiz, $victimAttempt) {
    [$code, $json] = post('diploma/quiz-taking/submit', [
        'quizId' => $quiz['quiz']->id, 'attemptId' => $victimAttempt->id,
        'responses' => responses(array_combine($quiz['questions'], $quiz['wrong'])),
    ]);

    return $code === 400 && QuizAttemptRecord::findOne($victimAttempt->id)->completedAt === null ?: "HTTP $code " . json_encode($json);
});

check('answering one question right scores 25, not 100', function() use ($quiz) {
    [$code, $json] = post('diploma/quiz-taking/submit', [
        'quizId' => $quiz['quiz']->id,
        'responses' => responses([$quiz['questions'][0] => $quiz['correct'][0]]),
    ]);

    return $code === 200 && ($json['score'] ?? null) == 25 && ($json['passed'] ?? true) === false ?: "HTTP $code " . json_encode($json);
});

check("one question's correct answer posted for every question does not pass", function() use ($quiz) {
    [$code, $json] = post('diploma/quiz-taking/submit', [
        'quizId' => $quiz['quiz']->id,
        'responses' => responses(array_fill_keys($quiz['questions'], $quiz['correct'][0])),
    ]);

    return $code === 200 && ($json['score'] ?? null) == 25 ?: "HTTP $code " . json_encode($json);
});

check('re-submitting a graded attempt is refused', function() use ($quiz, $learner) {
    $graded = QuizAttemptRecord::find()->where(['userId' => $learner->id])->andWhere(['not', ['completedAt' => null]])->one();
    [$code, $json] = post('diploma/quiz-taking/submit', [
        'quizId' => $quiz['quiz']->id, 'attemptId' => $graded->id,
        'responses' => responses(array_combine($quiz['questions'], $quiz['correct'])),
    ]);

    return $code === 400 && (float)QuizAttemptRecord::findOne($graded->id)->score == 25 ?: "HTTP $code " . json_encode($json);
});

check('an honest full pass still works', function() use ($quiz) {
    [$code, $json] = post('diploma/quiz-taking/submit', [
        'quizId' => $quiz['quiz']->id,
        'responses' => responses(array_combine($quiz['questions'], $quiz['correct'])),
    ]);

    return $code === 200 && ($json['score'] ?? null) == 100 && ($json['passed'] ?? false) === true ?: "HTTP $code " . json_encode($json);
});

check('an expired enrolment cannot sit the quiz', function() use ($quiz, $openCourse, $learner) {
    $e = dc()->enrollments->getEnrollment($openCourse->id, $learner->id);
    dc()->enrollments->updateStatus($e->id, 'expired');
    [$code, $json] = post('diploma/quiz-taking/submit', [
        'quizId' => $quiz['quiz']->id,
        'responses' => responses(array_combine($quiz['questions'], $quiz['correct'])),
    ]);
    [$code2] = post('diploma/progress/enroll', ['courseId' => $openCourse->id]);

    return $code === 400 && $code2 === 400 && dc()->enrollments->getEnrollment($openCourse->id, $learner->id)->enrollmentStatus === 'expired'
        ?: "quiz HTTP $code, re-enroll HTTP $code2";
});

echo "\nSettings\n";

check('a non-admin WITH diploma:manageSettings cannot save plugin settings', function() use ($learner) {
    // The permission the audit found sufficient before: CP access plus diploma:manageSettings.
    Craft::$app->getUserPermissions()->saveUserPermissions($learner->id, ['accesscp', 'accessplugin-diploma', 'diploma:managesettings']);
    [$pageCode] = http('GET', '/admin/diploma/settings');
    [$code] = http('POST', '/admin/actions/diploma/settings/save', [
        'CRAFT_CSRF_TOKEN' => token(), 'settings' => ['autoEnrollOnPurchase' => '0'],
    ], ['Accept: application/json']);

    // The settings screen still opens for them (view permission); saving is refused.
    return $pageCode === 200 && $code === 403 && dc()->getSettings()->autoEnrollOnPurchase === true ?: "page HTTP $pageCode, save HTTP $code";
});

check('an admin saving one settings page keeps the other pages\' settings', function() {
    global $jar;
    $plugins = Craft::$app->getPlugins();
    $plugin = dc();
    $original = Craft::$app->getProjectConfig()->get('plugins.diploma.settings');

    // A Headcount mapping, as the Headcount page would have saved it.
    $plugins->savePluginSettings($plugin, ['headcountPlanMapping' => ['plan_check' => '1']] + $plugin->getSettings()->toArray());
    Craft::$app->getProjectConfig()->flush();

    // Sign in as the admin in a fresh session and save the Certificates page's one field.
    $jar = tempnam(sys_get_temp_dir(), 'dc');
    exec('php craft users/impersonate admin 2>&1', $out);
    preg_match('#https?://\S+#', implode("\n", $out), $m);
    $url = parse_url($m[0] ?? '');
    http('GET', ($url['path'] ?? '/admin') . '?' . ($url['query'] ?? ''));
    [$code] = http('POST', '/admin/actions/diploma/settings/save', [
        'CRAFT_CSRF_TOKEN' => token(), 'settings' => ['certificateTitle' => 'Diploma check title'],
    ], ['Accept: application/json']);

    // Read and restore from fresh processes: this one's copy of project config is now stale.
    $after = json_decode((string)shell_exec('php ' . escapeshellarg(__DIR__ . '/_pc.php') . ' get'), true) ?? [];
    shell_exec('php ' . escapeshellarg(__DIR__ . '/_pc.php') . ' set ' . escapeshellarg(json_encode($original ?? [])));

    $mapping = $after['headcountPlanMapping'] ?? null;

    return in_array($code, [200, 302], true) && ($after['certificateTitle'] ?? null) === 'Diploma check title'
        && $mapping !== null && str_contains(json_encode($mapping), 'plan_check')
        ?: "HTTP $code, after: " . json_encode(array_intersect_key($after, array_flip(['certificateTitle', 'headcountPlanMapping'])));
});

sweep();

echo "\n$passed passed, $failed failed\n";
@unlink($jar);
exit($failed === 0 ? 0 : 1);
