<?php

/**
 * Update Automate plugin for WordPress
 *
 * @package   updateautomate
 * @link      https://github.com/quentin-ld/update-automate/
 * @author    Quentin Le Duff
 * @copyright 2024-2025 Quentin Le Duff
 * @license   GPL v2 or later
 *
 * Plugin Name: Update Automate
 * Description: Manage your WordPress updates with confidence. Control auto-updates, capture technical logs, and route alerts to the right places.
 * Version: 1.0
 * Plugin URI: https://wordpress.org/plugins/update-automate/
 * Author: Quentin Le Duff
 * Author URI: https://profiles.wordpress.org/quentinldd/
 * Text Domain: update-automate
 * Domain Path: /languages/
 * Requires at least: 6.2
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html/
 * License: GPL v2 or later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Plugin version (must match Version header above; used for DB schema version). */
define('UPDATEAUTOMATE_VERSION', '1.0');

define('updateautomate_PLUGIN_FILE', __FILE__);
define('updateautomate_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once __DIR__ . '/inc/core/constants.php';
require_once __DIR__ . '/inc/classes/Bootstrap.php';

require_once __DIR__ . '/inc/admin/enqueue.php';
require_once __DIR__ . '/inc/admin/links.php';
require_once __DIR__ . '/inc/admin/menu.php';
require_once __DIR__ . '/inc/settings/options.php';

add_action('plugins_loaded', ['UpdateAutomate_Bootstrap', 'init']);

register_activation_hook(__FILE__, 'updateautomate_activate');

/**
 * Create log table and schedule cron on activation.
 *
 * @return void
 */
function updateautomate_activate(): void {
    require_once __DIR__ . '/inc/classes/Database.php';
    UpdateAutomate_Database::create_table();
    require_once __DIR__ . '/inc/classes/Cron.php';
    UpdateAutomate_Cron::schedule_if_needed();
}

register_deactivation_hook(__FILE__, 'updateautomate_deactivate');

/**
 * Unschedule cron on deactivation. The log table is kept.
 *
 * @return void
 */
function updateautomate_deactivate(): void {
    require_once __DIR__ . '/inc/classes/Cron.php';
    UpdateAutomate_Cron::unschedule();
}
