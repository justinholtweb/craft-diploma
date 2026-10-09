<?php

namespace justinholtweb\diploma\console\controllers;

use craft\console\Controller;
use justinholtweb\diploma\console\BulkOutput;
use justinholtweb\diploma\Plugin;
use yii\console\ExitCode;

/**
 * Bulk certificate maintenance.
 */
class CertificatesController extends Controller
{
    use BulkOutput;

    /**
     * @var string|null Only this course's certificates, by ID or slug.
     */
    public ?string $course = null;

    /**
     * @var bool Also issue certificates to completed enrolments that have none.
     */
    public bool $missing = false;

    /**
     * @var bool Report what would happen without writing anything.
     */
    public bool $dryRun = false;

    public $defaultAction = 'reissue';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'reissue' => ['course', 'missing', 'dryRun'],
            default => [],
        });
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['c' => 'course']);
    }

    /**
     * Rebuild certificates' printed details (course title, learner name and email) from the
     * current course and user. Verification codes and issue dates are kept.
     *
     *     php craft diploma/certificates/reissue --course=intro-to-craft --missing
     */
    public function actionReissue(): int
    {
        $courseId = $this->courseId($this->course);

        if ($courseId === false) {
            return ExitCode::USAGE;
        }

        $certificates = Plugin::getInstance()->certificates;
        $exit = $this->report($certificates->reissue($courseId, $this->dryRun), 'reissued');

        if ($this->missing) {
            $this->stdout("\nCompleted enrolments without a certificate:\n");
            $exit = max($exit, $this->report($certificates->issueMissing($courseId, $this->dryRun), 'issued'));
        }

        return $exit;
    }
}
