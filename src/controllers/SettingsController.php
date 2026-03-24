<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
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

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $settings = Craft::$app->getRequest()->getBodyParam('settings', []);
        $plugin = Plugin::getInstance();

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError(Craft::t('diploma', 'Couldn\'t save settings.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('diploma', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
