<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class LessonsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('diploma:manageCourses');

        return true;
    }

    public function actionIndex(int $courseId): Response
    {
        $course = Plugin::getInstance()->courses->getById($courseId);
        if (!$course) {
            throw new NotFoundHttpException('Course not found.');
        }

        $lessons = Plugin::getInstance()->lessons->getLessonsByCourse($courseId);

        return $this->renderTemplate('diploma/lessons/_index', [
            'course' => $course,
            'lessons' => $lessons,
        ]);
    }

    public function actionEdit(int $courseId, ?int $lessonId = null, ?Lesson $lesson = null): Response
    {
        $course = Plugin::getInstance()->courses->getById($courseId);
        if (!$course) {
            throw new NotFoundHttpException('Course not found.');
        }

        if ($lesson === null) {
            if ($lessonId !== null) {
                $lesson = Plugin::getInstance()->lessons->getById($lessonId);
                if (!$lesson) {
                    throw new NotFoundHttpException('Lesson not found.');
                }
            } else {
                $lesson = new Lesson();
                $lesson->courseId = $courseId;
                $lesson->sortOrder = Plugin::getInstance()->lessons->getNextSortOrder($courseId);
            }
        }

        $isNew = !$lesson->id;

        $typeOptions = [
            ['label' => 'Text', 'value' => 'text'],
            ['label' => 'Video', 'value' => 'video'],
            ['label' => 'Mixed', 'value' => 'mixed'],
        ];

        // Get other lessons for prerequisite dropdown
        $otherLessons = Plugin::getInstance()->lessons->getLessonsByCourse($courseId);
        $prerequisiteOptions = [['label' => '— None —', 'value' => '']];
        foreach ($otherLessons as $otherLesson) {
            if ($otherLesson->id !== $lesson->id) {
                $prerequisiteOptions[] = ['label' => $otherLesson->title, 'value' => $otherLesson->id];
            }
        }

        return $this->renderTemplate('diploma/lessons/_edit', [
            'course' => $course,
            'lesson' => $lesson,
            'isNew' => $isNew,
            'typeOptions' => $typeOptions,
            'prerequisiteOptions' => $prerequisiteOptions,
            'title' => $isNew
                ? Craft::t('diploma', 'New Lesson')
                : $lesson->title,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $lessonId = $request->getBodyParam('lessonId');
        $courseId = $request->getRequiredBodyParam('courseId');

        if ($lessonId) {
            $lesson = Plugin::getInstance()->lessons->getById($lessonId);
            if (!$lesson) {
                throw new NotFoundHttpException('Lesson not found.');
            }
        } else {
            $lesson = new Lesson();
        }

        $lesson->courseId = $courseId;
        $lesson->title = $request->getBodyParam('title');
        $lesson->lessonType = $request->getBodyParam('lessonType', 'text');
        $lesson->estimatedDuration = $request->getBodyParam('estimatedDuration') ?: null;
        $lesson->sortOrder = (int)$request->getBodyParam('sortOrder', 0);
        $lesson->prerequisiteLessonId = $request->getBodyParam('prerequisiteLessonId') ?: null;
        $lesson->dripDays = $request->getBodyParam('dripDays') ?: null;
        $lesson->isFree = (bool)$request->getBodyParam('isFree');

        if (!Craft::$app->getElements()->saveElement($lesson)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t save lesson.'));
            Craft::$app->getUrlManager()->setRouteParams(['lesson' => $lesson]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Lesson saved.'));

        return $this->redirectToPostedUrl($lesson);
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $lessonIds = Craft::$app->getRequest()->getRequiredBodyParam('ids');

        Plugin::getInstance()->lessons->reorder($lessonIds);

        return $this->asSuccess('Lessons reordered.');
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $lessonId = Craft::$app->getRequest()->getRequiredBodyParam('lessonId');
        $lesson = Plugin::getInstance()->lessons->getById($lessonId);

        if (!$lesson) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        $courseId = $lesson->courseId;

        if (!Plugin::getInstance()->lessons->delete($lesson)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t delete lesson.'));
            return $this->redirect("diploma/courses/{$courseId}/lessons/{$lessonId}");
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Lesson deleted.'));

        return $this->redirect("diploma/courses/{$courseId}");
    }
}
