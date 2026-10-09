<?php

namespace justinholtweb\diploma\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use justinholtweb\diploma\console\BulkOutput;
use justinholtweb\diploma\Plugin;
use yii\console\ExitCode;

/**
 * Bulk enrolment: import learners from another LMS, and expire enrolments whose access ran out.
 */
class EnrollmentsController extends Controller
{
    use BulkOutput;

    /**
     * @var string|null The course, by ID or slug. Required by `import`; narrows `expire`.
     */
    public ?string $course = null;

    /**
     * @var bool Report what would happen without writing anything.
     */
    public bool $dryRun = false;

    /**
     * @var bool Create a pending user for an email no user has yet (import).
     */
    public bool $createUsers = false;

    public $defaultAction = 'expire';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'import' => ['course', 'dryRun', 'createUsers'],
            'expire' => ['course', 'dryRun'],
            default => [],
        });
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['c' => 'course']);
    }

    /**
     * Enrol the learners listed in a CSV file in one course.
     *
     * The first row names the columns: `email` (required), `status` (active, completed, cancelled
     * or expired; active when blank), `date` (enrolled), `completed` and `expires`. Learners
     * already enrolled are skipped, so a file can be imported again safely.
     *
     *     php craft diploma/enrollments/import learners.csv --course=intro-to-craft --dry-run
     *
     * @param string $file Path to the CSV file.
     */
    public function actionImport(string $file): int
    {
        if ($this->course === null || $this->course === '') {
            $this->stderr("Name the course with --course=<id or slug>.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $courseId = $this->courseId($this->course);

        if ($courseId === false || $courseId === null) {
            return ExitCode::USAGE;
        }

        $rows = $this->readCsv($file);

        if ($rows === null) {
            return ExitCode::NOINPUT;
        }

        if ($rows === []) {
            $this->stdout("The file has no learners in it.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout(sprintf("Importing %d row(s) into course #%d…\n", count($rows), $courseId));

        return $this->report(
            Plugin::getInstance()->enrollments->import($courseId, $rows, $this->dryRun, $this->createUsers),
            'enrolled',
        );
    }

    /**
     * Mark active enrolments whose expiry date has passed as expired. Run it from cron.
     *
     *     php craft diploma/enrollments/expire
     */
    public function actionExpire(): int
    {
        $courseId = $this->courseId($this->course);

        if ($courseId === false) {
            return ExitCode::USAGE;
        }

        return $this->report(
            Plugin::getInstance()->enrollments->expireDue($courseId, $this->dryRun),
            'expired',
        );
    }

    /**
     * Reads the CSV into rows keyed by lower-cased header. Null (having said why) if unreadable.
     *
     * @return array<int, array<string, string|null>>|null
     */
    private function readCsv(string $file): ?array
    {
        if (!is_file($file) || !is_readable($file)) {
            $this->stderr("Can't read “{$file}”.\n", Console::FG_RED);
            return null;
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            $this->stderr("Can't open “{$file}”.\n", Console::FG_RED);
            return null;
        }

        $header = fgetcsv($handle, null, ',', '"', '');

        if (!is_array($header) || $header === [null]) {
            fclose($handle);
            $this->stderr("“{$file}” is empty.\n", Console::FG_RED);
            return null;
        }

        // Strip a UTF-8 byte-order mark (Excel writes one) and normalise the column names.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

        if (!in_array('email', $header, true)) {
            fclose($handle);
            $this->stderr("The first row must name the columns, including “email”.\n", Console::FG_RED);
            return null;
        }

        $rows = [];

        while (($line = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($line === [null]) {
                continue; // blank line
            }

            $row = [];

            foreach ($header as $i => $name) {
                $row[$name] = $line[$i] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
