<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use justinholtweb\diploma\models\QuizAttempt;
use justinholtweb\diploma\models\QuizResponse;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\AnswerRecord;
use justinholtweb\diploma\records\QuizAttemptRecord;
use justinholtweb\diploma\records\QuizResponseRecord;

class QuizGrader extends Component
{
    public function startAttempt(int $quizId, int $enrollmentId, int $userId): ?QuizAttempt
    {
        $quiz = Plugin::getInstance()->quizzes->getById($quizId);
        if (!$quiz) {
            return null;
        }

        // Check max attempts
        if ($quiz->maxAttempts) {
            $attemptCount = (int)QuizAttemptRecord::find()
                ->where(['quizId' => $quizId, 'enrollmentId' => $enrollmentId])
                ->count();

            if ($attemptCount >= $quiz->maxAttempts) {
                return null;
            }
        }

        $record = new QuizAttemptRecord();
        $record->quizId = $quizId;
        $record->enrollmentId = $enrollmentId;
        $record->userId = $userId;
        $record->startedAt = Db::prepareDateForDb(new \DateTime());
        $record->uid = StringHelper::UUID();

        if (!$record->save()) {
            return null;
        }

        $attempt = new QuizAttempt();
        $attempt->id = $record->id;
        $attempt->quizId = $quizId;
        $attempt->enrollmentId = $enrollmentId;
        $attempt->userId = $userId;
        $attempt->startedAt = $record->startedAt;

        return $attempt;
    }

    public function gradeAttempt(int $attemptId, array $responses): ?QuizAttempt
    {
        $record = QuizAttemptRecord::findOne($attemptId);
        if (!$record) {
            return null;
        }

        $quiz = Plugin::getInstance()->quizzes->getById($record->quizId);
        if (!$quiz) {
            return null;
        }

        $questions = Plugin::getInstance()->quizzes->getQuestionsByQuiz($quiz->id);
        $questionMap = [];
        foreach ($questions as $q) {
            $questionMap[$q->id] = $q;
        }

        $pointsEarned = 0;
        $pointsPossible = 0;

        foreach ($responses as $questionId => $responseData) {
            $question = $questionMap[$questionId] ?? null;
            if (!$question) {
                continue;
            }

            $pointsPossible += $question->points;

            $responseRecord = new QuizResponseRecord();
            $responseRecord->attemptId = $attemptId;
            $responseRecord->questionId = $questionId;
            $responseRecord->uid = StringHelper::UUID();

            $isCorrect = false;

            switch ($question->questionType) {
                case 'multipleChoice':
                case 'trueFalse':
                    $answerId = (int)($responseData['answerId'] ?? 0);
                    $responseRecord->answerId = $answerId;

                    // Check if the selected answer is correct
                    $answer = AnswerRecord::findOne($answerId);
                    $isCorrect = $answer && (bool)$answer->isCorrect;
                    break;

                case 'shortAnswer':
                    $responseRecord->responseText = $responseData['text'] ?? '';
                    // Short answers need manual grading by default,
                    // but we can auto-grade exact matches
                    $correctAnswers = AnswerRecord::find()
                        ->where(['questionId' => $questionId, 'isCorrect' => true])
                        ->all();

                    foreach ($correctAnswers as $correctAnswer) {
                        if (strtolower(trim($responseRecord->responseText)) === strtolower(trim($correctAnswer->answerText))) {
                            $isCorrect = true;
                            break;
                        }
                    }
                    break;

                case 'matching':
                    $responseRecord->responseText = json_encode($responseData['pairs'] ?? []);
                    // Matching grading: compare pairs against metadata
                    $isCorrect = $this->gradeMatching($question, $responseData['pairs'] ?? []);
                    break;
            }

            $responseRecord->isCorrect = $isCorrect;
            $responseRecord->pointsAwarded = $isCorrect ? $question->points : 0;
            $responseRecord->save(false);

            if ($isCorrect) {
                $pointsEarned += $question->points;
            }
        }

        // Calculate score
        $score = $pointsPossible > 0 ? round(($pointsEarned / $pointsPossible) * 100, 2) : 0;
        $passed = $score >= $quiz->passingScore;

        // Update attempt record
        $record->score = $score;
        $record->pointsEarned = $pointsEarned;
        $record->pointsPossible = $pointsPossible;
        $record->passed = $passed;
        $record->completedAt = Db::prepareDateForDb(new \DateTime());

        if ($record->startedAt) {
            $started = new \DateTime($record->startedAt);
            $record->timeSpent = (new \DateTime())->getTimestamp() - $started->getTimestamp();
        }

        $record->save(false);

        // Build result model
        $attempt = new QuizAttempt();
        $attempt->id = $record->id;
        $attempt->quizId = $record->quizId;
        $attempt->enrollmentId = $record->enrollmentId;
        $attempt->userId = $record->userId;
        $attempt->score = $score;
        $attempt->pointsEarned = $pointsEarned;
        $attempt->pointsPossible = $pointsPossible;
        $attempt->passed = $passed;
        $attempt->startedAt = $record->startedAt;
        $attempt->completedAt = $record->completedAt;
        $attempt->timeSpent = $record->timeSpent;

        return $attempt;
    }

    public function getAttempts(int $quizId, int $userId): array
    {
        $records = QuizAttemptRecord::find()
            ->where(['quizId' => $quizId, 'userId' => $userId])
            ->orderBy(['startedAt' => SORT_DESC])
            ->all();

        return array_map(function ($record) {
            $attempt = new QuizAttempt();
            $attempt->id = $record->id;
            $attempt->quizId = $record->quizId;
            $attempt->enrollmentId = $record->enrollmentId;
            $attempt->userId = $record->userId;
            $attempt->score = $record->score;
            $attempt->pointsEarned = $record->pointsEarned;
            $attempt->pointsPossible = $record->pointsPossible;
            $attempt->passed = (bool)$record->passed;
            $attempt->startedAt = $record->startedAt;
            $attempt->completedAt = $record->completedAt;
            $attempt->timeSpent = $record->timeSpent;
            return $attempt;
        }, $records);
    }

    public function getBestScore(int $quizId, int $userId): ?float
    {
        return QuizAttemptRecord::find()
            ->where(['quizId' => $quizId, 'userId' => $userId])
            ->andWhere(['not', ['completedAt' => null]])
            ->max('score');
    }

    private function gradeMatching($question, array $pairs): bool
    {
        if (!$question->metadata || !isset($question->metadata['pairs'])) {
            return false;
        }

        $correctPairs = $question->metadata['pairs'];
        $allCorrect = true;

        foreach ($correctPairs as $pair) {
            $left = $pair['left'] ?? '';
            $right = $pair['right'] ?? '';
            $submitted = $pairs[$left] ?? null;

            if ($submitted !== $right) {
                $allCorrect = false;
                break;
            }
        }

        return $allCorrect;
    }
}
