<?php

namespace justinholtweb\diploma\enums;

enum LessonType: string
{
    case Text = 'text';
    case Video = 'video';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Video => 'Video',
            self::Mixed => 'Mixed',
        };
    }
}
