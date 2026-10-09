<?php

namespace justinholtweb\diploma\console;

use craft\helpers\Console;
use justinholtweb\diploma\models\BulkResult;
use yii\console\ExitCode;

/**
 * Prints a {@see BulkResult} the same way for every Diploma command.
 *
 * @mixin \craft\console\Controller
 */
trait BulkOutput
{
    private function report(BulkResult $result, string $doneLabel): int
    {
        foreach ($result->done as $line) {
            $this->stdout('  ' . $line . "\n");
        }

        foreach ($result->skipped as $line) {
            $this->stdout('  skipped: ' . $line . "\n", Console::FG_GREY);
        }

        foreach ($result->failures as $line) {
            $this->stderr('  ' . $line . "\n", Console::FG_RED);
        }

        $summary = sprintf(
            "%s: %d, skipped: %d, failed: %d.\n",
            $result->dryRun ? "Would be $doneLabel" : ucfirst($doneLabel),
            count($result->done),
            count($result->skipped),
            count($result->failures),
        );

        $this->stdout($summary, $result->hasFailures() ? Console::FG_YELLOW : Console::FG_GREEN);

        if ($result->dryRun) {
            $this->stdout("Dry run — nothing was written.\n", Console::FG_GREEN);
        }

        return $result->hasFailures() ? ExitCode::DATAERR : ExitCode::OK;
    }

    /**
     * Resolves `--course` (an ID or slug). Returns false, having said why, if it names nothing.
     */
    private function courseId(?string $ref): int|false|null
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        $course = \justinholtweb\diploma\Plugin::getInstance()->courses->resolve($ref);

        if ($course === null) {
            $this->stderr("No course has the ID or slug “{$ref}”.\n", Console::FG_RED);
            return false;
        }

        return (int)$course->id;
    }
}
