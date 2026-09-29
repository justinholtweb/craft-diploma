<?php
/**
 * Reads or writes Diploma's plugin settings in project config from a fresh process — the
 * harness process that just watched a web request change them holds a stale copy.
 *
 *     php _pc.php get
 *     php _pc.php set '<json>'
 */
$root = getcwd();
require $root . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

$pc = Craft::$app->getProjectConfig();

if (($argv[1] ?? '') === 'set') {
    $value = json_decode($argv[2], true);

    // No settings stored before (the plugin was running on its defaults) means remove the key —
    // setting an empty array is a no-op in project config.
    if (empty($value)) {
        $pc->remove('plugins.diploma.settings');
    } else {
        $pc->set('plugins.diploma.settings', $value);
    }

    $pc->flush();
}

echo json_encode($pc->get('plugins.diploma.settings'));
