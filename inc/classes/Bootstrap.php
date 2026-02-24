<?php

/**
 * Plugin bootstrap: loads classes and registers hooks.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps the Update Automate plugin.
 */
final class UpdateAutomate_Bootstrap {
    /**
     * Initialize the plugin: load classes and register hooks.
     *
     * @return void
     */
    public static function init(): void {
        self::load_classes();
        self::on_activation_create_table();
        UpdateAutomate_Cron::register();
        UpdateAutomate_Update_Logger::register();
        UpdateAutomate_ErrorHandler::register();
        UpdateAutomate_Settings::register();
        UpdateAutomate_Notifications::register();
        UpdateAutomate_MailObserver::register();
        UpdateAutomate_AutoUpdates::register();
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
            'UpdateLogger.php',
            'Notifications.php',
            'MailObserver.php',
            'Settings.php',
            'AutoUpdates.php',
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
        $version = get_option(UpdateAutomate_Database::OPTION_DB_VERSION, '');
        if ($version === UpdateAutomate_Database::DB_VERSION && UpdateAutomate_Database::table_exists()) {
            return;
        }

        UpdateAutomate_Database::create_table();
    }
}
