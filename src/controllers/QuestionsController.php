<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\models\Question;
use justinholtweb\diploma\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class QuestionsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('diploma:manageQuizzes');

        return true;
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $question = new Question();
        $question->id = $request->getBodyParam('questionId') ?: null;
        $question->quizId = $request->getRequiredBodyParam('quizId');
        $question->questionType = $request->getBodyParam('questionType', 'multipleChoice');
        $question->questionText = $request->getRequiredBodyParam('questionText');
        $question->explanation = $request->getBodyParam('explanation');
        $question->points = (int)$request->getBodyParam('points', 1);
        $question->sortOrder = (int)$request->getBodyParam('sortOrder', 0);

        // Parse answers
        $answersData = $request->getBodyParam('answers', []);
        $answers = [];
        foreach ($answersData as $order => $data) {
            $answers[] = [
                'answerText' => $data['answerText'] ?? '',
                'isCorrect' => (bool)($data['isCorrect'] ?? false),
                'sortOrder' => $order,
            ];
        }
        $question->answers = $answers;

        if (!Plugin::getInstance()->quizzes->saveQuestion($question)) {
            return $this->asFailure('Couldn\'t save question.');
        }

        return $this->asSuccess('Question saved.', [
            'question' => [
                'id' => $question->id,
                'questionType' => $question->questionType,
                'questionText' => $question->questionText,
                'explanation' => $question->explanation,
                'points' => $question->points,
                'sortOrder' => $question->sortOrder,
                'answers' => $answers,
            ],
        ]);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $questionId = Craft::$app->getRequest()->getRequiredBodyParam('questionId');

        if (!Plugin::getInstance()->quizzes->deleteQuestion($questionId)) {
            return $this->asFailure('Couldn\'t delete question.');
        }

        return $this->asSuccess('Question deleted.');
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $questionIds = Craft::$app->getRequest()->getRequiredBodyParam('ids');

        $db = Craft::$app->getDb();
        foreach ($questionIds as $order => $id) {
            $db->createCommand()
                ->update('{{%diploma_questions}}', [
                    'sortOrder' => $order,
                ], ['id' => $id])
                ->execute();
        }

        return $this->asSuccess('Questions reordered.');
    }
}
