<?php

namespace justinholtweb\diploma\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use justinholtweb\diploma\Plugin;

/**
 * Adds a per-course enrollment mode, so self-enrolment can be refused for paid and
 * invitation-only courses.
 *
 * Existing courses become `open` — what they effectively were — except any course that is
 * already sold: linked to a Commerce product through a `diplomaCourses` field, or mapped to a
 * Headcount plan. Those become `purchase`, so upgrading closes the free-enrolment hole on the
 * courses it matters for without anybody having to find them first.
 */
class m260929_000000_add_course_enrollment_mode extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%diploma_courses}}', 'enrollmentMode')) {
            $this->addColumn('{{%diploma_courses}}', 'enrollmentMode', $this->string(20)->notNull()->defaultValue('open')->after('courseStatus'));
        }

        $sold = [];

        // Courses related from any element through a field with the handle `diplomaCourses` —
        // the relation CommerceIntegration reads to enrol on purchase.
        $fieldIds = (new Query())->select('id')->from(Table::FIELDS)->where(['handle' => 'diplomaCourses'])->column();

        if ($fieldIds !== []) {
            $sold = array_merge($sold, (new Query())
                ->select('targetId')
                ->from(Table::RELATIONS)
                ->where(['fieldId' => $fieldIds])
                ->column());
        }

        $mapping = Plugin::getInstance()?->getSettings()->headcountPlanMapping ?? [];
        $sold = array_merge($sold, array_values(array_filter(array_map('intval', $mapping))));
        $sold = array_values(array_unique(array_map('intval', $sold)));

        if ($sold !== []) {
            $this->update('{{%diploma_courses}}', ['enrollmentMode' => 'purchase'], ['id' => $sold], [], false);
            Craft::info(sprintf('Diploma: %d sold course(s) set to purchase-only enrolment.', count($sold)), __METHOD__);
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%diploma_courses}}', 'enrollmentMode')) {
            $this->dropColumn('{{%diploma_courses}}', 'enrollmentMode');
        }

        return true;
    }
}
