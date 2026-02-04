<?php

/**
 * Creates and manages the custom database table for update logs.
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database table manager for update logs.
 */
final class UpdatesControl_Database {
    /**
     * Option key storing the database version for upgrades.
     *
     * @var string
     */
    public const OPTION_DB_VERSION = 'updatescontrol_log_db_version';

    /**
     * Current schema version.
     *
     * @var string
     */
    public const DB_VERSION = '1.1';

    /**
     * Table name (without prefix).
     *
     * @var string
     */
    public const TABLE_LOGS = 'updatescontrol_logs';

    /**
     * Get full table name including prefix (and blog prefix on multisite).
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_LOGS;
    }

    /**
     * Create or update the logs table.
     *
     * @return bool True on success, false on failure.
     */
    public static function create_table(): bool {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) unsigned NOT NULL DEFAULT 1,
            log_type varchar(20) NOT NULL DEFAULT 'plugin',
            action_type varchar(20) NOT NULL DEFAULT 'update',
            item_name varchar(255) NOT NULL DEFAULT '',
            item_slug varchar(255) NOT NULL DEFAULT '',
            version_before varchar(64) NOT NULL DEFAULT '',
            version_after varchar(64) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'success',
            message text DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            performed_by varchar(20) NOT NULL DEFAULT 'system',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY log_type (log_type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);

        return true;
    }

    /**
     * Drop the logs table (used on uninstall).
     *
     * Uses wpdb::prepare() with %i (identifier) for the table name (WP 6.2+).
     *
     * @return void
     */
    public static function drop_table(): void {
        global $wpdb;

        $table_name = self::get_table_name();
        // Plugin Check false positive: required on uninstall. No WordPress API for dropping custom tables. Table name from get_table_name() (prefix + constant); passed to prepare() %i.
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table_name));
        delete_option(self::OPTION_DB_VERSION);
    }

    /**
     * Check if the table exists.
     *
     * Uses the option set by create_table() / cleared by drop_table() to avoid
     * direct information_schema queries. No database read required.
     *
     * @return bool
     */
    public static function table_exists(): bool {
        return get_option(self::OPTION_DB_VERSION, false) !== false;
    }
}
