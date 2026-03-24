<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class Enrollment extends Model
{
    public ?int $id = null;
    public ?int $courseId = null;
    public ?int $userId = null;
    public string $enrollmentStatus = 'active';
    public ?string $enrolledAt = null;
    public ?string $completedAt = null;
    public ?string $expiresAt = null;
    public ?string $source = null;
    public ?int $sourceId = null;
    public ?string $dateCreated = null;
    public ?string $dateUpdated = null;
    public ?string $uid = null;

    public function defineRules(): array
    {
        return [
            [['courseId', 'userId'], 'required'],
            [['courseId', 'userId', 'sourceId'], 'integer'],
            [['enrollmentStatus'], 'in', 'range' => ['active', 'completed', 'cancelled', 'expired']],
            [['source'], 'string', 'max' => 50],
        ];
    }
}
