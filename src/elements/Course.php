<?php

namespace justinholtweb\diploma\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;
use justinholtweb\diploma\elements\db\CourseQuery;
use justinholtweb\diploma\enums\CourseStatus;
use justinholtweb\diploma\records\CourseRecord;
use yii\base\InvalidConfigException;

class Course extends Element
{
    public string $courseStatus = 'draft';
    public ?string $difficultyLevel = null;
    public ?int $estimatedDuration = null;
    public ?int $enrollmentLimit = null;
    public int $enrollmentCount = 0;
    public ?int $passingScore = null;
    public ?array $metadata = null;

    public static function displayName(): string
    {
        return Craft::t('diploma', 'Course');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('diploma', 'Courses');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('diploma', 'course');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('diploma', 'courses');
    }

    public static function refHandle(): ?string
    {
        return 'course';
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            'draft' => ['label' => Craft::t('diploma', 'Draft'), 'color' => 'white'],
            'published' => ['label' => Craft::t('diploma', 'Published'), 'color' => 'green'],
            'archived' => ['label' => Craft::t('diploma', 'Archived'), 'color' => 'light'],
        ];
    }

    public static function find(): CourseQuery
    {
        return new CourseQuery(static::class);
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public function getSupportedSites(): array
    {
        return [$this->siteId ?? Craft::$app->getSites()->getCurrentSite()->id];
    }

    public static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('diploma', 'All Courses'),
            ],
            [
                'key' => 'status:draft',
                'label' => Craft::t('diploma', 'Drafts'),
                'criteria' => ['courseStatus' => 'draft'],
            ],
            [
                'key' => 'status:published',
                'label' => Craft::t('diploma', 'Published'),
                'criteria' => ['courseStatus' => 'published'],
            ],
            [
                'key' => 'status:archived',
                'label' => Craft::t('diploma', 'Archived'),
                'criteria' => ['courseStatus' => 'archived'],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'courseStatus' => Craft::t('diploma', 'Status'),
            'difficultyLevel' => Craft::t('diploma', 'Difficulty'),
            'estimatedDuration' => Craft::t('diploma', 'Duration'),
            'enrollmentCount' => Craft::t('diploma', 'Enrollments'),
            'dateCreated' => Craft::t('app', 'Date Created'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'courseStatus', 'difficultyLevel', 'enrollmentCount', 'dateCreated'];
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
                'label' => Craft::t('diploma', 'Enrollments'),
                'orderBy' => 'diploma_courses.enrollmentCount',
                'attribute' => 'enrollmentCount',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    protected static function defineActions(?string $source = null): array
    {
        return [
            Delete::class,
            Restore::class,
        ];
    }

    public function getStatus(): ?string
    {
        return $this->courseStatus;
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("diploma/courses/{$this->id}");
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'courseStatus' => '<span class="status ' . CourseStatus::from($this->courseStatus)->color() . '"></span>' . CourseStatus::from($this->courseStatus)->label(),
            'difficultyLevel' => $this->difficultyLevel ? ucfirst($this->difficultyLevel) : '—',
            'estimatedDuration' => $this->estimatedDuration ? $this->estimatedDuration . ' min' : '—',
            default => parent::tableAttributeHtml($attribute),
        };
    }

    public function getLessons(): array
    {
        return Lesson::find()->courseId($this->id)->orderBy(['sortOrder' => SORT_ASC])->all();
    }

    public function getLessonCount(): int
    {
        return Lesson::find()->courseId($this->id)->count();
    }

    public function getQuizzes(): array
    {
        return Quiz::find()->courseId($this->id)->all();
    }

    public function canView(\craft\elements\User $user): bool
    {
        return $user->can('diploma:manageCourses') || $user->can('diploma:accessPlugin');
    }

    public function canSave(\craft\elements\User $user): bool
    {
        return $user->can('diploma:manageCourses');
    }

    public function canDelete(\craft\elements\User $user): bool
    {
        return $user->can('diploma:deleteCourses');
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['courseStatus'], 'required'];
        $rules[] = [['courseStatus'], 'in', 'range' => ['draft', 'published', 'archived']];
        $rules[] = [['difficultyLevel'], 'in', 'range' => ['beginner', 'intermediate', 'advanced'], 'skipOnEmpty' => true];
        $rules[] = [['estimatedDuration', 'enrollmentLimit', 'enrollmentCount', 'passingScore'], 'integer'];
        $rules[] = [['passingScore'], 'integer', 'min' => 0, 'max' => 100];

        return $rules;
    }

    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            $record = new CourseRecord();
        } else {
            $record = CourseRecord::findOne($this->id);
            if (!$record) {
                throw new InvalidConfigException("Invalid course ID: {$this->id}");
            }
        }

        $record->id = $this->id;
        $record->courseStatus = $this->courseStatus;
        $record->difficultyLevel = $this->difficultyLevel;
        $record->estimatedDuration = $this->estimatedDuration;
        $record->enrollmentLimit = $this->enrollmentLimit;
        $record->enrollmentCount = $this->enrollmentCount;
        $record->passingScore = $this->passingScore;
        $record->metadata = $this->metadata;

        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        $record = CourseRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }
}
