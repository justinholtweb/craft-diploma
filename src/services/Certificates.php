<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use justinholtweb\diploma\events\CertificateEvent;
use justinholtweb\diploma\models\BulkResult;
use justinholtweb\diploma\models\Certificate;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\CertificateRecord;
use justinholtweb\diploma\records\EnrollmentRecord;

class Certificates extends Component
{
    /**
     * @event CertificateEvent Fired after a certificate is issued.
     */
    public const EVENT_AFTER_ISSUE_CERTIFICATE = 'afterIssueCertificate';

    /**
     * @event CertificateEvent Fired after a certificate's snapshot is rebuilt by
     * `diploma/certificates/reissue`.
     */
    public const EVENT_AFTER_REISSUE_CERTIFICATE = 'afterReissueCertificate';

    public function issue(int $enrollmentId, int $userId, int $courseId): ?Certificate
    {
        // Check for existing certificate
        $existing = CertificateRecord::find()
            ->where(['enrollmentId' => $enrollmentId])
            ->one();

        if ($existing) {
            return $this->recordToModel($existing);
        }

        $course = Plugin::getInstance()->courses->getById($courseId);
        $user = Craft::$app->getUsers()->getUserById($userId);

        $record = new CertificateRecord();
        $record->enrollmentId = $enrollmentId;
        $record->userId = $userId;
        $record->courseId = $courseId;
        $record->verificationCode = $this->generateVerificationCode();
        $record->issuedAt = Db::prepareDateForDb(new \DateTime());
        $record->uid = StringHelper::UUID();

        // Snapshot metadata
        $record->metadata = json_encode($this->snapshot($course, $user, date('F j, Y')));

        if (!$record->save()) {
            return null;
        }

        $certificate = $this->recordToModel($record);

        if ($this->hasEventHandlers(self::EVENT_AFTER_ISSUE_CERTIFICATE)) {
            $this->trigger(self::EVENT_AFTER_ISSUE_CERTIFICATE, new CertificateEvent([
                'certificate' => $certificate,
            ]));
        }

        return $certificate;
    }

    public function getCertificate(int $courseId, int $userId): ?Certificate
    {
        $record = CertificateRecord::find()
            ->where(['courseId' => $courseId, 'userId' => $userId])
            ->one();

        if (!$record) {
            return null;
        }

        return $this->recordToModel($record);
    }

    public function getCertificateByCode(string $code): ?Certificate
    {
        $record = CertificateRecord::find()
            ->where(['verificationCode' => $code])
            ->one();

        if (!$record) {
            return null;
        }

        return $this->recordToModel($record);
    }

    public function getAllCertificates(): array
    {
        $records = CertificateRecord::find()
            ->orderBy(['issuedAt' => SORT_DESC])
            ->all();

        return array_map([$this, 'recordToModel'], $records);
    }

    public function generatePdf(Certificate $certificate): ?string
    {
        // Requires Pro edition and dompdf
        if (!Plugin::getInstance()->is(Plugin::EDITION_PRO)) {
            return null;
        }

        if (!class_exists('\Dompdf\Dompdf')) {
            return null;
        }

        $html = $this->renderCertificateHtml($certificate);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    public function renderCertificateHtml(Certificate $certificate): string
    {
        $settings = Plugin::getInstance()->getSettings();
        $metadata = $certificate->metadata ?? [];

        // Check for custom template
        if ($settings->certificateTemplatePath) {
            try {
                return Craft::$app->getView()->renderTemplate($settings->certificateTemplatePath, [
                    'certificate' => $certificate,
                    'metadata' => $metadata,
                ]);
            } catch (\Throwable $e) {
                // Fall through to default
            }
        }

        // Default template
        return Craft::$app->getView()->renderTemplate('diploma/certificates/_template', [
            'certificate' => $certificate,
            'metadata' => $metadata,
            'title' => $settings->certificateTitle,
            'body' => str_replace(
                ['{userName}', '{courseTitle}'],
                [$metadata['userName'] ?? '', $metadata['courseTitle'] ?? ''],
                $settings->certificateBody
            ),
        ]);
    }

    private function generateVerificationCode(): string
    {
        do {
            $code = strtoupper(StringHelper::randomString(8)) . '-' . strtoupper(StringHelper::randomString(8));
        } while (CertificateRecord::find()->where(['verificationCode' => $code])->exists());

        return $code;
    }

    /**
     * Rebuilds certificates' snapshots — the course title and learner's name and email printed on
     * them — from the current course and user, after a course was renamed, a learner changed their
     * name, or the certificate template started using a value older certificates never stored.
     *
     * The verification code and issue date are kept, so links and printed copies stay valid; the
     * PDF and verification page render through the current template either way. What
     * `diploma/certificates/reissue` runs.
     */
    public function reissue(?int $courseId = null, bool $dryRun = false): BulkResult
    {
        $result = new BulkResult(['dryRun' => $dryRun]);
        $query = CertificateRecord::find()->orderBy(['id' => SORT_ASC]);

        if ($courseId !== null) {
            $query->where(['courseId' => $courseId]);
        }

        foreach ($query->each() as $record) {
            /** @var CertificateRecord $record */
            $course = \justinholtweb\diploma\elements\Course::find()->id($record->courseId)->status(null)->one();
            $user = Craft::$app->getUsers()->getUserById((int)$record->userId);

            if ($course === null || $user === null) {
                $result->skipped[] = "{$record->verificationCode}: the " . ($course === null ? 'course' : 'learner') . ' no longer exists; the snapshot is kept.';
                continue;
            }

            $old = $record->metadata ? (json_decode($record->metadata, true) ?: []) : [];
            $issuedDate = $old['issuedDate'] ?? ($record->issuedAt ? date('F j, Y', strtotime($record->issuedAt . ' UTC')) : date('F j, Y'));
            $snapshot = array_merge($old, $this->snapshot($course, $user, $issuedDate));

            if ($snapshot === $old) {
                $result->skipped[] = "{$record->verificationCode}: already up to date.";
                continue;
            }

            if (!$dryRun) {
                $record->metadata = json_encode($snapshot);

                if (!$record->save(false)) {
                    $result->failures[] = "{$record->verificationCode}: could not be saved.";
                    continue;
                }

                if ($this->hasEventHandlers(self::EVENT_AFTER_REISSUE_CERTIFICATE)) {
                    $this->trigger(self::EVENT_AFTER_REISSUE_CERTIFICATE, new CertificateEvent([
                        'certificate' => $this->recordToModel($record),
                    ]));
                }
            }

            $result->done[] = "{$record->verificationCode}: {$snapshot['userName']} — {$snapshot['courseTitle']}.";
        }

        return $result;
    }

    /**
     * Issues the certificate every completed enrolment should have and doesn't — enrolments
     * imported as completed, or finished while certificates were switched off.
     */
    public function issueMissing(?int $courseId = null, bool $dryRun = false): BulkResult
    {
        $result = new BulkResult(['dryRun' => $dryRun]);

        $query = EnrollmentRecord::find()
            ->alias('e')
            ->leftJoin(['c' => CertificateRecord::tableName()], '[[c.enrollmentId]] = [[e.id]]')
            ->where(['e.enrollmentStatus' => 'completed', 'c.id' => null])
            ->orderBy(['e.id' => SORT_ASC]);

        if ($courseId !== null) {
            $query->andWhere(['e.courseId' => $courseId]);
        }

        foreach ($query->each() as $enrollment) {
            /** @var EnrollmentRecord $enrollment */
            $label = "Enrollment #{$enrollment->id} (user #{$enrollment->userId}, course #{$enrollment->courseId})";

            if ($dryRun) {
                $result->done[] = "$label: would be issued a certificate.";
                continue;
            }

            $certificate = $this->issue((int)$enrollment->id, (int)$enrollment->userId, (int)$enrollment->courseId);

            if ($certificate === null) {
                $result->failures[] = "$label: the certificate could not be issued.";
                continue;
            }

            $result->done[] = "$label: issued {$certificate->verificationCode}.";
        }

        return $result;
    }

    /**
     * The values a certificate keeps from the moment it was issued.
     */
    private function snapshot(?\justinholtweb\diploma\elements\Course $course, ?\craft\elements\User $user, string $issuedDate): array
    {
        return [
            'courseTitle' => $course->title ?? '',
            'userName' => $user->friendlyName ?? '',
            'userEmail' => $user->email ?? '',
            'issuedDate' => $issuedDate,
        ];
    }

    private function recordToModel(CertificateRecord $record): Certificate
    {
        $model = new Certificate();
        $model->id = $record->id;
        $model->enrollmentId = $record->enrollmentId;
        $model->userId = $record->userId;
        $model->courseId = $record->courseId;
        $model->verificationCode = $record->verificationCode;
        $model->issuedAt = $record->issuedAt;
        $model->templateHandle = $record->templateHandle;
        $model->metadata = $record->metadata ? json_decode($record->metadata, true) : null;
        $model->dateCreated = $record->dateCreated;
        $model->uid = $record->uid;

        return $model;
    }
}
