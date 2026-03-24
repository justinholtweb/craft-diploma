<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use justinholtweb\diploma\models\Edition;
use justinholtweb\diploma\Plugin;

class CommerceIntegration extends Component
{
    public function init(): void
    {
        parent::init();

        if (!Plugin::getInstance()->is(Plugin::EDITION_PRO)) {
            return;
        }

        // Only register if Commerce is installed
        if (!Craft::$app->getPlugins()->isPluginInstalled('commerce')) {
            return;
        }

        $this->registerCommerceEvents();
    }

    private function registerCommerceEvents(): void
    {
        // Listen for order completion
        if (class_exists('\craft\commerce\elements\Order')) {
            \yii\base\Event::on(
                \craft\commerce\elements\Order::class,
                \craft\commerce\elements\Order::EVENT_AFTER_COMPLETE_ORDER,
                function ($event) {
                    $this->handleOrderComplete($event->sender);
                }
            );
        }
    }

    private function handleOrderComplete($order): void
    {
        $settings = Plugin::getInstance()->getSettings();
        if (!$settings->autoEnrollOnPurchase) {
            return;
        }

        $userId = $order->getCustomer()?->id;
        if (!$userId) {
            return;
        }

        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();
            if (!$purchasable) {
                continue;
            }

            // Look for course relation on the product
            $courseIds = $this->getCourseIdsFromPurchasable($purchasable);

            foreach ($courseIds as $courseId) {
                Plugin::getInstance()->enrollments->enroll(
                    $courseId,
                    $userId,
                    'commerce',
                    $order->id
                );
            }
        }
    }

    private function getCourseIdsFromPurchasable($purchasable): array
    {
        $courseIds = [];

        // Check for a diplomaCourse relation field
        try {
            $product = $purchasable->getProduct();
            if ($product && method_exists($product, 'getFieldValue')) {
                $courses = $product->getFieldValue('diplomaCourses');
                if ($courses) {
                    foreach ($courses->all() as $course) {
                        $courseIds[] = $course->id;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Field doesn't exist, skip
        }

        return $courseIds;
    }
}
