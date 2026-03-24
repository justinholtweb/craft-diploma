<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\EnrollmentRecord;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class EnrollmentsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('diploma:manageEnrollments');

        return true;
    }

    public function actionIndex(): Response
    {
        $enrollments = EnrollmentRecord::find()
            ->orderBy(['enrolledAt' => SORT_DESC])
            ->all();

        return $this->renderTemplate('diploma/enrollments/_index', [
            'enrollments' => $enrollments,
        ]);
    }

    public function actionDetail(int $enrollmentId): Response
    {
        $enrollment = Plugin::getInstance()->enrollments->getEnrollmentById($enrollmentId);
        if (!$enrollment) {
            throw new NotFoundHttpException('Enrollment not found.');
        }

        $course = Plugin::getInstance()->courses->getById($enrollment->courseId);
        $user = Craft::$app->getUsers()->getUserById($enrollment->userId);
        $progress = Plugin::getInstance()->progressTracker->getCourseProgress($enrollment->courseId, $enrollment->userId);

        return $this->renderTemplate('diploma/enrollments/_detail', [
            'enrollment' => $enrollment,
            'course' => $course,
            'user' => $user,
            'progress' => $progress,
        ]);
    }

    public function actionUpdateStatus(): ?Response
    {
        $this->requirePostRequest();

        $enrollmentId = Craft::$app->getRequest()->getRequiredBodyParam('enrollmentId');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (!Plugin::getInstance()->enrollments->updateStatus($enrollmentId, $status)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t update enrollment.'));
            return $this->redirect("diploma/enrollments/{$enrollmentId}");
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Enrollment updated.'));

        return $this->redirect("diploma/enrollments/{$enrollmentId}");
    }

    public function actionEnrollUser(): ?Response
    {
        $this->requirePostRequest();

        $courseId = Craft::$app->getRequest()->getRequiredBodyParam('courseId');
        $userId = Craft::$app->getRequest()->getRequiredBodyParam('userId');

        $enrollment = Plugin::getInstance()->enrollments->enroll($courseId, $userId, 'manual');
        if (!$enrollment) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t enroll user.'));
            return $this->redirect('diploma/enrollments');
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'User enrolled.'));

        return $this->redirect("diploma/enrollments/{$enrollment->id}");
    }
}
