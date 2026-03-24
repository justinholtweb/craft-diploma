<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $questionId
 * @property string $answerText
 * @property bool $isCorrect
 * @property int $sortOrder
 * @property string $dateCreated
 * @property string $uid
 */
class AnswerRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_answers}}';
    }
}
