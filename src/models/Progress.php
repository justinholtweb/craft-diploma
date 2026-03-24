<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class Progress extends Model
{
    public ?int $id = null;
    public ?int $enrollmentId = null;
    public ?int $lessonId = null;
    public ?string $completedAt = null;
    public int $timeSpent = 0;
    public ?string $dateCreated = null;
    public ?string $dateUpdated = null;
    public ?string $uid = null;
}
