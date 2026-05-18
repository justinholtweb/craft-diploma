<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\helpers\Cp;
use craft\web\Controller;
use justinholtweb\diploma\elements\Course;
use justinholtweb\diploma\elements\Quiz;
use justinholtweb\diploma\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class QuizzesController extends Controller
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
        return $this->renderTemplate('diploma/quizzes/_index', [
            'elementType' => Quiz::class,
        ]);
    }

    public function actionEdit(?int $quizId = null, ?Quiz $quiz = null): Response
    {
        if ($quiz === null) {
            if ($quizId !== null) {
                $quiz = Plugin::getInstance()->quizzes->getById($quizId);
                if (!$quiz) {
                    throw new NotFoundHttpException('Quiz not found.');
                }
            } else {
                $quiz = new Quiz();
            }
        }

        $isNew = !$quiz->id;

        // Course options
        $courses = Course::find()->all();
        $courseOptions = [['label' => '— None —', 'value' => '']];
        foreach ($courses as $course) {
            $courseOptions[] = ['label' => $course->title, 'value' => $course->id];
        }

        // Questions
        $questions = $isNew ? [] : $quiz->getQuestions();

        return $this->renderTemplate('diploma/quizzes/_edit', [
            'quiz' => $quiz,
            'isNew' => $isNew,
            'courseOptions' => $courseOptions,
            'questions' => $questions,
            'title' => $isNew ? Craft::t('diploma', 'New Quiz') : $quiz->title,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('diploma:manageQuizzes');

        $request = Craft::$app->getRequest();
        $quizId = $request->getBodyParam('quizId');

        if ($quizId) {
            $quiz = Plugin::getInstance()->quizzes->getById($quizId);
            if (!$quiz) {
                throw new NotFoundHttpException('Quiz not found.');
            }
        } else {
            $quiz = new Quiz();
            $quiz->siteId = (Cp::requestedSite() ?? Craft::$app->getSites()->getCurrentSite())->id;
        }

        $quiz->title = $request->getBodyParam('title');
        $quiz->courseId = $request->getBodyParam('courseId') ?: null;
        $quiz->lessonId = $request->getBodyParam('lessonId') ?: null;
        $quiz->passingScore = (int)$request->getBodyParam('passingScore', 70);
        $quiz->timeLimit = $request->getBodyParam('timeLimit') ?: null;
        $quiz->maxAttempts = $request->getBodyParam('maxAttempts') ?: null;
        $quiz->randomizeQuestions = (bool)$request->getBodyParam('randomizeQuestions');
        $quiz->questionsPerAttempt = $request->getBodyParam('questionsPerAttempt') ?: null;

        if (!Craft::$app->getElements()->saveElement($quiz)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t save quiz.'));
            Craft::$app->getUrlManager()->setRouteParams(['quiz' => $quiz]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Quiz saved.'));

        return $this->redirectToPostedUrl($quiz);
    }
}
