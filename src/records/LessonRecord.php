<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $courseId
 * @property string $lessonType
 * @property int|null $estimatedDuration
 * @property int $sortOrder
 * @property int|null $prerequisiteLessonId
 * @property int|null $dripDays
 * @property bool $isFree
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class LessonRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_lessons}}';
    }
}
