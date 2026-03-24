<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $courseId
 * @property int $userId
 * @property string $enrollmentStatus
 * @property string $enrolledAt
 * @property string|null $completedAt
 * @property string|null $expiresAt
 * @property string|null $source
 * @property int|null $sourceId
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class EnrollmentRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_enrollments}}';
    }
}
