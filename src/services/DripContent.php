<?php

namespace justinholtweb\diploma\services;

use craft\base\Component;
use craft\helpers\StringHelper;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\enums\ActivityType;
use justinholtweb\diploma\events\LessonUnlockEvent;
use justinholtweb\diploma\models\BulkResult;
use justinholtweb\diploma\models\DripSchedule;
use justinholtweb\diploma\records\ActivityLogRecord;
use justinholtweb\diploma\records\DripScheduleRecord;
use justinholtweb\diploma\records\EnrollmentRecord;

class DripContent extends Component
{
    /**
     * @event LessonUnlockEvent Fired once per learner and lesson when `diploma/drip/unlock` finds a
     * drip-scheduled lesson whose unlock time has been reached. Hook it to tell the learner.
     */
    public const EVENT_AFTER_UNLOCK_LESSON = 'afterUnlockLesson';

    /**
     * Finds drip-scheduled lessons (a lesson's *Drip Days*) that have unlocked for active learners
     * within the last `$lookbackDays`, records each in the activity log and fires
     * {@see EVENT_AFTER_UNLOCK_LESSON} — what `diploma/drip/unlock` runs from cron.
     *
     * Access itself never waited for this: `ProgressTracker::isLessonUnlocked()` compares dates
     * on every request. This pass exists so something can *happen* when a lesson opens (an
     * email, a notification). The activity-log row is the ledger that makes it fire once per
     * enrolment — a learner who re-enrols starts their drip over and is told again. Unlocks older
     * than the lookback are ignored, so the first run doesn't announce months of old lessons.
     */
    public function unlockDue(?int $courseId = null, int $lookbackDays = 7, bool $dryRun = false, ?\DateTime $now = null): BulkResult
    {
        $result = new BulkResult(['dryRun' => $dryRun]);
        $utc = new \DateTimeZone('UTC');
        $now = $now ? (clone $now)->setTimezone($utc) : new \DateTime('now', $utc);
        $since = (clone $now)->modify('-' . max(0, $lookbackDays) . ' days');

        $lessonQuery = Lesson::find();

        if ($courseId !== null) {
            $lessonQuery->courseId($courseId);
        }

        /** @var array<int, Lesson[]> $byCourse */
        $byCourse = [];

        foreach ($lessonQuery->all() as $lesson) {
            if ($lesson->dripDays > 0 && $lesson->courseId) {
                $byCourse[$lesson->courseId][] = $lesson;
            }
        }

        if ($byCourse === []) {
            return $result;
        }

        $enrollments = EnrollmentRecord::find()
            ->where(['courseId' => array_keys($byCourse), 'enrollmentStatus' => 'active'])
            ->andWhere(['or', ['expiresAt' => null], ['>', 'expiresAt', $now->format('Y-m-d H:i:s')]])
            ->orderBy(['id' => SORT_ASC]);

        foreach ($enrollments->each() as $enrollment) {
            /** @var EnrollmentRecord $enrollment */
            if (!$enrollment->enrolledAt) {
                continue;
            }

            $enrolledAt = new \DateTime($enrollment->enrolledAt, $utc);

            foreach ($byCourse[$enrollment->courseId] as $lesson) {
                $unlockedAt = (clone $enrolledAt)->modify("+{$lesson->dripDays} days");

                if ($unlockedAt > $now || $unlockedAt < $since) {
                    continue;
                }

                $label = "Enrollment #{$enrollment->id}: “{$lesson->title}” (unlocked {$unlockedAt->format('Y-m-d H:i')} UTC)";

                $announced = ActivityLogRecord::find()
                    ->where([
                        'eventType' => ActivityType::LessonUnlocked->value,
                        'enrollmentId' => $enrollment->id,
                        'lessonId' => $lesson->id,
                    ])
                    ->andWhere(['>=', 'dateCreated', $enrollment->enrolledAt])
                    ->exists();

                if ($announced) {
                    $result->skipped[] = "$label: already announced.";
                    continue;
                }

                if ($dryRun) {
                    $result->done[] = $label;
                    continue;
                }

                $event = new LessonUnlockEvent([
                    'enrollmentId' => (int)$enrollment->id,
                    'userId' => (int)$enrollment->userId,
                    'courseId' => (int)$enrollment->courseId,
                    'lessonId' => (int)$lesson->id,
                    'unlockedAt' => $unlockedAt,
                ]);

                // The ledger row is written first: a handler that throws must not make the next
                // run announce the lesson again.
                if (\justinholtweb\diploma\Plugin::getInstance()->activityLog->handleUnlockLesson($event) === null) {
                    $result->failures[] = "$label: could not be recorded.";
                    continue;
                }

                try {
                    if ($this->hasEventHandlers(self::EVENT_AFTER_UNLOCK_LESSON)) {
                        $this->trigger(self::EVENT_AFTER_UNLOCK_LESSON, $event);
                    }
                } catch (\Throwable $e) {
                    \Craft::error("Diploma drip unlock handler failed for $label: " . $e->getMessage(), __METHOD__);
                    $result->failures[] = "$label: recorded, but a handler failed — " . $e->getMessage();
                    continue;
                }

                $result->done[] = $label;
            }
        }

        return $result;
    }

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
            /** @var DripScheduleRecord|null $record */
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
