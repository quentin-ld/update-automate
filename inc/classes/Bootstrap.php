<?php

/**
 * Plugin bootstrap: loads classes and registers hooks.
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps the Updates Control plugin.
 */
final class UpdatesControl_Bootstrap {
    /**
     * Initialize the plugin: load classes and register hooks.
     *
     * @return void
     */
    public static function init(): void {
        self::load_classes();
        self::on_activation_create_table();
        UpdatesControl_Cron::register();
        UpdatesControl_Update_Manager::register();
        UpdatesControl_ErrorHandler::register();
        UpdatesControl_Settings::register();
        UpdatesControl_Notifications::register();
    }

    /**
     * Load class files. Bootstrap itself is loaded by the main plugin file.
     *
     * @return void
     */
    private static function load_classes(): void {
        $dir = __DIR__;
        $classes = [
            'Database.php',
            'Security.php',
            'Logger.php',
            'Cron.php',
            'ErrorHandler.php',
            'UpdateManager.php',
            'Notifications.php',
            'Settings.php',
        ];
        foreach ($classes as $file) {
            $path = $dir . '/' . $file;
            if (is_file($path)) {
                require_once $path;
            }
        }
    }

    /**
     * Ensure log table exists (on init, after plugins loaded).
     *
     * @return void
     */
    private static function on_activation_create_table(): void {
        $version = get_option(UpdatesControl_Database::OPTION_DB_VERSION, '');
        if ($version === UpdatesControl_Database::DB_VERSION && UpdatesControl_Database::table_exists()) {
            return;
        }

        UpdatesControl_Database::create_table();
    }
}
