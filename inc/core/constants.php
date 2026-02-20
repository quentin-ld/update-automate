<?php
/**
 * Plugin path constants.
 *
 * Loading order: the main plugin file (updates-control.php) defines
 * updatescontrol_PLUGIN_FILE and updatescontrol_PLUGIN_DIR first, then
 * requires this file. The block below is a fallback when this file is
 * loaded in isolation (e.g. from a context that did not load the main file).
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('updatescontrol_PLUGIN_FILE')) {
    $updatescontrol_plugin_file = dirname(__DIR__, 2) . '/updates-control.php';
    define('updatescontrol_PLUGIN_FILE', is_file($updatescontrol_plugin_file) ? $updatescontrol_plugin_file : __FILE__);
}

if (!defined('updatescontrol_PLUGIN_DIR')) {
    define('updatescontrol_PLUGIN_DIR', plugin_dir_path(updatescontrol_PLUGIN_FILE));
}
