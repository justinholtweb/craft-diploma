<?php

namespace justinholtweb\diploma\twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DiplomaTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('diplomaDuration', [$this, 'formatDuration']),
            new TwigFilter('diplomaProgress', [$this, 'formatProgress']),
            new TwigFilter('diplomaTimeSpent', [$this, 'formatTimeSpent']),
        ];
    }

    /**
     * Format duration in minutes to human-readable string.
     * e.g., 90 → "1h 30m", 45 → "45m"
     */
    public function formatDuration(?int $minutes): string
    {
        if (!$minutes) {
            return '—';
        }

        if ($minutes < 60) {
            return $minutes . 'm';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
    }

    /**
     * Format progress as percentage string.
     * e.g., 0.75 → "75%"
     */
    public function formatProgress(float|int $value): string
    {
        if ($value > 1) {
            // Assume it's already a percentage
            return round($value, 1) . '%';
        }

        return round($value * 100, 1) . '%';
    }

    /**
     * Format time spent in seconds to human-readable string.
     * e.g., 3661 → "1h 1m 1s"
     */
    public function formatTimeSpent(?int $seconds): string
    {
        if (!$seconds) {
            return '—';
        }

        $hours = (int)floor($seconds / 3600);
        $minutes = (int)floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        if ($secs > 0 && $hours === 0) {
            $parts[] = "{$secs}s";
        }

        return implode(' ', $parts) ?: '0s';
    }
}
