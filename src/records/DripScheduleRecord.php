<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $courseId
 * @property int $lessonId
 * @property int $delayDays
 * @property int $delayHours
 * @property bool $enabled
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class DripScheduleRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_drip_schedules}}';
    }
}
