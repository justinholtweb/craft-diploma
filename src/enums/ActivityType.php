<?php

namespace justinholtweb\diploma\enums;

/**
 * Types of activity recorded in the Diploma audit log.
 */
enum ActivityType: string
{
    case Enrolled = 'enrolled';
    case Unenrolled = 'unenrolled';
    case LessonCompleted = 'lessonCompleted';
    case QuizGraded = 'quizGraded';
    case QuizPassed = 'quizPassed';
    case QuizFailed = 'quizFailed';
    case CourseCompleted = 'courseCompleted';
    case CertificateIssued = 'certificateIssued';

    public function label(): string
    {
        return match ($this) {
            self::Enrolled => 'Enrolled',
            self::Unenrolled => 'Unenrolled',
            self::LessonCompleted => 'Lesson completed',
            self::QuizGraded => 'Quiz graded',
            self::QuizPassed => 'Quiz passed',
            self::QuizFailed => 'Quiz failed',
            self::CourseCompleted => 'Course completed',
            self::CertificateIssued => 'Certificate issued',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Enrolled => 'blue',
            self::Unenrolled => 'gray',
            self::LessonCompleted => 'teal',
            self::QuizGraded => 'purple',
            self::QuizPassed => 'green',
            self::QuizFailed => 'red',
            self::CourseCompleted => 'green',
            self::CertificateIssued => 'yellow',
        };
    }
}
