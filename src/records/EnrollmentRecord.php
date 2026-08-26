<?php

namespace justinholtweb\diploma\records;

use Craft;
use craft\db\ActiveRecord;
use craft\elements\User;
use justinholtweb\diploma\elements\Course;

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

    /**
     * The enrolled user, so screens can show a name instead of a bare id.
     * Null when the user has since been deleted; templates fall back to the id.
     */
    public function getEnrolledUser(): ?User
    {
        return $this->userId ? Craft::$app->getUsers()->getUserById($this->userId) : null;
    }

    /**
     * The course this enrolment is against, or null if it has been deleted.
     */
    public function getEnrolledCourse(): ?Course
    {
        return $this->courseId ? Course::find()->id($this->courseId)->status(null)->one() : null;
    }
}
