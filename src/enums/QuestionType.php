<?php

namespace justinholtweb\diploma\enums;

enum QuestionType: string
{
    case MultipleChoice = 'multipleChoice';
    case TrueFalse = 'trueFalse';
    case ShortAnswer = 'shortAnswer';
    case Matching = 'matching';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple Choice',
            self::TrueFalse => 'True/False',
            self::ShortAnswer => 'Short Answer',
            self::Matching => 'Matching',
        };
    }
}
