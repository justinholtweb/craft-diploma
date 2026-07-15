<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\enums\ActivityType;
use justinholtweb\diploma\Plugin;
use yii\web\Response;

class ActivityLogController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('diploma:viewActivityLog');

        return true;
    }

    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();

        $criteria = $this->criteriaFromRequest();

        $perPage = 50;
        $page = max(1, (int)$request->getParam('page', 1));

        $total = Plugin::getInstance()->activityLog->getActivityCount($criteria);

        $entries = Plugin::getInstance()->activityLog->getActivity($criteria + [
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]);

        return $this->renderTemplate('diploma/activity-log/_index', [
            'entries' => $entries,
            'typeOptions' => $this->typeOptions(),
            'typeMeta' => $this->typeMeta(),
            'selectedType' => $criteria['eventType'] ?? '',
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => (int)ceil($total / $perPage),
        ]);
    }

    /**
     * Streams the (optionally filtered) activity log as CSV for compliance
     * exports and record-keeping.
     */
    public function actionExport(): Response
    {
        $criteria = $this->criteriaFromRequest();
        // Export the full matching set, not just the current page.
        $entries = Plugin::getInstance()->activityLog->getActivity($criteria + ['limit' => 100000]);

        $rows = [];
        $rows[] = ['Date', 'Event', 'User ID', 'User', 'Course ID', 'Course', 'Score', 'Passed', 'Detail', 'Actor ID', 'IP'];

        foreach ($entries as $entry) {
            $type = ActivityType::tryFrom($entry->eventType);
            $rows[] = [
                $entry->dateCreated,
                $type?->label() ?? $entry->eventType,
                $entry->userId,
                $entry->data['userName'] ?? '',
                $entry->courseId,
                $entry->data['courseTitle'] ?? '',
                $entry->score,
                $entry->passed === null ? '' : ($entry->passed ? 'yes' : 'no'),
                $entry->message,
                $entry->actorId,
                $entry->ipAddress,
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $response = Craft::$app->getResponse();
        $response->content = $csv;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'text/csv; charset=utf-8');
        $response->getHeaders()->set('Content-Disposition', 'attachment; filename="diploma-activity-log.csv"');

        return $response;
    }

    private function criteriaFromRequest(): array
    {
        $request = Craft::$app->getRequest();
        $criteria = [];

        $eventType = $request->getParam('eventType');
        if ($eventType && ActivityType::tryFrom($eventType)) {
            $criteria['eventType'] = $eventType;
        }

        foreach (['userId', 'courseId', 'enrollmentId'] as $key) {
            $value = $request->getParam($key);
            if ($value !== null && $value !== '') {
                $criteria[$key] = (int)$value;
            }
        }

        return $criteria;
    }

    private function typeOptions(): array
    {
        $options = [['label' => Craft::t('diploma', 'All events'), 'value' => '']];
        foreach (ActivityType::cases() as $case) {
            $options[] = ['label' => $case->label(), 'value' => $case->value];
        }

        return $options;
    }

    /**
     * value => ['label' => ..., 'color' => ...] for rendering status badges.
     */
    private function typeMeta(): array
    {
        $meta = [];
        foreach (ActivityType::cases() as $case) {
            $meta[$case->value] = ['label' => $case->label(), 'color' => $case->color()];
        }

        return $meta;
    }
}
