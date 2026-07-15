<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class ActivityLog extends Model
{
    public ?int $id = null;
    public ?int $userId = null;
    public ?int $actorId = null;
    public string $eventType = '';
    public ?int $courseId = null;
    public ?int $lessonId = null;
    public ?int $quizId = null;
    public ?int $enrollmentId = null;
    public ?float $score = null;
    public ?bool $passed = null;
    public ?string $message = null;
    public ?array $data = null;
    public ?string $ipAddress = null;
    public ?string $dateCreated = null;
    public ?string $uid = null;
}
