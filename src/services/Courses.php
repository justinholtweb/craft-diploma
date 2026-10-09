<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use justinholtweb\diploma\elements\Course;

class Courses extends Component
{
    public function getById(int $id): ?Course
    {
        return Course::find()->id($id)->one();
    }

    public function getBySlug(string $slug): ?Course
    {
        return Course::find()->slug($slug)->one();
    }

    /**
     * Finds a course by ID or slug, whatever its status — how the console commands' `--course`
     * option names one. A migration usually imports into a course before it is published.
     */
    public function resolve(string $ref): ?Course
    {
        $ref = trim($ref);

        if ($ref === '') {
            return null;
        }

        $query = Course::find()->status(null);

        return ctype_digit($ref) ? $query->id((int)$ref)->one() : $query->slug(\craft\helpers\Db::escapeParam($ref))->one();
    }

    public function save(Course $course): bool
    {
        return Craft::$app->getElements()->saveElement($course);
    }

    public function delete(Course $course): bool
    {
        return Craft::$app->getElements()->deleteElement($course);
    }

    public function incrementEnrollmentCount(int $courseId): void
    {
        Craft::$app->getDb()->createCommand()
            ->update('{{%diploma_courses}}', [
                'enrollmentCount' => new \yii\db\Expression('[[enrollmentCount]] + 1'),
            ], ['id' => $courseId])
            ->execute();
    }

    public function decrementEnrollmentCount(int $courseId): void
    {
        Craft::$app->getDb()->createCommand()
            ->update('{{%diploma_courses}}', [
                'enrollmentCount' => new \yii\db\Expression('GREATEST([[enrollmentCount]] - 1, 0)'),
            ], ['id' => $courseId])
            ->execute();
    }
}
