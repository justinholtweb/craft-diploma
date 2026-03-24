<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use justinholtweb\diploma\models\DripSchedule;
use justinholtweb\diploma\records\DripScheduleRecord;

class DripContent extends Component
{
    public function getSchedulesByCourse(int $courseId): array
    {
        $records = DripScheduleRecord::find()
            ->where(['courseId' => $courseId])
            ->orderBy(['delayDays' => SORT_ASC, 'delayHours' => SORT_ASC])
            ->all();

        return array_map([$this, 'recordToModel'], $records);
    }

    public function getScheduleForLesson(int $courseId, int $lessonId): ?DripSchedule
    {
        $record = DripScheduleRecord::find()
            ->where(['courseId' => $courseId, 'lessonId' => $lessonId])
            ->one();

        if (!$record) {
            return null;
        }

        return $this->recordToModel($record);
    }

    public function saveSchedule(DripSchedule $schedule): bool
    {
        if ($schedule->id) {
            $record = DripScheduleRecord::findOne($schedule->id);
            if (!$record) {
                return false;
            }
        } else {
            // Check for existing schedule for this lesson
            $record = DripScheduleRecord::find()
                ->where(['courseId' => $schedule->courseId, 'lessonId' => $schedule->lessonId])
                ->one();

            if (!$record) {
                $record = new DripScheduleRecord();
                $record->uid = StringHelper::UUID();
            }
        }

        $record->courseId = $schedule->courseId;
        $record->lessonId = $schedule->lessonId;
        $record->delayDays = $schedule->delayDays;
        $record->delayHours = $schedule->delayHours;
        $record->enabled = $schedule->enabled;

        if (!$record->save()) {
            return false;
        }

        $schedule->id = $record->id;

        return true;
    }

    public function deleteSchedule(int $id): bool
    {
        $record = DripScheduleRecord::findOne($id);
        if (!$record) {
            return false;
        }

        return (bool)$record->delete();
    }

    public function isLessonAvailable(int $courseId, int $lessonId, \DateTime $enrolledAt): bool
    {
        $schedule = $this->getScheduleForLesson($courseId, $lessonId);

        if (!$schedule || !$schedule->enabled) {
            return true;
        }

        $unlockDate = (clone $enrolledAt)
            ->modify("+{$schedule->delayDays} days")
            ->modify("+{$schedule->delayHours} hours");

        return new \DateTime() >= $unlockDate;
    }

    private function recordToModel(DripScheduleRecord $record): DripSchedule
    {
        $model = new DripSchedule();
        $model->id = $record->id;
        $model->courseId = $record->courseId;
        $model->lessonId = $record->lessonId;
        $model->delayDays = $record->delayDays;
        $model->delayHours = $record->delayHours;
        $model->enabled = (bool)$record->enabled;
        $model->dateCreated = $record->dateCreated;
        $model->dateUpdated = $record->dateUpdated;
        $model->uid = $record->uid;

        return $model;
    }
}
