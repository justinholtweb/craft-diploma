<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\Plugin;
use yii\web\Response;

class ProgressController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionEnroll(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $courseId = Craft::$app->getRequest()->getRequiredBodyParam('courseId');
        $userId = Craft::$app->getUser()->getId();

        $enrollment = Plugin::getInstance()->enrollments->enroll($courseId, $userId, 'self');

        if (!$enrollment) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asFailure('Could not enroll in course.');
            }
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Could not enroll in course.'));
            return $this->redirect(Craft::$app->getRequest()->getReferrer());
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asSuccess('Enrolled successfully.', ['enrollmentId' => $enrollment->id]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'You are now enrolled.'));
        return $this->redirectToPostedUrl();
    }

    public function actionCompleteLesson(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $lessonId = Craft::$app->getRequest()->getRequiredBodyParam('lessonId');
        $timeSpent = (int)Craft::$app->getRequest()->getBodyParam('timeSpent', 0);
        $userId = Craft::$app->getUser()->getId();

        // Find the enrollment for this lesson's course
        $lesson = Plugin::getInstance()->lessons->getById($lessonId);
        if (!$lesson) {
            return $this->asFailure('Lesson not found.');
        }

        $enrollment = Plugin::getInstance()->enrollments->getEnrollment($lesson->courseId, $userId);
        if (!$enrollment) {
            return $this->asFailure('Not enrolled in this course.');
        }

        // Check if lesson is unlocked
        if (!Plugin::getInstance()->progressTracker->isLessonUnlocked($lesson, $userId)) {
            return $this->asFailure('Lesson is not yet unlocked.');
        }

        $success = Plugin::getInstance()->progressTracker->completeLesson($enrollment->id, $lessonId, $timeSpent);

        if (!$success) {
            return $this->asFailure('Could not mark lesson as complete.');
        }

        $progress = Plugin::getInstance()->progressTracker->getCourseProgress($lesson->courseId, $userId);

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asSuccess('Lesson completed.', [
                'progress' => [
                    'percentage' => $progress->percentage,
                    'completedLessons' => $progress->completedLessons,
                    'totalLessons' => $progress->totalLessons,
                ],
            ]);
        }

        return $this->redirectToPostedUrl();
    }

    public function actionCourseProgress(int $courseId): Response
    {
        $this->requireLogin();

        $userId = Craft::$app->getUser()->getId();
        $progress = Plugin::getInstance()->progressTracker->getCourseProgress($courseId, $userId);

        return $this->asJson([
            'percentage' => $progress->percentage,
            'completedLessons' => $progress->completedLessons,
            'totalLessons' => $progress->totalLessons,
            'totalTimeSpent' => $progress->totalTimeSpent,
        ]);
    }
}
