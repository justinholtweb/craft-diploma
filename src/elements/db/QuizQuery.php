<?php

namespace justinholtweb\diploma\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class QuizQuery extends ElementQuery
{
    public ?int $courseId = null;
    public ?int $lessonId = null;

    public function courseId(?int $value): self
    {
        $this->courseId = $value;
        return $this;
    }

    public function lessonId(?int $value): self
    {
        $this->lessonId = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('diploma_quizzes');

        $this->query->addSelect([
            'diploma_quizzes.courseId',
            'diploma_quizzes.lessonId',
            'diploma_quizzes.passingScore',
            'diploma_quizzes.timeLimit',
            'diploma_quizzes.maxAttempts',
            'diploma_quizzes.randomizeQuestions',
            'diploma_quizzes.questionsPerAttempt',
            'diploma_quizzes.sortOrder',
        ]);

        if ($this->courseId !== null) {
            $this->subQuery->andWhere(Db::parseParam('diploma_quizzes.courseId', $this->courseId));
        }

        if ($this->lessonId !== null) {
            $this->subQuery->andWhere(Db::parseParam('diploma_quizzes.lessonId', $this->lessonId));
        }

        return parent::beforePrepare();
    }
}
