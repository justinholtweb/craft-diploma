<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $quizId
 * @property string $questionType
 * @property string $questionText
 * @property string|null $explanation
 * @property int $points
 * @property int $sortOrder
 * @property array|null $metadata
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class QuestionRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_questions}}';
    }
}
