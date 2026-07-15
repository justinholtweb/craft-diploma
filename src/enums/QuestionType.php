<?php

namespace justinholtweb\diploma\enums;

enum QuestionType: string
{
    case MultipleChoice = 'multipleChoice';
    case MultipleResponse = 'multipleResponse';
    case TrueFalse = 'trueFalse';
    case ShortAnswer = 'shortAnswer';
    case Matching = 'matching';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple Choice',
            self::MultipleResponse => 'Multiple Response (select all)',
            self::TrueFalse => 'True/False',
            self::ShortAnswer => 'Short Answer',
            self::Matching => 'Matching',
        };
    }

    /**
     * Whether learners may select more than one answer for this type.
     */
    public function allowsMultipleAnswers(): bool
    {
        return $this === self::MultipleResponse;
    }
}
