<?php

namespace justinholtweb\diploma\services;

use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use justinholtweb\diploma\events\QuizAttemptEvent;
use justinholtweb\diploma\models\QuizAttempt;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\AnswerRecord;
use justinholtweb\diploma\records\QuizAttemptRecord;
use justinholtweb\diploma\records\QuizResponseRecord;

class QuizGrader extends Component
{
    /**
     * @event QuizAttemptEvent Fired after a quiz attempt has been graded.
     * Check `$event->attempt->passed` to react to a pass specifically.
     */
    public const EVENT_AFTER_GRADE_ATTEMPT = 'afterGradeAttempt';

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

    /**
     * Grades an attempt — once.
     *
     * Refuses (returns null) an attempt that is already completed, so a score cannot be
     * overwritten by grading again, and — when `$userId` is given — one that belongs to somebody
     * else. Callers taking a request should always pass the user and use
     * {@see findOpenAttempt()} first.
     *
     * Every question on the quiz counts towards `pointsPossible`, answered or not: scoring only
     * the submitted questions let a learner answer one question and score 100%.
     *
     * @param array<int|string, mixed> $responses Question ID → response data.
     */
    public function gradeAttempt(int $attemptId, array $responses, ?int $userId = null): ?QuizAttempt
    {
        $record = QuizAttemptRecord::findOne($attemptId);
        if (!$record || $record->completedAt !== null) {
            return null;
        }

        if ($userId !== null && (int)$record->userId !== $userId) {
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

        foreach ($questions as $q) {
            $pointsPossible += $q->points;
        }

        foreach ($responses as $questionId => $responseData) {
            $question = $questionMap[$questionId] ?? null;
            if (!$question) {
                continue;
            }

            $questionId = (int)$question->id;
            $responseData = is_array($responseData) ? $responseData : [];

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

                    // The answer must belong to *this* question — otherwise any known-correct
                    // answer ID from anywhere passes every question.
                    $answer = AnswerRecord::findOne(['id' => $answerId, 'questionId' => $questionId]);
                    $isCorrect = $answer && (bool)$answer->isCorrect;
                    break;

                case 'multipleResponse':
                    // "Select all that apply": the learner submits a set of
                    // answer IDs. All correct answers must be chosen, and no
                    // incorrect ones — full marks only for an exact match.
                    $isCorrect = $this->gradeMultipleResponse($questionId, $responseData, $responseRecord);
                    break;

                case 'shortAnswer':
                    $responseRecord->responseText = $responseData['text'] ?? '';
                    // Short answers need manual grading by default,
                    // but we can auto-grade exact matches
                    /** @var AnswerRecord[] $correctAnswers */
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

        if ($this->hasEventHandlers(self::EVENT_AFTER_GRADE_ATTEMPT)) {
            $this->trigger(self::EVENT_AFTER_GRADE_ATTEMPT, new QuizAttemptEvent([
                'attempt' => $attempt,
            ]));
        }

        return $attempt;
    }

    /**
     * The attempt a learner may submit: theirs, on this quiz and enrolment, and not yet graded.
     */
    public function findOpenAttempt(int $attemptId, int $userId, int $quizId, int $enrollmentId): ?QuizAttemptRecord
    {
        /** @var QuizAttemptRecord|null $record */
        $record = QuizAttemptRecord::find()
            ->where([
                'id' => $attemptId,
                'userId' => $userId,
                'quizId' => $quizId,
                'enrollmentId' => $enrollmentId,
                'completedAt' => null,
            ])
            ->one();

        return $record;
    }

    public function getAttempts(int $quizId, int $userId): array
    {
        /** @var QuizAttemptRecord[] $records */
        $records = QuizAttemptRecord::find()
            ->where(['quizId' => $quizId, 'userId' => $userId])
            ->orderBy(['startedAt' => SORT_DESC])
            ->all();

        return array_map(function($record) {
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

    /**
     * Grades a "select all that apply" question. Full marks are awarded only
     * when the submitted answer set exactly equals the set of correct answers
     * (every correct answer chosen, no incorrect answer chosen).
     *
     * The submitted answer IDs are stored as a JSON array in `responseText`
     * so the individual selections are preserved for review.
     */
    private function gradeMultipleResponse(int $questionId, mixed $responseData, QuizResponseRecord $responseRecord): bool
    {
        $submitted = is_array($responseData)
            ? ($responseData['answerIds'] ?? $responseData['answerId'] ?? [])
            : [];
        $submittedIds = array_values(array_unique(array_map('intval', (array)$submitted)));
        sort($submittedIds);

        $responseRecord->responseText = json_encode($submittedIds);

        $correctIds = array_map('intval', AnswerRecord::find()
            ->select(['id'])
            ->where(['questionId' => $questionId, 'isCorrect' => true])
            ->column());
        sort($correctIds);

        // A question with no correct answers can never be answered correctly.
        return !empty($correctIds) && $submittedIds === $correctIds;
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
