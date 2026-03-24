<?php

namespace justinholtweb\diploma\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class LessonQuery extends ElementQuery
{
    public ?int $courseId = null;
    public ?string $lessonType = null;
    public ?bool $isFree = null;

    public function courseId(?int $value): self
    {
        $this->courseId = $value;
        return $this;
    }

    public function lessonType(?string $value): self
    {
        $this->lessonType = $value;
        return $this;
    }

    public function isFree(?bool $value): self
    {
        $this->isFree = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('diploma_lessons');

        $this->query->addSelect([
            'diploma_lessons.courseId',
            'diploma_lessons.lessonType',
            'diploma_lessons.estimatedDuration',
            'diploma_lessons.sortOrder',
            'diploma_lessons.prerequisiteLessonId',
            'diploma_lessons.dripDays',
            'diploma_lessons.isFree',
        ]);

        if ($this->courseId !== null) {
            $this->subQuery->andWhere(Db::parseParam('diploma_lessons.courseId', $this->courseId));
        }

        if ($this->lessonType !== null) {
            $this->subQuery->andWhere(Db::parseParam('diploma_lessons.lessonType', $this->lessonType));
        }

        if ($this->isFree !== null) {
            $this->subQuery->andWhere(['diploma_lessons.isFree' => $this->isFree]);
        }

        return parent::beforePrepare();
    }
}
