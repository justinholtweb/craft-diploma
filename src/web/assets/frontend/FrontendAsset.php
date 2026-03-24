<?php

namespace justinholtweb\diploma\web\assets\frontend;

use craft\web\AssetBundle;

class FrontendAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__;
        $this->css = ['css/diploma.css'];
        $this->js = ['js/progress.js'];

        parent::init();
    }
}
