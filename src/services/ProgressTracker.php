<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\events\LessonCompletionEvent;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\EnrollmentRecord;
use justinholtweb\diploma\records\ProgressRecord;

class ProgressTracker extends Component
{
    /**
     * @event LessonCompletionEvent Fired when a lesson is first completed
     * within an enrollment.
     */
    public const EVENT_AFTER_COMPLETE_LESSON = 'afterCompleteLesson';

    public function completeLesson(int $enrollmentId, int $lessonId, int $timeSpent = 0): bool
    {
        // Check for existing progress
        $existing = ProgressRecord::find()
            ->where(['enrollmentId' => $enrollmentId, 'lessonId' => $lessonId])
            ->one();

        if ($existing) {
            $justCompleted = false;
            if (!$existing->completedAt) {
                $existing->completedAt = Db::prepareDateForDb(new \DateTime());
                $justCompleted = true;
            }
            $existing->timeSpent = $existing->timeSpent + $timeSpent;

            if (!$existing->save(false)) {
                return false;
            }

            if ($justCompleted) {
                $this->onLessonCompleted($enrollmentId, $lessonId, $timeSpent);
            }

            return true;
        }

        $record = new ProgressRecord();
        $record->enrollmentId = $enrollmentId;
        $record->lessonId = $lessonId;
        $record->completedAt = Db::prepareDateForDb(new \DateTime());
        $record->timeSpent = $timeSpent;
        $record->uid = StringHelper::UUID();

        if (!$record->save()) {
            return false;
        }

        $this->onLessonCompleted($enrollmentId, $lessonId, $timeSpent);

        return true;
    }

    /**
     * Fires the lesson-completion event and checks whether the course is
     * now finished.
     */
    private function onLessonCompleted(int $enrollmentId, int $lessonId, int $timeSpent): void
    {
        if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_LESSON)) {
            $this->trigger(self::EVENT_AFTER_COMPLETE_LESSON, new LessonCompletionEvent([
                'enrollmentId' => $enrollmentId,
                'lessonId' => $lessonId,
                'timeSpent' => $timeSpent,
            ]));
        }

        // Check if course is now complete
        $this->checkCourseCompletion($enrollmentId);
    }

    public function getCourseProgress(int $courseId, int $userId): object
    {
        $enrollment = Plugin::getInstance()->enrollments->getEnrollment($courseId, $userId);

        $result = (object)[
            'percentage' => 0,
            'completedLessons' => 0,
            'totalLessons' => 0,
            'totalTimeSpent' => 0,
        ];

        if (!$enrollment) {
            return $result;
        }

        $totalLessons = Lesson::find()->courseId($courseId)->count();
        $result->totalLessons = (int)$totalLessons;

        if ($totalLessons === 0) {
            return $result;
        }

        $completedLessons = (int)ProgressRecord::find()
            ->where([
                'enrollmentId' => $enrollment->id,
            ])
            ->andWhere(['not', ['completedAt' => null]])
            ->count();

        $totalTimeSpent = (int)ProgressRecord::find()
            ->where(['enrollmentId' => $enrollment->id])
            ->sum('timeSpent');

        $result->completedLessons = $completedLessons;
        $result->percentage = round(($completedLessons / $result->totalLessons) * 100, 1);
        $result->totalTimeSpent = $totalTimeSpent ?: 0;

        return $result;
    }

    public function isLessonUnlocked(Lesson $lesson, int $userId): bool
    {
        // Free lessons are always unlocked
        if ($lesson->isFree) {
            return true;
        }

        // Must be enrolled
        if (!Plugin::getInstance()->enrollments->isEnrolled($lesson->courseId, $userId)) {
            return false;
        }

        $enrollment = Plugin::getInstance()->enrollments->getEnrollment($lesson->courseId, $userId);
        if (!$enrollment) {
            return false;
        }

        // Check drip schedule (Pro)
        if ($lesson->dripDays && Plugin::getInstance()->is(Plugin::EDITION_PRO)) {
            $enrolledAt = new \DateTime($enrollment->enrolledAt);
            $unlockDate = (clone $enrolledAt)->modify("+{$lesson->dripDays} days");
            if (new \DateTime() < $unlockDate) {
                return false;
            }
        }

        // Check prerequisite
        if ($lesson->prerequisiteLessonId) {
            $prereqCompleted = ProgressRecord::find()
                ->where([
                    'enrollmentId' => $enrollment->id,
                    'lessonId' => $lesson->prerequisiteLessonId,
                ])
                ->andWhere(['not', ['completedAt' => null]])
                ->exists();

            if (!$prereqCompleted) {
                return false;
            }
        }

        return true;
    }

    private function checkCourseCompletion(int $enrollmentId): void
    {
        $enrollment = EnrollmentRecord::findOne($enrollmentId);
        if (!$enrollment || $enrollment->enrollmentStatus !== 'active') {
            return;
        }

        $totalLessons = (int)Lesson::find()->courseId($enrollment->courseId)->count();
        if ($totalLessons === 0) {
            return;
        }

        $completedLessons = (int)ProgressRecord::find()
            ->where(['enrollmentId' => $enrollmentId])
            ->andWhere(['not', ['completedAt' => null]])
            ->count();

        if ($completedLessons >= $totalLessons) {
            // Check if quiz passing is required
            $settings = Plugin::getInstance()->getSettings();
            if ($settings->requirePassingQuiz) {
                // Check if course has a quiz and if the student passed
                $quiz = \justinholtweb\diploma\elements\Quiz::find()
                    ->courseId($enrollment->courseId)
                    ->one();

                if ($quiz) {
                    $hasPassed = \justinholtweb\diploma\records\QuizAttemptRecord::find()
                        ->where([
                            'quizId' => $quiz->id,
                            'enrollmentId' => $enrollmentId,
                            'passed' => true,
                        ])
                        ->exists();

                    if (!$hasPassed) {
                        return;
                    }
                }
            }

            Plugin::getInstance()->enrollments->markCompleted($enrollmentId);

            // Auto-issue certificate
            if ($settings->enableCertificates && $settings->autoIssueCertificate) {
                Plugin::getInstance()->certificates->issue(
                    $enrollmentId,
                    $enrollment->userId,
                    $enrollment->courseId
                );
            }
        }
    }
}
