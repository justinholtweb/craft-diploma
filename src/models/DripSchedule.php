<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class DripSchedule extends Model
{
    public ?int $id = null;
    public ?int $courseId = null;
    public ?int $lessonId = null;
    public int $delayDays = 0;
    public int $delayHours = 0;
    public bool $enabled = true;
    public ?string $dateCreated = null;
    public ?string $dateUpdated = null;
    public ?string $uid = null;

    public function defineRules(): array
    {
        return [
            [['courseId', 'lessonId'], 'required'],
            [['delayDays', 'delayHours'], 'integer', 'min' => 0],
            [['enabled'], 'boolean'],
        ];
    }
}
