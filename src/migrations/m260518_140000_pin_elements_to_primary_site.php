<?php

namespace justinholtweb\diploma\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Db;
use justinholtweb\diploma\elements\Course;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\elements\Quiz;

/**
 * Pins every existing Course, Lesson, and Quiz to the primary site.
 *
 * Diploma elements are single-site (each one lives only on the site it was
 * created on). This migration removes any cross-site `elements_sites` rows
 * from earlier installs so element queries don't return duplicates from
 * other sites.
 */
class m260518_140000_pin_elements_to_primary_site extends Migration
{
    public function safeUp(): bool
    {
        $sites = Craft::$app->getSites();

        if (count($sites->getAllSites()) < 2) {
            return true;
        }

        $primarySiteId = $sites->getPrimarySite()->id;
        $elementIds = (new Query())
            ->select(['id'])
            ->from(['{{%elements}}'])
            ->where(['type' => [Course::class, Lesson::class, Quiz::class]])
            ->column();

        if (empty($elementIds)) {
            return true;
        }

        Db::delete('{{%elements_sites}}', [
            'and',
            ['elementId' => $elementIds],
            ['not', ['siteId' => $primarySiteId]],
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
