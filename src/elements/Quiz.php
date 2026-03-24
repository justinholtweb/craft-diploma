<?php

namespace justinholtweb\diploma\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\helpers\UrlHelper;
use justinholtweb\diploma\elements\db\QuizQuery;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\QuizRecord;
use yii\base\InvalidConfigException;

class Quiz extends Element
{
    public ?int $courseId = null;
    public ?int $lessonId = null;
    public int $passingScore = 70;
    public ?int $timeLimit = null;
    public ?int $maxAttempts = null;
    public bool $randomizeQuestions = false;
    public ?int $questionsPerAttempt = null;
    public int $sortOrder = 0;

    public static function displayName(): string
    {
        return Craft::t('diploma', 'Quiz');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('diploma', 'Quizzes');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('diploma', 'quiz');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('diploma', 'quizzes');
    }

    public static function refHandle(): ?string
    {
        return 'quiz';
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return false;
    }

    public static function find(): QuizQuery
    {
        return new QuizQuery(static::class);
    }

    public static function defineSources(string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('diploma', 'All Quizzes'),
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'courseId' => Craft::t('diploma', 'Course'),
            'passingScore' => Craft::t('diploma', 'Passing Score'),
            'timeLimit' => Craft::t('diploma', 'Time Limit'),
            'maxAttempts' => Craft::t('diploma', 'Max Attempts'),
            'dateCreated' => Craft::t('app', 'Date Created'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'courseId', 'passingScore', 'maxAttempts', 'dateCreated'];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    protected static function defineActions(string $source = null): array
    {
        return [
            Delete::class,
            Restore::class,
        ];
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("diploma/quizzes/{$this->id}");
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'courseId' => $this->getCourse()?->title ?? '—',
            'passingScore' => $this->passingScore . '%',
            'timeLimit' => $this->timeLimit ? round($this->timeLimit / 60) . ' min' : '—',
            'maxAttempts' => $this->maxAttempts ?? 'Unlimited',
            default => parent::tableAttributeHtml($attribute),
        };
    }

    public function getCourse(): ?Course
    {
        if (!$this->courseId) {
            return null;
        }

        return Course::find()->id($this->courseId)->one();
    }

    public function getLesson(): ?Lesson
    {
        if (!$this->lessonId) {
            return null;
        }

        return Lesson::find()->id($this->lessonId)->one();
    }

    public function getQuestions(): array
    {
        return Plugin::getInstance()->quizzes->getQuestionsByQuiz($this->id);
    }

    public function getQuestionCount(): int
    {
        return Plugin::getInstance()->quizzes->getQuestionCount($this->id);
    }

    public function canView(\craft\elements\User $user): bool
    {
        return $user->can('diploma:manageQuizzes') || $user->can('diploma:accessPlugin');
    }

    public function canSave(\craft\elements\User $user): bool
    {
        return $user->can('diploma:manageQuizzes');
    }

    public function canDelete(\craft\elements\User $user): bool
    {
        return $user->can('diploma:deleteQuizzes');
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['passingScore'], 'required'];
        $rules[] = [['courseId', 'lessonId', 'timeLimit', 'maxAttempts', 'questionsPerAttempt', 'sortOrder'], 'integer'];
        $rules[] = [['passingScore'], 'integer', 'min' => 0, 'max' => 100];
        $rules[] = [['randomizeQuestions'], 'boolean'];

        return $rules;
    }

    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            $record = new QuizRecord();
        } else {
            $record = QuizRecord::findOne($this->id);
            if (!$record) {
                throw new InvalidConfigException("Invalid quiz ID: {$this->id}");
            }
        }

        $record->id = $this->id;
        $record->courseId = $this->courseId;
        $record->lessonId = $this->lessonId;
        $record->passingScore = $this->passingScore;
        $record->timeLimit = $this->timeLimit;
        $record->maxAttempts = $this->maxAttempts;
        $record->randomizeQuestions = $this->randomizeQuestions;
        $record->questionsPerAttempt = $this->questionsPerAttempt;
        $record->sortOrder = $this->sortOrder;

        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        $record = QuizRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }
}
