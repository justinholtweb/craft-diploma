<?php

namespace justinholtweb\diploma\models;

use Craft;
use justinholtweb\diploma\Plugin;
use yii\base\InvalidConfigException;

class Edition
{
    public static function isPro(): bool
    {
        return Plugin::getInstance()->is(Plugin::EDITION_PRO);
    }

    public static function isLite(): bool
    {
        return Plugin::getInstance()->is(Plugin::EDITION_LITE);
    }

    public static function requiresPro(string $feature = ''): void
    {
        if (!self::isPro()) {
            $message = $feature
                ? Craft::t('diploma', '{feature} requires Diploma Pro.', ['feature' => $feature])
                : Craft::t('diploma', 'This feature requires Diploma Pro.');
            throw new InvalidConfigException($message);
        }
    }
}
