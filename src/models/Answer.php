<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class Answer extends Model
{
    public ?int $id = null;
    public ?int $questionId = null;
    public string $answerText = '';
    public bool $isCorrect = false;
    public int $sortOrder = 0;
    public ?string $dateCreated = null;
    public ?string $uid = null;

    public function defineRules(): array
    {
        return [
            [['questionId', 'answerText'], 'required'],
            [['sortOrder'], 'integer'],
            [['isCorrect'], 'boolean'],
        ];
    }
}
