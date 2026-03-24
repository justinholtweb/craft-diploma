<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;

use craft\helpers\StringHelper;
use justinholtweb\diploma\elements\Quiz;
use justinholtweb\diploma\models\Answer;
use justinholtweb\diploma\models\Question;
use justinholtweb\diploma\records\AnswerRecord;
use justinholtweb\diploma\records\QuestionRecord;

class Quizzes extends Component
{
    public function getById(int $id): ?Quiz
    {
        return Quiz::find()->id($id)->one();
    }

    public function save(Quiz $quiz): bool
    {
        return Craft::$app->getElements()->saveElement($quiz);
    }

    public function delete(Quiz $quiz): bool
    {
        return Craft::$app->getElements()->deleteElement($quiz);
    }

    // --- Question management ---

    public function getQuestionsByQuiz(int $quizId): array
    {
        $records = QuestionRecord::find()
            ->where(['quizId' => $quizId])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        $questions = [];
        foreach ($records as $record) {
            $question = new Question();
            $question->id = $record->id;
            $question->quizId = $record->quizId;
            $question->questionType = $record->questionType;
            $question->questionText = $record->questionText;
            $question->explanation = $record->explanation;
            $question->points = $record->points;
            $question->sortOrder = $record->sortOrder;
            $question->metadata = $record->metadata ? json_decode($record->metadata, true) : null;
            $question->answers = $this->getAnswersByQuestion($record->id);
            $questions[] = $question;
        }

        return $questions;
    }

    public function getQuestionCount(int $quizId): int
    {
        return (int)QuestionRecord::find()
            ->where(['quizId' => $quizId])
            ->count();
    }

    public function getQuestionById(int $id): ?Question
    {
        $record = QuestionRecord::findOne($id);
        if (!$record) {
            return null;
        }

        $question = new Question();
        $question->id = $record->id;
        $question->quizId = $record->quizId;
        $question->questionType = $record->questionType;
        $question->questionText = $record->questionText;
        $question->explanation = $record->explanation;
        $question->points = $record->points;
        $question->sortOrder = $record->sortOrder;
        $question->metadata = $record->metadata ? json_decode($record->metadata, true) : null;
        $question->answers = $this->getAnswersByQuestion($record->id);

        return $question;
    }

    public function saveQuestion(Question $question): bool
    {
        if ($question->id) {
            $record = QuestionRecord::findOne($question->id);
            if (!$record) {
                return false;
            }
        } else {
            $record = new QuestionRecord();
            $record->uid = StringHelper::UUID();
        }

        $record->quizId = $question->quizId;
        $record->questionType = $question->questionType;
        $record->questionText = $question->questionText;
        $record->explanation = $question->explanation;
        $record->points = $question->points;
        $record->sortOrder = $question->sortOrder;
        $record->metadata = $question->metadata ? json_encode($question->metadata) : null;

        if (!$record->save()) {
            return false;
        }

        $question->id = $record->id;

        // Save answers
        if (!empty($question->answers)) {
            $this->saveAnswers($question->id, $question->answers);
        }

        return true;
    }

    public function deleteQuestion(int $questionId): bool
    {
        $record = QuestionRecord::findOne($questionId);
        if (!$record) {
            return false;
        }

        return (bool)$record->delete();
    }

    // --- Answer management ---

    public function getAnswersByQuestion(int $questionId): array
    {
        $records = AnswerRecord::find()
            ->where(['questionId' => $questionId])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        $answers = [];
        foreach ($records as $record) {
            $answer = new Answer();
            $answer->id = $record->id;
            $answer->questionId = $record->questionId;
            $answer->answerText = $record->answerText;
            $answer->isCorrect = (bool)$record->isCorrect;
            $answer->sortOrder = $record->sortOrder;
            $answers[] = $answer;
        }

        return $answers;
    }

    public function saveAnswers(int $questionId, array $answers): void
    {
        // Delete existing answers
        AnswerRecord::deleteAll(['questionId' => $questionId]);

        // Save new answers
        foreach ($answers as $order => $answerData) {
            $record = new AnswerRecord();
            $record->questionId = $questionId;
            $record->uid = StringHelper::UUID();

            if ($answerData instanceof Answer) {
                $record->answerText = $answerData->answerText;
                $record->isCorrect = $answerData->isCorrect;
                $record->sortOrder = $answerData->sortOrder;
            } else {
                $record->answerText = $answerData['answerText'] ?? '';
                $record->isCorrect = (bool)($answerData['isCorrect'] ?? false);
                $record->sortOrder = $answerData['sortOrder'] ?? $order;
            }

            $record->save(false);
        }
    }
}
