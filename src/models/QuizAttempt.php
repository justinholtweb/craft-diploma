<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

class QuizAttempt extends Model
{
    public ?int $id = null;
    public ?int $quizId = null;
    public ?int $enrollmentId = null;
    public ?int $userId = null;
    public ?float $score = null;
    public int $pointsEarned = 0;
    public int $pointsPossible = 0;
    public bool $passed = false;
    public ?string $startedAt = null;
    public ?string $completedAt = null;
    public ?int $timeSpent = null;
    public ?string $dateCreated = null;
    public ?string $uid = null;

    public array $responses = [];
}
