<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $enrollmentId
 * @property int $lessonId
 * @property string|null $completedAt
 * @property int $timeSpent
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class ProgressRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_progress}}';
    }
}
