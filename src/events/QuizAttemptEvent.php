<?php

namespace justinholtweb\diploma\events;

use justinholtweb\diploma\models\QuizAttempt;
use yii\base\Event;

/**
 * Fired after a quiz attempt has been graded.
 *
 * Check `$event->attempt->passed` to react specifically to a pass or fail.
 */
class QuizAttemptEvent extends Event
{
    /**
     * @var QuizAttempt The graded quiz attempt (includes score and pass/fail).
     */
    public QuizAttempt $attempt;
}
