<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use justinholtweb\diploma\elements\Lesson;

class Lessons extends Component
{
    public function getById(int $id): ?Lesson
    {
        return Lesson::find()->id($id)->one();
    }

    public function getLessonsByCourse(int $courseId): array
    {
        return Lesson::find()
            ->courseId($courseId)
            ->orderBy('sortOrder asc')
            ->all();
    }

    public function save(Lesson $lesson): bool
    {
        return Craft::$app->getElements()->saveElement($lesson);
    }

    public function delete(Lesson $lesson): bool
    {
        return Craft::$app->getElements()->deleteElement($lesson);
    }

    public function getNextSortOrder(int $courseId): int
    {
        $maxOrder = (int)Lesson::find()
            ->courseId($courseId)
            ->orderBy('sortOrder desc')
            ->limit(1)
            ->select('diploma_lessons.sortOrder')
            ->scalar();

        return $maxOrder + 1;
    }

    public function reorder(array $lessonIds): bool
    {
        $db = Craft::$app->getDb();

        foreach ($lessonIds as $order => $lessonId) {
            $db->createCommand()
                ->update('{{%diploma_lessons}}', [
                    'sortOrder' => $order,
                ], ['id' => $lessonId])
                ->execute();
        }

        return true;
    }
}
