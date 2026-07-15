<?php

namespace justinholtweb\diploma\migrations;

use craft\db\Migration;

/**
 * Adds the append-only activity/audit log table.
 */
class m260715_120000_add_activity_log extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%diploma_activity_log}}')) {
            return true;
        }

        $this->createTable('{{%diploma_activity_log}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->null(),
            'actorId' => $this->integer()->null(),
            'eventType' => $this->string(50)->notNull(),
            'courseId' => $this->integer()->null(),
            'lessonId' => $this->integer()->null(),
            'quizId' => $this->integer()->null(),
            'enrollmentId' => $this->integer()->null(),
            'score' => $this->decimal(5, 2)->null(),
            'passed' => $this->boolean()->null(),
            'message' => $this->string(255)->null(),
            'data' => $this->json()->null(),
            'ipAddress' => $this->string(45)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%diploma_activity_log}}', ['eventType']);
        $this->createIndex(null, '{{%diploma_activity_log}}', ['userId']);
        $this->createIndex(null, '{{%diploma_activity_log}}', ['courseId']);
        $this->createIndex(null, '{{%diploma_activity_log}}', ['enrollmentId']);
        $this->createIndex(null, '{{%diploma_activity_log}}', ['dateCreated']);

        // SET NULL so audit rows survive user/course deletion. enrollmentId,
        // lessonId and quizId are intentionally left without foreign keys.
        $this->addForeignKey(null, '{{%diploma_activity_log}}', ['userId'], '{{%users}}', ['id'], 'SET NULL', null);
        $this->addForeignKey(null, '{{%diploma_activity_log}}', ['actorId'], '{{%users}}', ['id'], 'SET NULL', null);
        $this->addForeignKey(null, '{{%diploma_activity_log}}', ['courseId'], '{{%diploma_courses}}', ['id'], 'SET NULL', null);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%diploma_activity_log}}');

        return true;
    }
}
