<?php

namespace justinholtweb\diploma\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use justinholtweb\diploma\console\BulkOutput;
use justinholtweb\diploma\Plugin;
use yii\console\ExitCode;

/**
 * Drip content (Pro).
 */
class DripController extends Controller
{
    use BulkOutput;

    /**
     * @var string|null Only this course, by ID or slug.
     */
    public ?string $course = null;

    /**
     * @var int How many days back to look for unlocks. Older ones are never announced.
     */
    public int $lookback = 7;

    /**
     * @var bool Report what would happen without writing anything.
     */
    public bool $dryRun = false;

    public $defaultAction = 'unlock';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'unlock' => ['course', 'lookback', 'dryRun'],
            default => [],
        });
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['c' => 'course']);
    }

    /**
     * Announce drip lessons that have unlocked since the last run: one activity-log entry and one
     * `afterUnlockLesson` event per learner and lesson. Run it from cron, hourly or daily.
     *
     *     php craft diploma/drip/unlock
     */
    public function actionUnlock(): int
    {
        $plugin = Plugin::getInstance();

        if (!$plugin->is(Plugin::EDITION_PRO)) {
            $this->stderr("Drip content is a Diploma Pro feature.\n", Console::FG_RED);
            return ExitCode::UNAVAILABLE;
        }

        $courseId = $this->courseId($this->course);

        if ($courseId === false) {
            return ExitCode::USAGE;
        }

        return $this->report($plugin->dripContent->unlockDue($courseId, $this->lookback, $this->dryRun), 'announced');
    }
}
