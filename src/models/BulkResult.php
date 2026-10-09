<?php

namespace justinholtweb\diploma\models;

use craft\base\Model;

/**
 * What a bulk operation (an import, an expiry run, a certificate reissue, a drip unlock pass) did,
 * one line per row it looked at. The console commands print it; nothing stores it.
 */
class BulkResult extends Model
{
    /**
     * @var bool Whether this was a dry run — `done` then lists what *would* have happened.
     */
    public bool $dryRun = false;

    /**
     * @var string[] Rows that were (or, on a dry run, would be) acted on.
     */
    public array $done = [];

    /**
     * @var string[] Rows left alone on purpose (already enrolled, already unlocked, …).
     */
    public array $skipped = [];

    /**
     * @var string[] Rows that could not be processed, with the reason.
     */
    public array $failures = [];

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }
}
