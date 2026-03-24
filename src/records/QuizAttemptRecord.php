<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $quizId
 * @property int $enrollmentId
 * @property int $userId
 * @property float|null $score
 * @property int $pointsEarned
 * @property int $pointsPossible
 * @property bool $passed
 * @property string $startedAt
 * @property string|null $completedAt
 * @property int|null $timeSpent
 * @property string $dateCreated
 * @property string $uid
 */
class QuizAttemptRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_quiz_attempts}}';
    }
}
