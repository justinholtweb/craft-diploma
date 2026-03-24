<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class Question extends Model
{
    public ?int $id = null;
    public ?int $quizId = null;
    public string $questionType = 'multipleChoice';
    public string $questionText = '';
    public ?string $explanation = null;
    public int $points = 1;
    public int $sortOrder = 0;
    public ?array $metadata = null;
    public ?string $dateCreated = null;
    public ?string $dateUpdated = null;
    public ?string $uid = null;

    public array $answers = [];

    public function defineRules(): array
    {
        return [
            [['quizId', 'questionType', 'questionText'], 'required'],
            [['questionType'], 'in', 'range' => ['multipleChoice', 'trueFalse', 'shortAnswer', 'matching']],
            [['points', 'sortOrder'], 'integer'],
            [['points'], 'integer', 'min' => 0],
        ];
    }
}
