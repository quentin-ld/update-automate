<?php
/**
 * Plugin path constants.
 *
 * Loading order: the main plugin file (update-automate.php) defines
 * updateautomate_PLUGIN_FILE and updateautomate_PLUGIN_DIR first, then
 * requires this file. The block below is a fallback when this file is
 * loaded in isolation (e.g. from a context that did not load the main file).
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('updateautomate_PLUGIN_FILE')) {
    $updateautomate_plugin_file = dirname(__DIR__, 2) . '/update-automate.php';
    define('updateautomate_PLUGIN_FILE', is_file($updateautomate_plugin_file) ? $updateautomate_plugin_file : __FILE__);
}

if (!defined('updateautomate_PLUGIN_DIR')) {
    define('updateautomate_PLUGIN_DIR', plugin_dir_path(updateautomate_PLUGIN_FILE));
}
