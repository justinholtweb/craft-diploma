<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class Settings extends Model
{
    public bool $enableCertificates = true;
    public bool $enableCourseUrls = false;
    public string $courseUriFormat = 'courses/{slug}';
    public string $courseTemplate = '';
    public string $certificateTemplatePath = '';
    public bool $autoEnrollOnPurchase = true;
    public bool $autoIssueCertificate = true;
    public bool $requirePassingQuiz = false;
    public int $defaultPassingScore = 70;
    public string $certificateTitle = 'Certificate of Completion';
    public string $certificateBody = 'This certifies that {userName} has successfully completed the course "{courseTitle}".';
    public array $commerceProductMapping = [];
    public array $headcountPlanMapping = [];

    public function defineRules(): array
    {
        return [
            [['enableCertificates', 'enableCourseUrls', 'autoEnrollOnPurchase', 'autoIssueCertificate', 'requirePassingQuiz'], 'boolean'],
            [['defaultPassingScore'], 'integer', 'min' => 0, 'max' => 100],
            [['certificateTemplatePath', 'certificateTitle', 'certificateBody', 'courseUriFormat', 'courseTemplate'], 'string'],
            [['courseUriFormat', 'courseTemplate'], 'required', 'when' => fn(Settings $model) => $model->enableCourseUrls],
        ];
    }
}
