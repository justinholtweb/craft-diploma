<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $attemptId
 * @property int $questionId
 * @property int|null $answerId
 * @property string|null $responseText
 * @property bool|null $isCorrect
 * @property int $pointsAwarded
 * @property string $dateCreated
 * @property string $uid
 */
class QuizResponseRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_quiz_responses}}';
    }
}
