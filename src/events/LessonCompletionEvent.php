<?php

namespace justinholtweb\diploma\events;

use yii\base\Event;

/**
 * Fired when a lesson is completed within an enrollment.
 */
class LessonCompletionEvent extends Event
{
    /**
     * @var int The enrollment the lesson was completed under.
     */
    public int $enrollmentId;

    /**
     * @var int The completed lesson's ID.
     */
    public int $lessonId;

    /**
     * @var int Seconds spent on the lesson for this completion.
     */
    public int $timeSpent = 0;
}
