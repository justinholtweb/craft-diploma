<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $courseStatus
 * @property string|null $difficultyLevel
 * @property int|null $estimatedDuration
 * @property int|null $enrollmentLimit
 * @property int $enrollmentCount
 * @property int|null $passingScore
 * @property array|null $metadata
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class CourseRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_courses}}';
    }
}
