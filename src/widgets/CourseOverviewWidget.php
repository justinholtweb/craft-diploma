<?php

namespace justinholtweb\diploma\widgets;

use Craft;
use craft\base\Widget;
use justinholtweb\diploma\Plugin;

class CourseOverviewWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('diploma', 'Diploma: Course Overview');
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@justinholtweb/diploma/icon.svg');
    }

    public function getBodyHtml(): ?string
    {
        $stats = Plugin::getInstance()->reporting->getOverviewStats();

        return Craft::$app->getView()->renderTemplate('diploma/widgets/_course-overview', [
            'stats' => $stats,
        ]);
    }
}
