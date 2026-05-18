<?php

namespace justinholtweb\diploma\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

class CpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__;
        $this->depends = [CraftCpAsset::class];
        $this->css = ['css/diploma-cp.css'];
        $this->js = [
            'js/quiz-builder.js',
            'js/course-editor.js',
            'js/analytics.js',
        ];

        parent::init();
    }
}
