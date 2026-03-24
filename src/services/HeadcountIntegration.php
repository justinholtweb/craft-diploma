<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use justinholtweb\diploma\Plugin;

class HeadcountIntegration extends Component
{
    public function init(): void
    {
        parent::init();

        if (!Plugin::getInstance()->is(Plugin::EDITION_PRO)) {
            return;
        }

        // Only register if Headcount is installed
        if (!Craft::$app->getPlugins()->isPluginInstalled('headcount')) {
            return;
        }

        $this->registerHeadcountEvents();
    }

    private function registerHeadcountEvents(): void
    {
        // Listen for subscription creation/updates
        if (class_exists('\justinholtweb\headcount\elements\Subscription')) {
            \yii\base\Event::on(
                \justinholtweb\headcount\elements\Subscription::class,
                \craft\base\Element::EVENT_AFTER_SAVE,
                function ($event) {
                    $this->handleSubscriptionSave($event->sender);
                }
            );
        }
    }

    private function handleSubscriptionSave($subscription): void
    {
        $userId = $subscription->userId ?? null;
        $planId = $subscription->planId ?? null;

        if (!$userId || !$planId) {
            return;
        }

        $settings = Plugin::getInstance()->getSettings();
        $planMapping = $settings->headcountPlanMapping;

        // Check if this plan is mapped to a course
        $courseId = $planMapping[$planId] ?? null;
        if (!$courseId) {
            return;
        }

        $status = $subscription->status ?? '';

        if (in_array($status, ['active', 'trialing'])) {
            // Enroll user
            Plugin::getInstance()->enrollments->enroll(
                (int)$courseId,
                (int)$userId,
                'headcount',
                $subscription->id
            );
        } elseif (in_array($status, ['expired', 'canceled', 'cancelled'])) {
            // Expire enrollment
            $enrollment = Plugin::getInstance()->enrollments->getEnrollment((int)$courseId, (int)$userId);
            if ($enrollment && $enrollment->enrollmentStatus === 'active') {
                Plugin::getInstance()->enrollments->updateStatus($enrollment->id, 'expired');
            }
        }
    }
}
