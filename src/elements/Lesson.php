<?php

namespace justinholtweb\diploma\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use justinholtweb\diploma\elements\db\LessonQuery;
use justinholtweb\diploma\records\LessonRecord;
use yii\base\InvalidConfigException;

class Lesson extends Element
{
    public ?int $courseId = null;
    public string $lessonType = 'text';
    public ?int $estimatedDuration = null;
    public int $sortOrder = 0;
    public ?int $prerequisiteLessonId = null;
    public ?int $dripDays = null;
    public bool $isFree = false;

    public static function displayName(): string
    {
        return Craft::t('diploma', 'Lesson');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('diploma', 'Lessons');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('diploma', 'lesson');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('diploma', 'lessons');
    }

    public static function refHandle(): ?string
    {
        return 'lesson';
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

    /**
     * Lessons share a single, admin-designed field layout (managed under
     * Diploma → Settings → Lesson Content Fields). This lets a lesson hold
     * rich mixed content — CKEditor/rich text, embedded assets (PDF, video,
     * images), Matrix blocks, links, etc. — via Craft's native fields.
     */
    public function getFieldLayout(): ?FieldLayout
    {
        $fieldLayout = parent::getFieldLayout();
        if ($fieldLayout) {
            return $fieldLayout;
        }

        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    public static function find(): LessonQuery
    {
        return new LessonQuery(static::class);
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
                'label' => Craft::t('diploma', 'All Lessons'),
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'lessonType' => Craft::t('diploma', 'Type'),
            'estimatedDuration' => Craft::t('diploma', 'Duration'),
            'sortOrder' => Craft::t('diploma', 'Order'),
            'isFree' => Craft::t('diploma', 'Free Preview'),
            'dateCreated' => Craft::t('app', 'Date Created'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'lessonType', 'sortOrder', 'estimatedDuration'];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('diploma', 'Sort Order'),
                'orderBy' => 'diploma_lessons.sortOrder',
                'attribute' => 'sortOrder',
                'defaultDir' => 'asc',
            ],
            'title' => Craft::t('app', 'Title'),
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

    public function getCpEditUrl(): ?string
    {
        if ($this->courseId) {
            return UrlHelper::cpUrl("diploma/courses/{$this->courseId}/lessons/{$this->id}");
        }

        return null;
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'lessonType' => ucfirst($this->lessonType),
            'estimatedDuration' => $this->estimatedDuration ? $this->estimatedDuration . ' min' : '—',
            'isFree' => $this->isFree ? '<span class="status green"></span>Yes' : '—',
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

    public function getPrerequisiteLesson(): ?Lesson
    {
        if (!$this->prerequisiteLessonId) {
            return null;
        }

        return Lesson::find()->id($this->prerequisiteLessonId)->one();
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
        $rules[] = [['courseId'], 'required'];
        $rules[] = [['courseId', 'sortOrder', 'estimatedDuration', 'prerequisiteLessonId', 'dripDays'], 'integer'];
        $rules[] = [['lessonType'], 'in', 'range' => ['text', 'video', 'mixed']];
        $rules[] = [['isFree'], 'boolean'];

        return $rules;
    }

    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            $record = new LessonRecord();
        } else {
            $record = LessonRecord::findOne($this->id);
            if (!$record) {
                throw new InvalidConfigException("Invalid lesson ID: {$this->id}");
            }
        }

        $record->id = $this->id;
        $record->courseId = $this->courseId;
        $record->lessonType = $this->lessonType;
        $record->estimatedDuration = $this->estimatedDuration;
        $record->sortOrder = $this->sortOrder;
        $record->prerequisiteLessonId = $this->prerequisiteLessonId;
        $record->dripDays = $this->dripDays;
        $record->isFree = $this->isFree;

        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        $record = LessonRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }
}
