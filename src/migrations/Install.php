<?php

namespace justinholtweb\diploma\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    public function safeDown(): bool
    {
        // Drop in reverse dependency order
        $this->dropTableIfExists('{{%diploma_drip_schedules}}');
        $this->dropTableIfExists('{{%diploma_certificates}}');
        $this->dropTableIfExists('{{%diploma_quiz_responses}}');
        $this->dropTableIfExists('{{%diploma_quiz_attempts}}');
        $this->dropTableIfExists('{{%diploma_progress}}');
        $this->dropTableIfExists('{{%diploma_enrollments}}');
        $this->dropTableIfExists('{{%diploma_answers}}');
        $this->dropTableIfExists('{{%diploma_questions}}');
        $this->dropTableIfExists('{{%diploma_quizzes}}');
        $this->dropTableIfExists('{{%diploma_lessons}}');
        $this->dropTableIfExists('{{%diploma_courses}}');

        return true;
    }

    private function createTables(): void
    {
        // Courses (element-backed)
        $this->createTable('{{%diploma_courses}}', [
            'id' => $this->integer()->notNull(),
            'courseStatus' => $this->string(20)->notNull()->defaultValue('draft'),
            'difficultyLevel' => $this->string(20)->null(),
            'estimatedDuration' => $this->integer()->null(),
            'enrollmentLimit' => $this->integer()->null(),
            'enrollmentCount' => $this->integer()->notNull()->defaultValue(0),
            'passingScore' => $this->integer()->null(),
            'metadata' => $this->json()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Lessons (element-backed)
        $this->createTable('{{%diploma_lessons}}', [
            'id' => $this->integer()->notNull(),
            'courseId' => $this->integer()->notNull(),
            'lessonType' => $this->string(20)->notNull()->defaultValue('text'),
            'estimatedDuration' => $this->integer()->null(),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'prerequisiteLessonId' => $this->integer()->null(),
            'dripDays' => $this->integer()->null(),
            'isFree' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Quizzes (element-backed)
        $this->createTable('{{%diploma_quizzes}}', [
            'id' => $this->integer()->notNull(),
            'courseId' => $this->integer()->null(),
            'lessonId' => $this->integer()->null(),
            'passingScore' => $this->integer()->notNull()->defaultValue(70),
            'timeLimit' => $this->integer()->null(),
            'maxAttempts' => $this->integer()->null(),
            'randomizeQuestions' => $this->boolean()->notNull()->defaultValue(false),
            'questionsPerAttempt' => $this->integer()->null(),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Questions (standalone)
        $this->createTable('{{%diploma_questions}}', [
            'id' => $this->primaryKey(),
            'quizId' => $this->integer()->notNull(),
            'questionType' => $this->string(20)->notNull(),
            'questionText' => $this->text()->notNull(),
            'explanation' => $this->text()->null(),
            'points' => $this->integer()->notNull()->defaultValue(1),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'metadata' => $this->json()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Answers (standalone)
        $this->createTable('{{%diploma_answers}}', [
            'id' => $this->primaryKey(),
            'questionId' => $this->integer()->notNull(),
            'answerText' => $this->text()->notNull(),
            'isCorrect' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Enrollments (standalone)
        $this->createTable('{{%diploma_enrollments}}', [
            'id' => $this->primaryKey(),
            'courseId' => $this->integer()->notNull(),
            'userId' => $this->integer()->notNull(),
            'enrollmentStatus' => $this->string(20)->notNull()->defaultValue('active'),
            'enrolledAt' => $this->dateTime()->notNull(),
            'completedAt' => $this->dateTime()->null(),
            'expiresAt' => $this->dateTime()->null(),
            'source' => $this->string(50)->null(),
            'sourceId' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Progress (standalone)
        $this->createTable('{{%diploma_progress}}', [
            'id' => $this->primaryKey(),
            'enrollmentId' => $this->integer()->notNull(),
            'lessonId' => $this->integer()->notNull(),
            'completedAt' => $this->dateTime()->null(),
            'timeSpent' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Quiz Attempts (standalone)
        $this->createTable('{{%diploma_quiz_attempts}}', [
            'id' => $this->primaryKey(),
            'quizId' => $this->integer()->notNull(),
            'enrollmentId' => $this->integer()->notNull(),
            'userId' => $this->integer()->notNull(),
            'score' => $this->decimal(5, 2)->null(),
            'pointsEarned' => $this->integer()->notNull()->defaultValue(0),
            'pointsPossible' => $this->integer()->notNull()->defaultValue(0),
            'passed' => $this->boolean()->notNull()->defaultValue(false),
            'startedAt' => $this->dateTime()->notNull(),
            'completedAt' => $this->dateTime()->null(),
            'timeSpent' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Quiz Responses (standalone)
        $this->createTable('{{%diploma_quiz_responses}}', [
            'id' => $this->primaryKey(),
            'attemptId' => $this->integer()->notNull(),
            'questionId' => $this->integer()->notNull(),
            'answerId' => $this->integer()->null(),
            'responseText' => $this->text()->null(),
            'isCorrect' => $this->boolean()->null(),
            'pointsAwarded' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Certificates (standalone)
        $this->createTable('{{%diploma_certificates}}', [
            'id' => $this->primaryKey(),
            'enrollmentId' => $this->integer()->notNull(),
            'userId' => $this->integer()->notNull(),
            'courseId' => $this->integer()->notNull(),
            'verificationCode' => $this->string(40)->notNull(),
            'issuedAt' => $this->dateTime()->notNull(),
            'templateHandle' => $this->string(100)->null(),
            'metadata' => $this->json()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Drip Schedules (Pro, standalone)
        $this->createTable('{{%diploma_drip_schedules}}', [
            'id' => $this->primaryKey(),
            'courseId' => $this->integer()->notNull(),
            'lessonId' => $this->integer()->notNull(),
            'delayDays' => $this->integer()->notNull()->defaultValue(0),
            'delayHours' => $this->integer()->notNull()->defaultValue(0),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createIndexes(): void
    {
        // Courses
        $this->createIndex(null, '{{%diploma_courses}}', ['courseStatus']);

        // Lessons
        $this->createIndex(null, '{{%diploma_lessons}}', ['courseId']);
        $this->createIndex(null, '{{%diploma_lessons}}', ['courseId', 'sortOrder']);
        $this->createIndex(null, '{{%diploma_lessons}}', ['prerequisiteLessonId']);

        // Quizzes
        $this->createIndex(null, '{{%diploma_quizzes}}', ['courseId']);
        $this->createIndex(null, '{{%diploma_quizzes}}', ['lessonId']);

        // Questions
        $this->createIndex(null, '{{%diploma_questions}}', ['quizId']);
        $this->createIndex(null, '{{%diploma_questions}}', ['quizId', 'sortOrder']);

        // Answers
        $this->createIndex(null, '{{%diploma_answers}}', ['questionId']);

        // Enrollments
        $this->createIndex(null, '{{%diploma_enrollments}}', ['courseId', 'userId'], true);
        $this->createIndex(null, '{{%diploma_enrollments}}', ['userId']);
        $this->createIndex(null, '{{%diploma_enrollments}}', ['enrollmentStatus']);

        // Progress
        $this->createIndex(null, '{{%diploma_progress}}', ['enrollmentId', 'lessonId'], true);
        $this->createIndex(null, '{{%diploma_progress}}', ['lessonId']);

        // Quiz Attempts
        $this->createIndex(null, '{{%diploma_quiz_attempts}}', ['quizId']);
        $this->createIndex(null, '{{%diploma_quiz_attempts}}', ['enrollmentId']);
        $this->createIndex(null, '{{%diploma_quiz_attempts}}', ['userId']);

        // Quiz Responses
        $this->createIndex(null, '{{%diploma_quiz_responses}}', ['attemptId']);
        $this->createIndex(null, '{{%diploma_quiz_responses}}', ['questionId']);

        // Certificates
        $this->createIndex(null, '{{%diploma_certificates}}', ['verificationCode'], true);
        $this->createIndex(null, '{{%diploma_certificates}}', ['enrollmentId']);
        $this->createIndex(null, '{{%diploma_certificates}}', ['userId']);
        $this->createIndex(null, '{{%diploma_certificates}}', ['courseId']);

        // Drip Schedules
        $this->createIndex(null, '{{%diploma_drip_schedules}}', ['courseId']);
        $this->createIndex(null, '{{%diploma_drip_schedules}}', ['lessonId']);
    }

    private function addForeignKeys(): void
    {
        // Courses → elements
        $this->addForeignKey(null, '{{%diploma_courses}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', null);

        // Lessons → elements, courses
        $this->addForeignKey(null, '{{%diploma_lessons}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_lessons}}', ['courseId'], '{{%diploma_courses}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_lessons}}', ['prerequisiteLessonId'], '{{%diploma_lessons}}', ['id'], 'SET NULL', null);

        // Quizzes → elements, courses, lessons
        $this->addForeignKey(null, '{{%diploma_quizzes}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_quizzes}}', ['courseId'], '{{%diploma_courses}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_quizzes}}', ['lessonId'], '{{%diploma_lessons}}', ['id'], 'SET NULL', null);

        // Questions → quizzes
        $this->addForeignKey(null, '{{%diploma_questions}}', ['quizId'], '{{%diploma_quizzes}}', ['id'], 'CASCADE', null);

        // Answers → questions
        $this->addForeignKey(null, '{{%diploma_answers}}', ['questionId'], '{{%diploma_questions}}', ['id'], 'CASCADE', null);

        // Enrollments → courses, users
        $this->addForeignKey(null, '{{%diploma_enrollments}}', ['courseId'], '{{%diploma_courses}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_enrollments}}', ['userId'], '{{%users}}', ['id'], 'CASCADE', null);

        // Progress → enrollments, lessons
        $this->addForeignKey(null, '{{%diploma_progress}}', ['enrollmentId'], '{{%diploma_enrollments}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_progress}}', ['lessonId'], '{{%diploma_lessons}}', ['id'], 'CASCADE', null);

        // Quiz Attempts → quizzes, enrollments, users
        $this->addForeignKey(null, '{{%diploma_quiz_attempts}}', ['quizId'], '{{%diploma_quizzes}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_quiz_attempts}}', ['enrollmentId'], '{{%diploma_enrollments}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_quiz_attempts}}', ['userId'], '{{%users}}', ['id'], 'CASCADE', null);

        // Quiz Responses → attempts, questions, answers
        $this->addForeignKey(null, '{{%diploma_quiz_responses}}', ['attemptId'], '{{%diploma_quiz_attempts}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_quiz_responses}}', ['questionId'], '{{%diploma_questions}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_quiz_responses}}', ['answerId'], '{{%diploma_answers}}', ['id'], 'SET NULL', null);

        // Certificates → enrollments, users, courses
        $this->addForeignKey(null, '{{%diploma_certificates}}', ['enrollmentId'], '{{%diploma_enrollments}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_certificates}}', ['userId'], '{{%users}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_certificates}}', ['courseId'], '{{%diploma_courses}}', ['id'], 'CASCADE', null);

        // Drip Schedules → courses, lessons
        $this->addForeignKey(null, '{{%diploma_drip_schedules}}', ['courseId'], '{{%diploma_courses}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%diploma_drip_schedules}}', ['lessonId'], '{{%diploma_lessons}}', ['id'], 'CASCADE', null);
    }
}
