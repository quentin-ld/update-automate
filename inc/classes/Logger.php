<?php

/**
 * Responsible for logging functionalities with specified fields.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create, read, and delete update logs.
 */
final class UpdateAutomate_Logger {
    /**
     * Insert a log entry.
     *
     * @param string $log_type       One of: core, plugin, theme, translation.
     * @param string $action_type    One of: update, downgrade, install, same_version, failed, uninstall.
     * @param string $item_name      Display name of the item.
     * @param string $item_slug      Slug/identifier.
     * @param string $version_before Previous version.
     * @param string $version_after  New version after update.
     * @param string $status         success, error, cancelled.
     * @param string $message        Optional message (e.g. process log, error details).
     * @param string $trace          Optional call stack trace.
     * @param string $performed_as   manual, automatic, or upload.
     * @param string $update_context bulk, single, or '' (for core/translation/legacy).
     * @return int|false Log ID on success, false on failure.
     */
    public static function log(
        string $log_type = 'plugin',
        string $action_type = 'update',
        string $item_name = '',
        string $item_slug = '',
        string $version_before = '',
        string $version_after = '',
        string $status = 'success',
        string $message = '',
        string $trace = '',
        string $performed_as = 'manual',
        string $update_context = ''
    ): int|false {
        if (!UpdateAutomate_Database::table_exists()) {
            return false;
        }

        $log_type = UpdateAutomate_Security::sanitize_log_type($log_type);
        $action_type = UpdateAutomate_Security::sanitize_action_type($action_type);
        $item_name = UpdateAutomate_Security::sanitize_string($item_name, 255);
        $item_slug = UpdateAutomate_Security::sanitize_string($item_slug, 255);
        $version_before = UpdateAutomate_Security::sanitize_version($version_before);
        $version_after = UpdateAutomate_Security::sanitize_version($version_after);
        $status = UpdateAutomate_Security::sanitize_status($status);
        $message = UpdateAutomate_Security::sanitize_message($message);
        $trace = UpdateAutomate_Security::sanitize_trace($trace);
        $performed_as = UpdateAutomate_Security::sanitize_performed_as($performed_as);
        $update_context = UpdateAutomate_Security::sanitize_update_context($update_context);

        $site_id = (int) get_current_blog_id();
        $user_id = (int) get_current_user_id();
        $performed_by = $user_id > 0 ? 'user' : 'system';

        global $wpdb;
        $table = UpdateAutomate_Database::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; $wpdb->insert is the correct API, prepared internally.
        $result = $wpdb->insert(
            $table,
            [
                'site_id' => $site_id,
                'log_type' => $log_type,
                'action_type' => $action_type,
                'item_name' => $item_name,
                'item_slug' => $item_slug,
                'version_before' => $version_before,
                'version_after' => $version_after,
                'status' => $status,
                'message' => $message,
                'trace' => $trace,
                'user_id' => $user_id,
                'performed_by' => $performed_by,
                'performed_as' => $performed_as,
                'update_context' => $update_context,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        $log_id = (int) $wpdb->insert_id;
        $data = [
            'log_type' => $log_type,
            'action_type' => $action_type,
            'item_name' => $item_name,
            'item_slug' => $item_slug,
            'version_before' => $version_before,
            'version_after' => $version_after,
            'status' => $status,
            'message' => $message,
            'trace' => $trace,
            'update_context' => $update_context,
            'created_at' => current_time('mysql'),
        ];
        do_action('updateautomate_after_log', $log_id, $data);

        return $log_id;
    }

    /**
     * Build WHERE clause and placeholder values for log queries.
     *
     * @param array<string, mixed> $args Keys: site_id, log_type, status, performed_as.
     * @return array{where_sql: string, values: array<int, int|string>}
     */
    private static function build_logs_where(array $args): array {
        $where = ['1=1'];
        $values = [];
        $site_id = $args['site_id'] ?? null;
        if ($site_id !== null) {
            $where[] = 'site_id = %d';
            $values[] = (int) $site_id;
        }
        $log_type = $args['log_type'] ?? null;
        if ($log_type !== null && $log_type !== '') {
            $where[] = 'log_type = %s';
            $values[] = UpdateAutomate_Security::sanitize_log_type((string) $log_type);
        }
        $status = $args['status'] ?? null;
        if ($status !== null && $status !== '') {
            $where[] = 'status = %s';
            $values[] = UpdateAutomate_Security::sanitize_status((string) $status);
        }
        $performed_as = $args['performed_as'] ?? null;
        if ($performed_as !== null && $performed_as !== '') {
            $where[] = 'performed_as = %s';
            $values[] = UpdateAutomate_Security::sanitize_performed_as((string) $performed_as);
        }

        return ['where_sql' => implode(' AND ', $where), 'values' => $values];
    }

    /**
     * Get logs with optional filters and pagination.
     *
     * @param array<string, mixed> $args Optional. site_id, log_type, status, performed_as, per_page, page, orderby, order.
     * @return array<int, object> Array of log row objects.
     */
    public static function get_logs(array $args = []): array {
        if (!UpdateAutomate_Database::table_exists()) {
            return [];
        }

        global $wpdb;
        $table = UpdateAutomate_Database::get_table_name();

        $defaults = [
            'site_id' => null,
            'log_type' => null,
            'status' => null,
            'performed_as' => null,
            'per_page' => 50,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);

        $built = self::build_logs_where($args);
        $orderby = in_array($args['orderby'], ['id', 'created_at', 'log_type', 'status', 'item_name', 'performed_as'], true)
            ? $args['orderby']
            : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $per_page = max(1, min(200, (int) $args['per_page']));
        $offset = max(0, ((int) $args['page']) - 1) * $per_page;

        $values = array_merge([$table], $built['values'], [$orderby, $per_page, $offset]);

        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table/orderby via %i; user input only in $values.
        $prepared = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql and $order are whitelisted (literal fragments and ASC/DESC).
            "SELECT * FROM %i WHERE {$built['where_sql']} ORDER BY %i {$order} LIMIT %d OFFSET %d",
            $values
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $prepared from prepare() above; user input only in bound $values.
        $results = $wpdb->get_results($prepared);

        return is_array($results) ? $results : [];
    }

    /**
     * Get total count of logs matching filters.
     *
     * @param array<string, mixed> $args Optional. site_id, log_type, status, performed_as.
     * @return int
     */
    public static function get_logs_count(array $args = []): int {
        if (!UpdateAutomate_Database::table_exists()) {
            return 0;
        }

        global $wpdb;
        $table = UpdateAutomate_Database::get_table_name();

        $built = self::build_logs_where($args);
        $values = array_merge([$table], $built['values']);

        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table via %i; user input only in $values.
        $prepared = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- where_sql is whitelisted literal fragments only.
            "SELECT COUNT(*) FROM %i WHERE {$built['where_sql']}",
            $values
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $prepared from prepare() above; user input only in bound $values.
        return (int) $wpdb->get_var($prepared);
    }

    /**
     * Delete a single log by ID.
     *
     * @param int $id Log ID.
     * @return bool
     */
    public static function delete_log(int $id): bool {
        if (!UpdateAutomate_Database::table_exists()) {
            return false;
        }

        global $wpdb;
        $table = UpdateAutomate_Database::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; $wpdb->delete is the correct API, prepared.
        $result = $wpdb->delete($table, ['id' => $id], ['%d']);

        return $result !== false;
    }

    /**
     * Delete logs older than the given number of days.
     *
     * @param int $days Delete logs older than this many days.
     * @return int Number of rows deleted.
     */
    public static function delete_older_than(int $days): int {
        if (!UpdateAutomate_Database::table_exists() || $days < 1) {
            return 0;
        }

        global $wpdb;
        $table = UpdateAutomate_Database::get_table_name();
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; table and date via prepare(); no WP API for bulk delete by date.
        $result = $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created_at < %s', $table, $date));

        return is_numeric($result) ? (int) $result : 0;
    }
}
