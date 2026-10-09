<?php

namespace justinholtweb\diploma\events;

use yii\base\Event;

/**
 * Fired when a drip-scheduled lesson's unlock time has been reached for a learner.
 *
 * The drip date is the only thing that changed: a lesson that also has a prerequisite stays locked
 * until that is completed, so check `ProgressTracker::isLessonUnlocked()` before promising access.
 */
class LessonUnlockEvent extends Event
{
    /**
     * @var int The enrollment the lesson unlocked under.
     */
    public int $enrollmentId;

    /**
     * @var int The learner.
     */
    public int $userId;

    /**
     * @var int The course.
     */
    public int $courseId;

    /**
     * @var int The lesson that unlocked.
     */
    public int $lessonId;

    /**
     * @var \DateTime When it unlocked (enrolment date + the lesson's drip days).
     */
    public \DateTime $unlockedAt;
}
