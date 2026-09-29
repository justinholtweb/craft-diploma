<?php
/**
 * Shared fixtures for the Diploma integration checks: a course with a lesson and a four-question
 * quiz, a second course whose answers must never count for the first, and learners.
 *
 * Everything is named `Diploma check …` / `diplomacheck…` and removed by sweep().
 */

use craft\elements\User;
use justinholtweb\diploma\elements\Course;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\elements\Quiz;
use justinholtweb\diploma\models\Question;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\AnswerRecord;
use justinholtweb\diploma\records\EnrollmentRecord;

const DC_PREFIX = 'Diploma check';
const DC_USER = 'diplomacheck';
const DC_PASSWORD = 'Diploma-check-pass-2026';

$passed = 0;
$failed = 0;

function check(string $label, callable $test): void
{
    global $passed, $failed;

    try {
        $result = $test();

        if ($result === true) {
            $passed++;
            echo "  ✓ $label\n";

            return;
        }

        $failed++;
        echo "  ✗ $label\n    " . (is_string($result) ? $result : var_export($result, true)) . "\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✗ $label\n    " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    }
}

function section(string $title): void
{
    echo "\n$title\n";
}

function dc(): Plugin
{
    return Plugin::getInstance();
}

function sweep(): void
{
    $elements = Craft::$app->getElements();

    foreach ([Quiz::class, Lesson::class, Course::class] as $type) {
        foreach ($type::find()->title(DC_PREFIX . '*')->status(null)->trashed(null)->all() as $el) {
            if ($el instanceof Course) {
                EnrollmentRecord::deleteAll(['courseId' => $el->id]);
            }
            $elements->deleteElement($el, true);
        }
    }

    foreach (User::find()->username(DC_USER . '*')->status(null)->all() as $user) {
        $elements->deleteElement($user, true);
    }
}

function makeUser(string $suffix): User
{
    $user = new User();
    $user->username = DC_USER . $suffix;
    $user->email = DC_USER . $suffix . '@example.test';
    $user->newPassword = DC_PASSWORD;
    $user->active = true;

    if (!Craft::$app->getElements()->saveElement($user, false)) {
        throw new RuntimeException('User did not save: ' . json_encode($user->getErrors()));
    }

    return $user;
}

function makeCourse(string $suffix, string $mode = 'open', string $status = 'published'): Course
{
    $course = new Course();
    $course->title = DC_PREFIX . ' ' . $suffix;
    $course->courseStatus = $status;
    $course->enrollmentMode = $mode;

    if (!dc()->courses->save($course)) {
        throw new RuntimeException('Course did not save: ' . json_encode($course->getErrors()));
    }

    return $course;
}

/**
 * A quiz with four one-point multiple-choice questions, passing at 70%.
 *
 * @return array{quiz: Quiz, questions: int[], correct: int[], wrong: int[]} Question IDs with the
 *   IDs of each question's correct and wrong answer, in the same order.
 */
function makeQuiz(Course $course, string $suffix, ?int $maxAttempts = null): array
{
    $quiz = new Quiz();
    $quiz->title = DC_PREFIX . ' quiz ' . $suffix;
    $quiz->courseId = $course->id;
    $quiz->passingScore = 70;
    $quiz->maxAttempts = $maxAttempts;

    if (!dc()->quizzes->save($quiz)) {
        throw new RuntimeException('Quiz did not save: ' . json_encode($quiz->getErrors()));
    }

    $out = ['quiz' => $quiz, 'questions' => [], 'correct' => [], 'wrong' => []];

    for ($i = 1; $i <= 4; $i++) {
        $q = new Question();
        $q->quizId = $quiz->id;
        $q->questionType = 'multipleChoice';
        $q->questionText = "Question $i";
        $q->points = 1;
        $q->sortOrder = $i;

        if (!dc()->quizzes->saveQuestion($q)) {
            throw new RuntimeException('Question did not save: ' . json_encode($q->getErrors()));
        }

        dc()->quizzes->saveAnswers((int)$q->id, [
            ['answerText' => 'Right', 'isCorrect' => true],
            ['answerText' => 'Wrong', 'isCorrect' => false],
        ]);

        $out['questions'][] = (int)$q->id;
        $out['correct'][] = (int)AnswerRecord::find()->select('id')->where(['questionId' => $q->id, 'isCorrect' => true])->scalar();
        $out['wrong'][] = (int)AnswerRecord::find()->select('id')->where(['questionId' => $q->id, 'isCorrect' => false])->scalar();
    }

    return $out;
}

/** @param int[] $answerIds Question ID → answer ID. */
function responses(array $answerIds): array
{
    $r = [];

    foreach ($answerIds as $questionId => $answerId) {
        $r[$questionId] = ['answerId' => $answerId];
    }

    return $r;
}
