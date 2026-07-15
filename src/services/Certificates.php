<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use justinholtweb\diploma\events\CertificateEvent;
use justinholtweb\diploma\models\Certificate;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\CertificateRecord;

class Certificates extends Component
{
    /**
     * @event CertificateEvent Fired after a certificate is issued.
     */
    public const EVENT_AFTER_ISSUE_CERTIFICATE = 'afterIssueCertificate';

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
        $record->metadata = json_encode([
            'courseTitle' => $course?->title ?? '',
            'userName' => $user?->friendlyName ?? '',
            'userEmail' => $user?->email ?? '',
            'issuedDate' => date('F j, Y'),
        ]);

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
