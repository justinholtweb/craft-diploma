<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\Plugin;
use yii\web\Response;

class QuizTakingController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionSubmit(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $quizId = (int)$request->getRequiredBodyParam('quizId');
        $userId = (int)Craft::$app->getUser()->getId();

        // Find enrollment
        $quiz = Plugin::getInstance()->quizzes->getById($quizId);
        if (!$quiz || !$quiz->courseId) {
            return $this->asFailure('Quiz not found or not linked to a course.');
        }

        // An expired or cancelled enrolment does not get to sit the quiz (or earn the
        // certificate a pass issues). Completed learners may retake.
        $enrollment = Plugin::getInstance()->enrollments->getEnrollment($quiz->courseId, $userId);
        if (!$enrollment || !in_array($enrollment->enrollmentStatus, ['active', 'completed'], true)) {
            return $this->asFailure('Not enrolled in this course.');
        }

        $grader = Plugin::getInstance()->quizGrader;

        // Resume an attempt only if it is this learner's, on this quiz and enrolment, and not yet
        // graded — otherwise a posted attemptId overwrote anybody's score, or re-graded the
        // learner's own attempt as often as they liked and so never used up maxAttempts.
        $attemptId = (int)$request->getBodyParam('attemptId');
        if ($attemptId) {
            if (!$grader->findOpenAttempt($attemptId, $userId, $quizId, (int)$enrollment->id)) {
                return $this->asFailure('That quiz attempt cannot be submitted.');
            }
        } else {
            $attempt = $grader->startAttempt($quizId, (int)$enrollment->id, $userId);
            if (!$attempt) {
                return $this->asFailure('Cannot start quiz attempt. Max attempts may have been reached.');
            }
            $attemptId = (int)$attempt->id;
        }

        $responses = $request->getBodyParam('responses', []);

        $result = $grader->gradeAttempt($attemptId, is_array($responses) ? $responses : [], $userId);

        if (!$result) {
            return $this->asFailure('Could not grade quiz.');
        }

        if ($request->getAcceptsJson()) {
            return $this->asSuccess('Quiz submitted.', [
                'score' => $result->score,
                'pointsEarned' => $result->pointsEarned,
                'pointsPossible' => $result->pointsPossible,
                'passed' => $result->passed,
                'timeSpent' => $result->timeSpent,
            ]);
        }

        if ($result->passed) {
            Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Quiz passed with {score}%!', ['score' => $result->score]));
        } else {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Quiz not passed. Score: {score}%', ['score' => $result->score]));
        }

        return $this->redirectToPostedUrl();
    }
}
