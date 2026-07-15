<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\elements\Quiz;
use justinholtweb\diploma\enums\ActivityType;
use justinholtweb\diploma\events\CertificateEvent;
use justinholtweb\diploma\events\EnrollmentEvent;
use justinholtweb\diploma\events\LessonCompletionEvent;
use justinholtweb\diploma\events\QuizAttemptEvent;
use justinholtweb\diploma\models\ActivityLog as ActivityLogModel;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\ActivityLogRecord;
use justinholtweb\diploma\records\EnrollmentRecord;

/**
 * Append-only audit log of learner activity (enrollments, lesson completions,
 * quiz attempts, course completions and certificate issuance).
 *
 * Rows are never updated or deleted by the plugin; each records who did what,
 * when, and from which IP, plus a denormalised snapshot so the entry stays
 * meaningful even if the related course/user is later removed.
 */
class ActivityLog extends Component
{
    // Event handlers — wired up in Plugin::registerActivityLogging()
    // -------------------------------------------------------------------------

    public function handleEnroll(EnrollmentEvent $event): void
    {
        $enrollment = $event->enrollment;
        $courseTitle = $this->courseTitle($enrollment->courseId);

        $this->log(ActivityType::Enrolled->value, [
            'userId' => $enrollment->userId,
            'courseId' => $enrollment->courseId,
            'enrollmentId' => $enrollment->id,
            'message' => Craft::t('diploma', 'Enrolled in “{course}”', ['course' => $courseTitle]),
            'data' => [
                'courseTitle' => $courseTitle,
                'userName' => $this->userName($enrollment->userId),
                'source' => $enrollment->source,
            ],
        ]);
    }

    public function handleCompleteCourse(EnrollmentEvent $event): void
    {
        $enrollment = $event->enrollment;
        $courseTitle = $this->courseTitle($enrollment->courseId);

        $this->log(ActivityType::CourseCompleted->value, [
            'userId' => $enrollment->userId,
            'courseId' => $enrollment->courseId,
            'enrollmentId' => $enrollment->id,
            'message' => Craft::t('diploma', 'Completed “{course}”', ['course' => $courseTitle]),
            'data' => [
                'courseTitle' => $courseTitle,
                'userName' => $this->userName($enrollment->userId),
            ],
        ]);
    }

    public function handleCompleteLesson(LessonCompletionEvent $event): void
    {
        $enrollment = EnrollmentRecord::findOne($event->enrollmentId);
        $lesson = Lesson::find()->id($event->lessonId)->status(null)->one();
        $lessonTitle = $lesson?->title ?? (string)$event->lessonId;

        $this->log(ActivityType::LessonCompleted->value, [
            'userId' => $enrollment?->userId,
            'courseId' => $enrollment?->courseId,
            'lessonId' => $event->lessonId,
            'enrollmentId' => $event->enrollmentId,
            'message' => Craft::t('diploma', 'Completed lesson “{lesson}”', ['lesson' => $lessonTitle]),
            'data' => [
                'lessonTitle' => $lessonTitle,
                'timeSpent' => $event->timeSpent,
            ],
        ]);
    }

    public function handleGradeAttempt(QuizAttemptEvent $event): void
    {
        $attempt = $event->attempt;
        $quiz = $attempt->quizId ? Quiz::find()->id($attempt->quizId)->status(null)->one() : null;
        $quizTitle = $quiz?->title ?? Craft::t('diploma', 'Quiz');
        $type = $attempt->passed ? ActivityType::QuizPassed : ActivityType::QuizFailed;

        $enrollment = $attempt->enrollmentId ? EnrollmentRecord::findOne($attempt->enrollmentId) : null;

        $this->log($type->value, [
            'userId' => $attempt->userId,
            'courseId' => $enrollment?->courseId,
            'quizId' => $attempt->quizId,
            'enrollmentId' => $attempt->enrollmentId,
            'score' => $attempt->score,
            'passed' => (bool)$attempt->passed,
            'message' => $attempt->passed
                ? Craft::t('diploma', 'Passed “{quiz}” ({score}%)', ['quiz' => $quizTitle, 'score' => $attempt->score])
                : Craft::t('diploma', 'Did not pass “{quiz}” ({score}%)', ['quiz' => $quizTitle, 'score' => $attempt->score]),
            'data' => [
                'quizTitle' => $quizTitle,
                'attemptId' => $attempt->id,
                'pointsEarned' => $attempt->pointsEarned,
                'pointsPossible' => $attempt->pointsPossible,
            ],
        ]);
    }

    public function handleIssueCertificate(CertificateEvent $event): void
    {
        $certificate = $event->certificate;
        $courseTitle = $this->courseTitle($certificate->courseId);

        $this->log(ActivityType::CertificateIssued->value, [
            'userId' => $certificate->userId,
            'courseId' => $certificate->courseId,
            'enrollmentId' => $certificate->enrollmentId,
            'message' => Craft::t('diploma', 'Certificate issued for “{course}”', ['course' => $courseTitle]),
            'data' => [
                'courseTitle' => $courseTitle,
                'verificationCode' => $certificate->verificationCode,
            ],
        ]);
    }

    // Writing
    // -------------------------------------------------------------------------

    /**
     * Writes a single activity-log row. Failures are logged but never thrown —
     * audit logging must not break the flow it is observing.
     *
     * @param string $eventType One of the ActivityType values.
     * @param array $config userId, actorId, courseId, lessonId, quizId,
     *   enrollmentId, score, passed, message, data, ipAddress
     */
    public function log(string $eventType, array $config = []): ?ActivityLogModel
    {
        $record = new ActivityLogRecord();
        $record->eventType = $eventType;
        $record->userId = $config['userId'] ?? null;
        $record->actorId = $config['actorId'] ?? $this->currentActorId();
        $record->courseId = $config['courseId'] ?? null;
        $record->lessonId = $config['lessonId'] ?? null;
        $record->quizId = $config['quizId'] ?? null;
        $record->enrollmentId = $config['enrollmentId'] ?? null;
        $record->score = $config['score'] ?? null;
        $record->passed = $config['passed'] ?? null;
        $record->message = $config['message'] ?? null;
        $record->data = isset($config['data']) ? json_encode($config['data']) : null;
        $record->ipAddress = $config['ipAddress'] ?? $this->currentIp();
        $record->uid = StringHelper::UUID();

        if (!$record->save()) {
            Craft::warning(
                'Could not write Diploma activity log entry: ' . implode(', ', $record->getErrorSummary(true)),
                __METHOD__
            );
            return null;
        }

        return $this->recordToModel($record);
    }

    // Reading
    // -------------------------------------------------------------------------

    /**
     * Returns activity-log entries, most recent first.
     *
     * @param array $criteria Supported keys: userId, actorId, courseId,
     *   enrollmentId, eventType (string or array), limit, offset.
     * @return ActivityLogModel[]
     */
    public function getActivity(array $criteria = []): array
    {
        $records = $this->buildQuery($criteria)
            ->limit($criteria['limit'] ?? 100)
            ->offset($criteria['offset'] ?? 0)
            ->all();

        return array_map([$this, 'recordToModel'], $records);
    }

    /**
     * Returns the total number of entries matching the given criteria
     * (ignoring limit/offset).
     */
    public function getActivityCount(array $criteria = []): int
    {
        return (int)$this->buildQuery($criteria)->count();
    }

    /**
     * @return ActivityLogModel[]
     */
    public function getActivityForUser(int $userId, int $limit = 100): array
    {
        return $this->getActivity(['userId' => $userId, 'limit' => $limit]);
    }

    /**
     * @return ActivityLogModel[]
     */
    public function getActivityForCourse(int $courseId, int $limit = 100): array
    {
        return $this->getActivity(['courseId' => $courseId, 'limit' => $limit]);
    }

    /**
     * @return ActivityLogModel[]
     */
    public function getActivityForEnrollment(int $enrollmentId, int $limit = 100): array
    {
        return $this->getActivity(['enrollmentId' => $enrollmentId, 'limit' => $limit]);
    }

    private function buildQuery(array $criteria): \yii\db\ActiveQuery
    {
        $query = ActivityLogRecord::find()->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC]);

        foreach (['userId', 'actorId', 'courseId', 'enrollmentId', 'eventType'] as $key) {
            if (isset($criteria[$key]) && $criteria[$key] !== '') {
                $query->andWhere([$key => $criteria[$key]]);
            }
        }

        return $query;
    }

    // Internals
    // -------------------------------------------------------------------------

    private function currentActorId(): ?int
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return null;
        }

        return Craft::$app->getUser()->getId();
    }

    private function currentIp(): ?string
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return null;
        }

        return $request->getUserIP();
    }

    private function courseTitle(?int $courseId): string
    {
        if (!$courseId) {
            return '';
        }

        $course = Plugin::getInstance()->courses->getById($courseId);
        return $course?->title ?? (string)$courseId;
    }

    private function userName(?int $userId): string
    {
        if (!$userId) {
            return '';
        }

        $user = Craft::$app->getUsers()->getUserById($userId);
        return $user?->friendlyName ?? $user?->username ?? (string)$userId;
    }

    private function recordToModel(ActivityLogRecord $record): ActivityLogModel
    {
        $model = new ActivityLogModel();
        $model->id = $record->id;
        $model->userId = $record->userId;
        $model->actorId = $record->actorId;
        $model->eventType = $record->eventType;
        $model->courseId = $record->courseId;
        $model->lessonId = $record->lessonId;
        $model->quizId = $record->quizId;
        $model->enrollmentId = $record->enrollmentId;
        $model->score = $record->score !== null ? (float)$record->score : null;
        $model->passed = $record->passed !== null ? (bool)$record->passed : null;
        $model->message = $record->message;
        $model->data = $record->data ? json_decode($record->data, true) : null;
        $model->ipAddress = $record->ipAddress;
        $model->dateCreated = $record->dateCreated;
        $model->uid = $record->uid;

        return $model;
    }
}
