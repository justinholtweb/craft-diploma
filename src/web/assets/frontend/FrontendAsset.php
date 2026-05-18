<?php

namespace justinholtweb\diploma\web\assets\frontend;

use craft\helpers\UrlHelper;
use craft\web\AssetBundle;
use craft\web\View;

class FrontendAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__;
        $this->css = ['css/diploma.css'];
        $this->js = ['js/progress.js', 'js/quiz.js'];

        parent::init();
    }

    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        $urls = [
            'completeLesson' => UrlHelper::actionUrl('diploma/progress/complete-lesson'),
            'submitQuiz' => UrlHelper::actionUrl('diploma/quiz-taking/submit'),
            'enroll' => UrlHelper::actionUrl('diploma/progress/enroll'),
            'courseProgress' => UrlHelper::actionUrl('diploma/progress/course-progress'),
        ];

        $view->registerJs('window.DiplomaUrls = ' . json_encode($urls) . ';', View::POS_HEAD);
    }
}
