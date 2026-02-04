<?php
/**
 * Plugin path constants (fallback when not loaded from main plugin file).
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
