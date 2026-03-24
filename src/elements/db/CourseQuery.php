<?php

namespace justinholtweb\diploma\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class CourseQuery extends ElementQuery
{
    public ?string $courseStatus = null;
    public ?string $difficultyLevel = null;
    public mixed $enrollmentLimit = null;

    public function courseStatus(?string $value): self
    {
        $this->courseStatus = $value;
        return $this;
    }

    public function status(array|string|null $value): static
    {
        if (is_string($value) && in_array($value, ['draft', 'published', 'archived'])) {
            $this->courseStatus = $value;
            return $this;
        }

        return parent::status($value);
    }

    public function difficultyLevel(?string $value): self
    {
        $this->difficultyLevel = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('diploma_courses');

        $this->query->addSelect([
            'diploma_courses.courseStatus',
            'diploma_courses.difficultyLevel',
            'diploma_courses.estimatedDuration',
            'diploma_courses.enrollmentLimit',
            'diploma_courses.enrollmentCount',
            'diploma_courses.passingScore',
            'diploma_courses.metadata',
        ]);

        if ($this->courseStatus !== null) {
            $this->subQuery->andWhere(Db::parseParam('diploma_courses.courseStatus', $this->courseStatus));
        }

        if ($this->difficultyLevel !== null) {
            $this->subQuery->andWhere(Db::parseParam('diploma_courses.difficultyLevel', $this->difficultyLevel));
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            'draft' => ['diploma_courses.courseStatus' => 'draft'],
            'published' => ['diploma_courses.courseStatus' => 'published'],
            'archived' => ['diploma_courses.courseStatus' => 'archived'],
            default => parent::statusCondition($status),
        };
    }
}
