<?php

namespace justinholtweb\diploma\variables;

use craft\elements\User;
use justinholtweb\diploma\elements\Course;
use justinholtweb\diploma\elements\db\CourseQuery;
use justinholtweb\diploma\elements\db\LessonQuery;
use justinholtweb\diploma\elements\db\QuizQuery;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\elements\Quiz;
use justinholtweb\diploma\Plugin;

class DiplomaVariable
{
    public function courses(): CourseQuery
    {
        return Course::find();
    }

    public function lessons(): LessonQuery
    {
        return Lesson::find();
    }

    public function quizzes(): QuizQuery
    {
        return Quiz::find();
    }

    public function getEnrollment(Course $course, ?User $user = null): ?object
    {
        if (!$user) {
            return null;
        }

        return Plugin::getInstance()->enrollments->getEnrollment($course->id, $user->id);
    }

    public function getCourseProgress(Course $course, ?User $user = null): ?object
    {
        if (!$user) {
            return null;
        }

        return Plugin::getInstance()->progressTracker->getCourseProgress($course->id, $user->id);
    }

    public function getQuizAttempts(Quiz $quiz, ?User $user = null): array
    {
        if (!$user) {
            return [];
        }

        return Plugin::getInstance()->quizGrader->getAttempts($quiz->id, $user->id);
    }

    public function getBestScore(Quiz $quiz, ?User $user = null): ?float
    {
        if (!$user) {
            return null;
        }

        return Plugin::getInstance()->quizGrader->getBestScore($quiz->id, $user->id);
    }

    public function getCertificate(Course $course, ?User $user = null): ?object
    {
        if (!$user) {
            return null;
        }

        return Plugin::getInstance()->certificates->getCertificate($course->id, $user->id);
    }

    public function isEnrolled(Course $course, ?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return Plugin::getInstance()->enrollments->isEnrolled($course->id, $user->id);
    }

    public function isLessonUnlocked(Lesson $lesson, ?User $user = null): bool
    {
        if (!$user) {
            return $lesson->isFree;
        }

        return Plugin::getInstance()->progressTracker->isLessonUnlocked($lesson, $user->id);
    }

    public function hasCompleted(Course $course, ?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return Plugin::getInstance()->enrollments->hasCompleted($course->id, $user->id);
    }
}
