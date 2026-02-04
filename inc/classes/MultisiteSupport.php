<?php

/**
 * Adds functionality for logging across multisite installations.
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multisite support: create table per site, switch context for logging.
 */
final class UpdatesControl_MultisiteSupport {
    /**
     * Register multisite hooks.
     *
     * @return void
     */
    public static function register(): void {
        add_action('wp_initialize_site', [self::class, 'on_site_created'], 10, 1);
        add_filter('wpmu_drop_tables', [self::class, 'drop_tables_on_site_delete'], 10, 2);
    }

    /**
     * When a new site is created, create the log table for it.
     *
     * @param WP_Site $site New site object.
     * @return void
     */
    public static function on_site_created(WP_Site $site): void {
        if (!function_exists('switch_to_blog')) {
            return;
        }

        switch_to_blog((int) $site->blog_id);
        UpdatesControl_Database::create_table();
        restore_current_blog();
    }

    /**
     * Drop plugin tables when a site is deleted (multisite).
     *
     * @param array<string> $tables  Tables to drop.
     * @param int           $site_id Site ID.
     * @return array<string>
     */
    public static function drop_tables_on_site_delete(array $tables, int $site_id): array {
        if (!function_exists('switch_to_blog')) {
            return $tables;
        }

        switch_to_blog($site_id);
        $table_name = UpdatesControl_Database::get_table_name();
        restore_current_blog();

        $tables[] = $table_name;

        return $tables;
    }

    /**
     * Get all site IDs for log filtering (network admin).
     *
     * @return array<int>
     */
    public static function get_site_ids(): array {
        if (!is_multisite()) {
            return [1];
        }

        $sites = get_sites(['fields' => 'ids', 'number' => 10000]);

        return array_map('intval', $sites);
    }
}
