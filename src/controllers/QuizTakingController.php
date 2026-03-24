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
        $quizId = $request->getRequiredBodyParam('quizId');
        $userId = Craft::$app->getUser()->getId();

        // Find enrollment
        $quiz = Plugin::getInstance()->quizzes->getById($quizId);
        if (!$quiz || !$quiz->courseId) {
            return $this->asFailure('Quiz not found or not linked to a course.');
        }

        $enrollment = Plugin::getInstance()->enrollments->getEnrollment($quiz->courseId, $userId);
        if (!$enrollment) {
            return $this->asFailure('Not enrolled in this course.');
        }

        // Start or resume attempt
        $attemptId = $request->getBodyParam('attemptId');
        if (!$attemptId) {
            $attempt = Plugin::getInstance()->quizGrader->startAttempt($quizId, $enrollment->id, $userId);
            if (!$attempt) {
                return $this->asFailure('Cannot start quiz attempt. Max attempts may have been reached.');
            }
            $attemptId = $attempt->id;
        }

        // Parse responses
        $responses = $request->getBodyParam('responses', []);

        // Grade
        $result = Plugin::getInstance()->quizGrader->gradeAttempt($attemptId, $responses);

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
