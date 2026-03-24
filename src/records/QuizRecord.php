<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $courseId
 * @property int|null $lessonId
 * @property int $passingScore
 * @property int|null $timeLimit
 * @property int|null $maxAttempts
 * @property bool $randomizeQuestions
 * @property int|null $questionsPerAttempt
 * @property int $sortOrder
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class QuizRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_quizzes}}';
    }
}
