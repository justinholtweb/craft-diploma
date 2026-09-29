<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\elements\Lesson;
use justinholtweb\diploma\models\Edition;
use justinholtweb\diploma\Plugin;
use yii\web\Response;

class SettingsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('diploma:manageSettings');

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('diploma/settings/_index', [
            'settings' => Plugin::getInstance()->getSettings(),
            'plugin' => Plugin::getInstance(),
        ]);
    }

    public function actionCertificates(): Response
    {
        return $this->renderTemplate('diploma/settings/_certificates', [
            'settings' => Plugin::getInstance()->getSettings(),
        ]);
    }

    public function actionCommerce(): Response
    {
        Edition::requiresPro('Commerce integration');

        return $this->renderTemplate('diploma/settings/_commerce', [
            'settings' => Plugin::getInstance()->getSettings(),
        ]);
    }

    public function actionHeadcount(): Response
    {
        Edition::requiresPro('Headcount integration');

        return $this->renderTemplate('diploma/settings/_headcount', [
            'settings' => Plugin::getInstance()->getSettings(),
        ]);
    }

    public function actionLessonFields(): Response
    {
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Lesson::class);

        return $this->renderTemplate('diploma/settings/_lesson-fields', [
            'fieldLayout' => $fieldLayout,
        ]);
    }

    public function actionSaveLessonFieldLayout(): ?Response
    {
        $this->requirePostRequest();
        // Admins only: a field layout decides what every lesson can hold. Not
        // requireAdminChanges — the lesson layout lives in the database, not project config,
        // so it must stay editable where admin changes are off.
        $this->requireAdmin(false);

        $fieldsService = Craft::$app->getFields();
        $fieldLayout = $fieldsService->assembleLayoutFromPost();
        $fieldLayout->type = Lesson::class;

        if (!$fieldsService->saveLayout($fieldLayout)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t save lesson fields.'));
            Craft::$app->getUrlManager()->setRouteParams(['fieldLayout' => $fieldLayout]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Lesson fields saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        // Plugin settings are project config. Only admins may change them, and only where admin
        // changes are allowed — otherwise a user with diploma:manageSettings could rewrite
        // Commerce auto-enrolment on production, or drift it from the deployed config.
        $this->requireAdmin(true);

        $posted = Craft::$app->getRequest()->getBodyParam('settings', []);
        $plugin = Plugin::getInstance();

        // Each settings page posts only its own fields, and savePluginSettings() writes exactly
        // the keys it is given to project config — so saving the Certificates page used to
        // erase the Headcount plan mapping and every other page's settings. Merge the posted
        // values over the current ones and save the lot.
        $settings = array_merge($plugin->getSettings()->toArray(), is_array($posted) ? $posted : []);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t save settings.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
