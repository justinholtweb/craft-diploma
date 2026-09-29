<?php

namespace justinholtweb\diploma\enums;

/**
 * How a learner may come to be enrolled in a course.
 *
 * - `open` — learners may enrol themselves (the `diploma/progress/enroll` action).
 * - `purchase` — only a Commerce order or a Headcount membership enrols a learner.
 * - `manual` — only an admin enrols learners, from the control panel.
 *
 * Commerce, Headcount and admin enrolment work in every mode; the mode only decides whether
 * self-enrolment is allowed.
 */
enum EnrollmentMode: string
{
    case Open = 'open';
    case Purchase = 'purchase';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open — learners enrol themselves',
            self::Purchase => 'Purchase — through Commerce or a Headcount membership',
            self::Manual => 'Manual — admins enrol learners',
        };
    }
}
