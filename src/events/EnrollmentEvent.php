<?php

namespace justinholtweb\diploma\events;

use justinholtweb\diploma\models\Enrollment;
use yii\base\Event;

/**
 * Fired when a user is enrolled in a course or an enrollment is completed.
 */
class EnrollmentEvent extends Event
{
    /**
     * @var Enrollment The enrollment involved in the event.
     */
    public Enrollment $enrollment;
}
