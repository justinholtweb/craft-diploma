<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use justinholtweb\diploma\models\Enrollment;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\EnrollmentRecord;

class Enrollments extends Component
{
    public function getEnrollment(int $courseId, int $userId): ?Enrollment
    {
        $record = EnrollmentRecord::find()
            ->where(['courseId' => $courseId, 'userId' => $userId])
            ->one();

        if (!$record) {
            return null;
        }

        return $this->recordToModel($record);
    }

    public function getEnrollmentById(int $id): ?Enrollment
    {
        $record = EnrollmentRecord::findOne($id);
        if (!$record) {
            return null;
        }

        return $this->recordToModel($record);
    }

    public function isEnrolled(int $courseId, int $userId): bool
    {
        return EnrollmentRecord::find()
            ->where([
                'courseId' => $courseId,
                'userId' => $userId,
                'enrollmentStatus' => 'active',
            ])
            ->exists();
    }

    public function hasCompleted(int $courseId, int $userId): bool
    {
        return EnrollmentRecord::find()
            ->where([
                'courseId' => $courseId,
                'userId' => $userId,
                'enrollmentStatus' => 'completed',
            ])
            ->exists();
    }

    public function enroll(int $courseId, int $userId, string $source = 'self', ?int $sourceId = null): ?Enrollment
    {
        // Check for existing enrollment
        $existing = $this->getEnrollment($courseId, $userId);
        if ($existing && $existing->enrollmentStatus === 'active') {
            return $existing;
        }

        // Check enrollment limit
        $course = Plugin::getInstance()->courses->getById($courseId);
        if ($course && $course->enrollmentLimit && $course->enrollmentCount >= $course->enrollmentLimit) {
            return null;
        }

        $record = new EnrollmentRecord();
        $record->courseId = $courseId;
        $record->userId = $userId;
        $record->enrollmentStatus = 'active';
        $record->enrolledAt = Db::prepareDateForDb(new \DateTime());
        $record->source = $source;
        $record->sourceId = $sourceId;
        $record->uid = StringHelper::UUID();

        if (!$record->save()) {
            return null;
        }

        // Increment course enrollment count
        Plugin::getInstance()->courses->incrementEnrollmentCount($courseId);

        return $this->recordToModel($record);
    }

    public function unenroll(int $courseId, int $userId): bool
    {
        $record = EnrollmentRecord::find()
            ->where(['courseId' => $courseId, 'userId' => $userId])
            ->one();

        if (!$record) {
            return false;
        }

        $record->enrollmentStatus = 'cancelled';
        $record->save(false);

        Plugin::getInstance()->courses->decrementEnrollmentCount($courseId);

        return true;
    }

    public function markCompleted(int $enrollmentId): bool
    {
        $record = EnrollmentRecord::findOne($enrollmentId);
        if (!$record) {
            return false;
        }

        $record->enrollmentStatus = 'completed';
        $record->completedAt = Db::prepareDateForDb(new \DateTime());

        return $record->save(false);
    }

    public function updateStatus(int $enrollmentId, string $status): bool
    {
        $record = EnrollmentRecord::findOne($enrollmentId);
        if (!$record) {
            return false;
        }

        $record->enrollmentStatus = $status;

        if ($status === 'completed') {
            $record->completedAt = Db::prepareDateForDb(new \DateTime());
        }

        return $record->save(false);
    }

    public function getEnrollmentsByCourse(int $courseId): array
    {
        $records = EnrollmentRecord::find()
            ->where(['courseId' => $courseId])
            ->orderBy(['enrolledAt' => SORT_DESC])
            ->all();

        return array_map([$this, 'recordToModel'], $records);
    }

    public function getEnrollmentsByUser(int $userId): array
    {
        $records = EnrollmentRecord::find()
            ->where(['userId' => $userId])
            ->orderBy(['enrolledAt' => SORT_DESC])
            ->all();

        return array_map([$this, 'recordToModel'], $records);
    }

    private function recordToModel(EnrollmentRecord $record): Enrollment
    {
        $model = new Enrollment();
        $model->id = $record->id;
        $model->courseId = $record->courseId;
        $model->userId = $record->userId;
        $model->enrollmentStatus = $record->enrollmentStatus;
        $model->enrolledAt = $record->enrolledAt;
        $model->completedAt = $record->completedAt;
        $model->expiresAt = $record->expiresAt;
        $model->source = $record->source;
        $model->sourceId = $record->sourceId;
        $model->dateCreated = $record->dateCreated;
        $model->dateUpdated = $record->dateUpdated;
        $model->uid = $record->uid;

        return $model;
    }
}
