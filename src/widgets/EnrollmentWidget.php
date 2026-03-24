<?php

namespace justinholtweb\diploma\widgets;

use Craft;
use craft\base\Widget;
use justinholtweb\diploma\records\EnrollmentRecord;

class EnrollmentWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('diploma', 'Diploma: Recent Enrollments');
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@justinholtweb/diploma/icon.svg');
    }

    public function getBodyHtml(): ?string
    {
        $enrollments = EnrollmentRecord::find()
            ->orderBy(['enrolledAt' => SORT_DESC])
            ->limit(5)
            ->all();

        return Craft::$app->getView()->renderTemplate('diploma/widgets/_enrollment', [
            'enrollments' => $enrollments,
        ]);
    }
}
