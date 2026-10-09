<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use justinholtweb\diploma\events\EnrollmentEvent;
use justinholtweb\diploma\models\BulkResult;
use justinholtweb\diploma\models\Enrollment;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\EnrollmentRecord;

class Enrollments extends Component
{
    /**
     * @event EnrollmentEvent Fired after a user is enrolled in a course.
     */
    public const EVENT_AFTER_ENROLL = 'afterEnroll';

    /**
     * @event EnrollmentEvent Fired after an enrollment is marked completed
     * (i.e. the user finished the course).
     */
    public const EVENT_AFTER_COMPLETE_COURSE = 'afterCompleteCourse';

    /**
     * @event EnrollmentEvent Fired after an enrollment's access period ran out and
     * `diploma/enrollments/expire` marked it expired.
     */
    public const EVENT_AFTER_EXPIRE = 'afterExpire';

    /**
     * The statuses an imported row may carry.
     */
    public const IMPORT_STATUSES = ['active', 'completed', 'cancelled', 'expired'];

    public function getEnrollment(int $courseId, int $userId): ?Enrollment
    {
        $record = EnrollmentRecord::find()
            ->where(['courseId' => $courseId, 'userId' => $userId])
            ->one();

        if (!$record) {
            return null;
        }

        return $this->recordToModel($record);
    }

    public function getEnrollmentById(int $id): ?Enrollment
    {
        $record = EnrollmentRecord::findOne($id);
        if (!$record) {
            return null;
        }

        return $this->recordToModel($record);
    }

    /**
     * Whether the user holds an active enrolment whose access period (if it has one) has not run
     * out. An overdue enrolment stops counting at once, not when the expire command next runs.
     */
    public function isEnrolled(int $courseId, int $userId): bool
    {
        return EnrollmentRecord::find()
            ->where([
                'courseId' => $courseId,
                'userId' => $userId,
                'enrollmentStatus' => 'active',
            ])
            ->andWhere(['or', ['expiresAt' => null], ['>', 'expiresAt', Db::prepareDateForDb(new \DateTime())]])
            ->exists();
    }

    public function hasCompleted(int $courseId, int $userId): bool
    {
        return EnrollmentRecord::find()
            ->where([
                'courseId' => $courseId,
                'userId' => $userId,
                'enrollmentStatus' => 'completed',
            ])
            ->exists();
    }

    /**
     * Enrols a user, or returns the enrolment they already have.
     *
     * - An **active or completed** enrolment is returned unchanged.
     * - An **expired or cancelled** one is reactivated — for Commerce, Headcount and admins. A
     *   learner cannot reactivate their own lapsed enrolment by self-enrolling; that is exactly
     *   how a membership that expired would be renewed for free. Returns null for `self`.
     * - Otherwise a new enrolment is created, within the course's enrolment limit.
     *
     * The `(courseId, userId)` pair is unique, so a lapsed enrolment has to be reactivated rather
     * than inserted again — inserting failed on the index, which is why re-subscribing through
     * Headcount used to leave the learner locked out.
     *
     * Whether a learner may enrol *themselves* at all is {@see selfEnrollmentProblem()}.
     */
    public function enroll(int $courseId, int $userId, string $source = 'self', ?int $sourceId = null): ?Enrollment
    {
        /** @var EnrollmentRecord|null $existing */
        $existing = EnrollmentRecord::find()
            ->where(['courseId' => $courseId, 'userId' => $userId])
            ->one();

        if ($existing && in_array($existing->enrollmentStatus, ['active', 'completed'], true)) {
            return $this->recordToModel($existing);
        }

        if ($existing) {
            if ($source === 'self') {
                return null;
            }

            $existing->enrollmentStatus = 'active';
            $existing->enrolledAt = Db::prepareDateForDb(new \DateTime());
            $existing->expiresAt = null;
            $existing->source = $source;
            $existing->sourceId = $sourceId;

            if (!$existing->save(false)) {
                return null;
            }

            $enrollment = $this->recordToModel($existing);

            if ($this->hasEventHandlers(self::EVENT_AFTER_ENROLL)) {
                $this->trigger(self::EVENT_AFTER_ENROLL, new EnrollmentEvent([
                    'enrollment' => $enrollment,
                ]));
            }

            return $enrollment;
        }

        // Check enrollment limit
        $course = Plugin::getInstance()->courses->getById($courseId);
        if ($course && $course->enrollmentLimit && $course->enrollmentCount >= $course->enrollmentLimit) {
            return null;
        }

        $record = new EnrollmentRecord();
        $record->courseId = $courseId;
        $record->userId = $userId;
        $record->enrollmentStatus = 'active';
        $record->enrolledAt = Db::prepareDateForDb(new \DateTime());
        $record->source = $source;
        $record->sourceId = $sourceId;
        $record->uid = StringHelper::UUID();

        if (!$record->save()) {
            return null;
        }

        // Increment course enrollment count
        Plugin::getInstance()->courses->incrementEnrollmentCount($courseId);

        $enrollment = $this->recordToModel($record);

        if ($this->hasEventHandlers(self::EVENT_AFTER_ENROLL)) {
            $this->trigger(self::EVENT_AFTER_ENROLL, new EnrollmentEvent([
                'enrollment' => $enrollment,
            ]));
        }

        return $enrollment;
    }

    /**
     * Why a user may not enrol themselves in a course, or null if they may.
     *
     * Self-enrolment is for free, open courses only. It is refused when the course:
     *
     * - does not exist, is disabled, or is not published;
     * - is not in `open` enrolment mode;
     * - is sold — linked to a Commerce product through a `diplomaCourses` field, or mapped to a
     *   Headcount plan — whatever its mode says, because a course somebody forgot to switch to
     *   `purchase` must not become free;
     * - already has a lapsed enrolment for this user (only a purchase, a membership or an admin
     *   reactivates one);
     * - is full.
     */
    public function selfEnrollmentProblem(int $courseId, int $userId): ?string
    {
        $course = \justinholtweb\diploma\elements\Course::find()->id($courseId)->status(null)->one();

        if ($course === null || !$course->enabled || $course->courseStatus !== 'published') {
            return Craft::t('diploma', 'This course is not open for enrollment.');
        }

        if ($course->enrollmentMode !== 'open' || $this->isSold($courseId)) {
            return Craft::t('diploma', 'This course cannot be joined directly.');
        }

        $existing = $this->getEnrollment($courseId, $userId);

        if ($existing && in_array($existing->enrollmentStatus, ['cancelled', 'expired'], true)) {
            return Craft::t('diploma', 'Your enrollment in this course has ended.');
        }

        if (!$existing && $course->enrollmentLimit && $course->enrollmentCount >= $course->enrollmentLimit) {
            return Craft::t('diploma', 'This course is full.');
        }

        return null;
    }

    /**
     * Whether a course is sold: related from a `diplomaCourses` field (what CommerceIntegration
     * enrols from) or mapped to a Headcount plan.
     */
    public function isSold(int $courseId): bool
    {
        $mapping = Plugin::getInstance()->getSettings()->headcountPlanMapping;

        if (in_array($courseId, array_map('intval', $mapping), true)) {
            return true;
        }

        $fieldIds = (new \craft\db\Query())
            ->select('id')
            ->from(\craft\db\Table::FIELDS)
            ->where(['handle' => 'diplomaCourses'])
            ->column();

        return $fieldIds !== [] && (new \craft\db\Query())
            ->from(\craft\db\Table::RELATIONS)
            ->where(['fieldId' => $fieldIds, 'targetId' => $courseId])
            ->exists();
    }

    public function unenroll(int $courseId, int $userId): bool
    {
        /** @var EnrollmentRecord|null $record */
        $record = EnrollmentRecord::find()
            ->where(['courseId' => $courseId, 'userId' => $userId])
            ->one();

        if (!$record) {
            return false;
        }

        $record->enrollmentStatus = 'cancelled';
        $record->save(false);

        Plugin::getInstance()->courses->decrementEnrollmentCount($courseId);

        return true;
    }

    public function markCompleted(int $enrollmentId): bool
    {
        $record = EnrollmentRecord::findOne($enrollmentId);
        if (!$record) {
            return false;
        }

        $alreadyCompleted = $record->enrollmentStatus === 'completed';

        $record->enrollmentStatus = 'completed';
        $record->completedAt = Db::prepareDateForDb(new \DateTime());

        if (!$record->save(false)) {
            return false;
        }

        if (!$alreadyCompleted && $this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_COURSE)) {
            $this->trigger(self::EVENT_AFTER_COMPLETE_COURSE, new EnrollmentEvent([
                'enrollment' => $this->recordToModel($record),
            ]));
        }

        return true;
    }

    public function updateStatus(int $enrollmentId, string $status): bool
    {
        $record = EnrollmentRecord::findOne($enrollmentId);
        if (!$record) {
            return false;
        }

        $record->enrollmentStatus = $status;

        if ($status === 'completed') {
            $record->completedAt = Db::prepareDateForDb(new \DateTime());
        }

        return $record->save(false);
    }

    public function getEnrollmentsByCourse(int $courseId): array
    {
        $records = EnrollmentRecord::find()
            ->where(['courseId' => $courseId])
            ->orderBy(['enrolledAt' => SORT_DESC])
            ->all();

        return array_map([$this, 'recordToModel'], $records);
    }

    public function getEnrollmentsByUser(int $userId): array
    {
        $records = EnrollmentRecord::find()
            ->where(['userId' => $userId])
            ->orderBy(['enrolledAt' => SORT_DESC])
            ->all();

        return array_map([$this, 'recordToModel'], $records);
    }

    /**
     * Enrols a list of learners in one course — what `diploma/enrollments/import` runs over a CSV
     * file when a customer moves from another LMS.
     *
     * Each row is keyed by lower-case column name: `email` (required), `status` (`active` by
     * default, or `completed`, `cancelled`, `expired`), `date` (when they enrolled), `completed`
     * (when they finished) and `expires` (when access ends). Dates are read in the system time zone.
     *
     * Rows are independent: a bad row is reported and the rest carry on. An existing active or
     * completed enrolment is left alone, so the same file can be imported twice; a lapsed one is
     * reactivated. Enrolments go through {@see enroll()} with the source `import`, so the course's
     * enrolment limit and the `afterEnroll` event (and the activity log) apply as usual.
     *
     * @param array<int, array<string, string|null>> $rows
     * @param bool $createUsers Create a pending user for an email nobody has yet. They set a
     *   password through *Forgot password* or an activation email sent from the Users screen.
     */
    public function import(int $courseId, array $rows, bool $dryRun = false, bool $createUsers = false): BulkResult
    {
        $result = new BulkResult(['dryRun' => $dryRun]);
        $line = 1;

        foreach ($rows as $row) {
            $line++;

            try {
                $this->importRow($courseId, $row, $line, $dryRun, $createUsers, $result);
            } catch (\Throwable $e) {
                Craft::error("Diploma import, line $line: " . $e->getMessage(), __METHOD__);
                $result->failures[] = "Line $line: " . $e->getMessage();
            }
        }

        return $result;
    }

    private function importRow(int $courseId, array $row, int $line, bool $dryRun, bool $createUsers, BulkResult $result): void
    {
        $email = trim((string)($row['email'] ?? ''));
        $status = strtolower(trim((string)($row['status'] ?? ''))) ?: 'active';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result->failures[] = "Line $line: “{$email}” is not an email address.";
            return;
        }

        if (!in_array($status, self::IMPORT_STATUSES, true)) {
            $result->failures[] = "Line $line ($email): unknown status “{$status}”.";
            return;
        }

        $dates = [];

        foreach (['date' => 'enrolledAt', 'completed' => 'completedAt', 'expires' => 'expiresAt'] as $column => $attribute) {
            $value = trim((string)($row[$column] ?? ''));

            if ($value === '') {
                continue;
            }

            $date = DateTimeHelper::toDateTime($value);

            if ($date === false) {
                $result->failures[] = "Line $line ($email): “{$value}” is not a date ({$column}).";
                return;
            }

            $dates[$attribute] = $date;
        }

        $user = User::find()->email(Db::escapeParam($email))->status(null)->one();

        if ($user === null && !$createUsers) {
            $result->failures[] = "Line $line: no user has the email $email (use --create-users to create one).";
            return;
        }

        /** @var EnrollmentRecord|null $existing */
        $existing = $user ? EnrollmentRecord::find()->where(['courseId' => $courseId, 'userId' => $user->id])->one() : null;

        if ($existing && in_array($existing->enrollmentStatus, ['active', 'completed'], true)) {
            $result->skipped[] = "$email: already enrolled ({$existing->enrollmentStatus}).";
            return;
        }

        $verb = $existing ? 'reactivated' : 'enrolled';
        $what = "$email: $verb as $status" . ($user === null ? ' (new user)' : '') . '.';

        if ($dryRun) {
            $result->done[] = $what;
            return;
        }

        if ($user === null) {
            $user = new User();
            $user->email = $email;
            $user->username = $email;
            $user->pending = true;

            if (!Craft::$app->getElements()->saveElement($user)) {
                $result->failures[] = "Line $line ($email): the user could not be created — " . implode(' ', $user->getFirstErrors());
                return;
            }
        }

        $enrollment = $this->enroll($courseId, (int)$user->id, 'import');

        if ($enrollment === null) {
            $result->failures[] = "Line $line ($email): could not enrol — the course may be full.";
            return;
        }

        if ($status === 'completed') {
            $this->markCompleted((int)$enrollment->id);
        } elseif ($status !== 'active') {
            $this->updateStatus((int)$enrollment->id, $status);
        }

        if ($dates !== []) {
            $record = EnrollmentRecord::findOne($enrollment->id);

            foreach ($dates as $attribute => $date) {
                if ($attribute === 'completedAt' && $status !== 'completed') {
                    continue;
                }

                $record->$attribute = Db::prepareDateForDb($date);
            }

            $record->save(false);
        }

        $result->done[] = $what;
    }

    /**
     * Marks every active enrolment whose `expiresAt` has passed as expired, firing
     * {@see EVENT_AFTER_EXPIRE} for each. What `diploma/enrollments/expire` runs from cron.
     *
     * Completed enrolments are left alone — finishing a course is a record, not access.
     */
    public function expireDue(?int $courseId = null, bool $dryRun = false): BulkResult
    {
        $result = new BulkResult(['dryRun' => $dryRun]);

        $query = EnrollmentRecord::find()
            ->where(['enrollmentStatus' => 'active'])
            ->andWhere(['not', ['expiresAt' => null]])
            ->andWhere(['<=', 'expiresAt', Db::prepareDateForDb(new \DateTime())])
            ->orderBy(['id' => SORT_ASC]);

        if ($courseId !== null) {
            $query->andWhere(['courseId' => $courseId]);
        }

        foreach ($query->each() as $record) {
            /** @var EnrollmentRecord $record */
            $label = "Enrollment #{$record->id} (user #{$record->userId}, course #{$record->courseId}), expired {$record->expiresAt} UTC.";

            if ($dryRun) {
                $result->done[] = $label;
                continue;
            }

            $record->enrollmentStatus = 'expired';

            if (!$record->save(false)) {
                $result->failures[] = "Enrollment #{$record->id}: could not be saved.";
                continue;
            }

            $result->done[] = $label;

            if ($this->hasEventHandlers(self::EVENT_AFTER_EXPIRE)) {
                $this->trigger(self::EVENT_AFTER_EXPIRE, new EnrollmentEvent([
                    'enrollment' => $this->recordToModel($record),
                ]));
            }
        }

        return $result;
    }

    private function recordToModel(EnrollmentRecord $record): Enrollment
    {
        $model = new Enrollment();
        $model->id = $record->id;
        $model->courseId = $record->courseId;
        $model->userId = $record->userId;
        $model->enrollmentStatus = $record->enrollmentStatus;
        $model->enrolledAt = $record->enrolledAt;
        $model->completedAt = $record->completedAt;
        $model->expiresAt = $record->expiresAt;
        $model->source = $record->source;
        $model->sourceId = $record->sourceId;
        $model->dateCreated = $record->dateCreated;
        $model->dateUpdated = $record->dateUpdated;
        $model->uid = $record->uid;

        return $model;
    }
}
