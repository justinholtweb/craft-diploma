<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\elements\Course;
use justinholtweb\diploma\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class CoursesController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('diploma:accessPlugin');

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('diploma/courses/_index', [
            'elementType' => Course::class,
        ]);
    }

    public function actionEdit(?int $courseId = null, ?Course $course = null): Response
    {
        if ($course === null) {
            if ($courseId !== null) {
                $course = Plugin::getInstance()->courses->getById($courseId);
                if (!$course) {
                    throw new NotFoundHttpException('Course not found.');
                }
            } else {
                $course = new Course();
            }
        }

        $isNew = !$course->id;

        $difficultyOptions = [
            ['label' => '— None —', 'value' => ''],
            ['label' => 'Beginner', 'value' => 'beginner'],
            ['label' => 'Intermediate', 'value' => 'intermediate'],
            ['label' => 'Advanced', 'value' => 'advanced'],
        ];

        $statusOptions = [
            ['label' => 'Draft', 'value' => 'draft'],
            ['label' => 'Published', 'value' => 'published'],
            ['label' => 'Archived', 'value' => 'archived'],
        ];

        $lessons = $isNew ? [] : $course->getLessons();

        return $this->renderTemplate('diploma/courses/_edit', [
            'course' => $course,
            'isNew' => $isNew,
            'difficultyOptions' => $difficultyOptions,
            'statusOptions' => $statusOptions,
            'lessons' => $lessons,
            'title' => $isNew ? Craft::t('diploma', 'New Course') : $course->title,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('diploma:manageCourses');

        $request = Craft::$app->getRequest();
        $courseId = $request->getBodyParam('courseId');

        if ($courseId) {
            $course = Plugin::getInstance()->courses->getById($courseId);
            if (!$course) {
                throw new NotFoundHttpException('Course not found.');
            }
        } else {
            $course = new Course();
        }

        $course->title = $request->getBodyParam('title');
        $course->courseStatus = $request->getBodyParam('courseStatus', 'draft');
        $course->difficultyLevel = $request->getBodyParam('difficultyLevel') ?: null;
        $course->estimatedDuration = $request->getBodyParam('estimatedDuration') ?: null;
        $course->enrollmentLimit = $request->getBodyParam('enrollmentLimit') ?: null;
        $course->passingScore = $request->getBodyParam('passingScore') ?: null;

        if (!Craft::$app->getElements()->saveElement($course)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t save course.'));
            Craft::$app->getUrlManager()->setRouteParams(['course' => $course]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Course saved.'));

        return $this->redirectToPostedUrl($course);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('diploma:deleteCourses');

        $courseId = Craft::$app->getRequest()->getRequiredBodyParam('courseId');
        $course = Plugin::getInstance()->courses->getById($courseId);

        if (!$course) {
            throw new NotFoundHttpException('Course not found.');
        }

        if (!Plugin::getInstance()->courses->delete($course)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t delete course.'));
            return $this->redirect("diploma/courses/{$courseId}");
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Course deleted.'));

        return $this->redirect('diploma/courses');
    }
}
