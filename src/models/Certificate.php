<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;
use craft\helpers\UrlHelper;

class Certificate extends Model
{
    public ?int $id = null;
    public ?int $enrollmentId = null;
    public ?int $userId = null;
    public ?int $courseId = null;
    public ?string $verificationCode = null;
    public ?string $issuedAt = null;
    public ?string $templateHandle = null;
    public ?array $metadata = null;
    public ?string $dateCreated = null;
    public ?string $uid = null;

    public function getVerifyUrl(): string
    {
        return UrlHelper::siteUrl("diploma/certificate/verify/{$this->verificationCode}");
    }

    public function getDownloadUrl(): string
    {
        return UrlHelper::siteUrl("diploma/certificate/download/{$this->verificationCode}");
    }
}
