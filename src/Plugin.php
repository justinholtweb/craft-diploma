<?php

namespace justinholtweb\diploma;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use justinholtweb\diploma\elements\Course;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\elements\Quiz;
use justinholtweb\diploma\models\Settings;
use justinholtweb\diploma\services\Certificates;
use justinholtweb\diploma\services\CommerceIntegration;
use justinholtweb\diploma\services\Courses;
use justinholtweb\diploma\services\DripContent;
use justinholtweb\diploma\services\Enrollments;
use justinholtweb\diploma\services\HeadcountIntegration;
use justinholtweb\diploma\services\Lessons;
use justinholtweb\diploma\services\ProgressTracker;
use justinholtweb\diploma\services\QuizGrader;
use justinholtweb\diploma\services\Quizzes;
use justinholtweb\diploma\services\Reporting;
use justinholtweb\diploma\twig\DiplomaTwigExtension;
use justinholtweb\diploma\variables\DiplomaVariable;
use justinholtweb\diploma\widgets\CourseOverviewWidget;
use justinholtweb\diploma\widgets\EnrollmentWidget;
use yii\base\Event;

/**
 * Diploma — LMS for Craft CMS
 *
 * @property Courses $courses
 * @property Lessons $lessons
 * @property Quizzes $quizzes
 * @property Enrollments $enrollments
 * @property ProgressTracker $progressTracker
 * @property QuizGrader $quizGrader
 * @property Certificates $certificates
 * @property DripContent $dripContent
 * @property CommerceIntegration $commerceIntegration
 * @property HeadcountIntegration $headcountIntegration
 * @property Reporting $reporting
 * @property Settings $settings
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'courses' => Courses::class,
                'lessons' => Lessons::class,
                'quizzes' => Quizzes::class,
                'enrollments' => Enrollments::class,
                'progressTracker' => ProgressTracker::class,
                'quizGrader' => QuizGrader::class,
                'certificates' => Certificates::class,
                'dripContent' => DripContent::class,
                'commerceIntegration' => CommerceIntegration::class,
                'headcountIntegration' => HeadcountIntegration::class,
                'reporting' => Reporting::class,
            ],
        ];
    }

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function () {
            $this->registerElementTypes();
            $this->registerCpRoutes();
            $this->registerSiteRoutes();
            $this->registerPermissions();
            $this->registerTemplateVariable();
            $this->registerWidgets();
            $this->registerTwigExtension();
        });
    }

    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $nav['label'] = 'Diploma';

        $nav['subnav'] = [];

        if (Craft::$app->getUser()->checkPermission('diploma:accessPlugin')) {
            $nav['subnav']['dashboard'] = [
                'label' => Craft::t('diploma', 'Dashboard'),
                'url' => 'diploma',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('diploma:manageCourses') ||
            Craft::$app->getUser()->checkPermission('diploma:accessPlugin')) {
            $nav['subnav']['courses'] = [
                'label' => Craft::t('diploma', 'Courses'),
                'url' => 'diploma/courses',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('diploma:manageQuizzes') ||
            Craft::$app->getUser()->checkPermission('diploma:accessPlugin')) {
            $nav['subnav']['quizzes'] = [
                'label' => Craft::t('diploma', 'Quizzes'),
                'url' => 'diploma/quizzes',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('diploma:manageEnrollments') ||
            Craft::$app->getUser()->checkPermission('diploma:accessPlugin')) {
            $nav['subnav']['enrollments'] = [
                'label' => Craft::t('diploma', 'Enrollments'),
                'url' => 'diploma/enrollments',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('diploma:viewCertificates') ||
            Craft::$app->getUser()->checkPermission('diploma:accessPlugin')) {
            $nav['subnav']['certificates'] = [
                'label' => Craft::t('diploma', 'Certificates'),
                'url' => 'diploma/certificates',
            ];
        }

        if ($this->is(self::EDITION_PRO) &&
            Craft::$app->getUser()->checkPermission('diploma:viewAnalytics')) {
            $nav['subnav']['analytics'] = [
                'label' => Craft::t('diploma', 'Analytics'),
                'url' => 'diploma/analytics',
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin() ||
            Craft::$app->getUser()->checkPermission('diploma:manageSettings')) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('diploma', 'Settings'),
                'url' => 'diploma/settings',
            ];
        }

        return $nav;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('diploma/settings/_fields', [
            'settings' => $this->getSettings(),
            'plugin' => $this,
        ]);
    }

    private function registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Course::class;
                $event->types[] = Lesson::class;
                $event->types[] = Quiz::class;
            }
        );
    }

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                // Dashboard
                $event->rules['diploma'] = 'diploma/dashboard/index';

                // Courses
                $event->rules['diploma/courses'] = 'diploma/courses/index';
                $event->rules['diploma/courses/new'] = 'diploma/courses/edit';
                $event->rules['diploma/courses/<courseId:\d+>'] = 'diploma/courses/edit';

                // Lessons (nested under courses)
                $event->rules['diploma/courses/<courseId:\d+>/lessons'] = 'diploma/lessons/index';
                $event->rules['diploma/courses/<courseId:\d+>/lessons/new'] = 'diploma/lessons/edit';
                $event->rules['diploma/courses/<courseId:\d+>/lessons/<lessonId:\d+>'] = 'diploma/lessons/edit';

                // Quizzes
                $event->rules['diploma/quizzes'] = 'diploma/quizzes/index';
                $event->rules['diploma/quizzes/new'] = 'diploma/quizzes/edit';
                $event->rules['diploma/quizzes/<quizId:\d+>'] = 'diploma/quizzes/edit';

                // Enrollments
                $event->rules['diploma/enrollments'] = 'diploma/enrollments/index';
                $event->rules['diploma/enrollments/<enrollmentId:\d+>'] = 'diploma/enrollments/detail';

                // Certificates
                $event->rules['diploma/certificates'] = 'diploma/certificates/index';

                // Analytics (Pro)
                $event->rules['diploma/analytics'] = 'diploma/dashboard/analytics';

                // Settings
                $event->rules['diploma/settings'] = 'diploma/settings/index';
                $event->rules['diploma/settings/certificates'] = 'diploma/settings/certificates';
                $event->rules['diploma/settings/commerce'] = 'diploma/settings/commerce';
                $event->rules['diploma/settings/headcount'] = 'diploma/settings/headcount';
            }
        );
    }

    private function registerSiteRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                // Front-end API
                $event->rules['diploma/api/enroll'] = 'diploma/progress/enroll';
                $event->rules['diploma/api/complete-lesson'] = 'diploma/progress/complete-lesson';
                $event->rules['diploma/api/submit-quiz'] = 'diploma/quiz-taking/submit';
                $event->rules['diploma/api/progress/<courseId:\d+>'] = 'diploma/progress/course-progress';

                // Certificate verification (public)
                $event->rules['diploma/certificate/verify/<code:\w+>'] = 'diploma/certificates/verify';
                $event->rules['diploma/certificate/download/<code:\w+>'] = 'diploma/certificates/download';
            }
        );
    }

    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('diploma', 'Diploma'),
                    'permissions' => [
                        'diploma:accessPlugin' => [
                            'label' => Craft::t('diploma', 'Access Diploma'),
                        ],
                        'diploma:manageCourses' => [
                            'label' => Craft::t('diploma', 'Manage courses'),
                            'nested' => [
                                'diploma:deleteCourses' => [
                                    'label' => Craft::t('diploma', 'Delete courses'),
                                ],
                            ],
                        ],
                        'diploma:manageQuizzes' => [
                            'label' => Craft::t('diploma', 'Manage quizzes'),
                            'nested' => [
                                'diploma:deleteQuizzes' => [
                                    'label' => Craft::t('diploma', 'Delete quizzes'),
                                ],
                            ],
                        ],
                        'diploma:manageEnrollments' => [
                            'label' => Craft::t('diploma', 'Manage enrollments'),
                        ],
                        'diploma:viewCertificates' => [
                            'label' => Craft::t('diploma', 'View certificates'),
                        ],
                        'diploma:viewAnalytics' => [
                            'label' => Craft::t('diploma', 'View analytics'),
                        ],
                        'diploma:manageSettings' => [
                            'label' => Craft::t('diploma', 'Manage settings'),
                        ],
                    ],
                ];
            }
        );
    }

    private function registerTemplateVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $event->sender->set('diploma', DiplomaVariable::class);
            }
        );
    }

    private function registerWidgets(): void
    {
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CourseOverviewWidget::class;
                $event->types[] = EnrollmentWidget::class;
            }
        );
    }

    private function registerTwigExtension(): void
    {
        Craft::$app->getView()->registerTwigExtension(new DiplomaTwigExtension());
    }
}
