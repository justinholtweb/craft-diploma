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
