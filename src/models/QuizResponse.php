<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class QuizResponse extends Model
{
    public ?int $id = null;
    public ?int $attemptId = null;
    public ?int $questionId = null;
    public ?int $answerId = null;
    public ?string $responseText = null;
    public ?bool $isCorrect = null;
    public int $pointsAwarded = 0;
    public ?string $dateCreated = null;
    public ?string $uid = null;
}
