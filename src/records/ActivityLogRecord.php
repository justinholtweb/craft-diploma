<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $userId
 * @property int|null $actorId
 * @property string $eventType
 * @property int|null $courseId
 * @property int|null $lessonId
 * @property int|null $quizId
 * @property int|null $enrollmentId
 * @property float|null $score
 * @property bool|null $passed
 * @property string|null $message
 * @property string|null $data
 * @property string|null $ipAddress
 * @property string $dateCreated
 * @property string $uid
 */
class ActivityLogRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_activity_log}}';
    }
}
