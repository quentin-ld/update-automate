<?php

/**
 * Integrates with WordPress to manage automatic updates and log actions.
 *
 * This class does not modify or block updates; it only reads/adds version_before
 * to transients for logging purposes when updates complete.
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks into WordPress update process and logs core/plugin/theme updates.
 */
final class UpdatesControl_Updater {
    /**
     * Register hooks for update events.
     *
     * @return void
     */
    public static function register(): void {
        add_action('upgrader_process_complete', [self::class, 'on_upgrader_process_complete'], 10, 2);
        add_filter('upgrader_pre_install', [self::class, 'store_core_version_before'], 5, 2);
        // Plugin Check false positive: we do not implement a custom updater or block updates. We only add version_before to the transient for audit logging when updates complete; core update flow is unchanged.
        add_filter('set_site_transient_update_plugins', [self::class, 'capture_plugin_versions_before'], 10, 1);
        add_filter('set_site_transient_update_themes', [self::class, 'capture_theme_versions_before'], 10, 1);
    }

    /**
     * Store current WordPress version before core upgrade runs.
     *
     * @param bool $result Filter result.
     * @param array<string, mixed> $hook_extra Extra args (may contain 'type' => 'core').
     * @return bool
     */
    public static function store_core_version_before($result, array $hook_extra = []) {
        if (($hook_extra['type'] ?? '') === 'core') {
            update_option('updatescontrol_core_version_before', get_bloginfo('version'));
        }

        return $result;
    }

    /**
     * Store current plugin versions before updates (to get version_before).
     *
     * @param mixed $value Transient value.
     * @return mixed
     */
    public static function capture_plugin_versions_before($value) {
        if (!is_object($value) || !isset($value->response) || !is_array($value->response)) {
            return $value;
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        foreach (array_keys($value->response) as $file) {
            if (isset($all_plugins[$file]['Version'])) {
                if (!isset($value->response[$file]->version_before)) {
                    $value->response[$file]->version_before = $all_plugins[$file]['Version'];
                }
            }
        }

        return $value;
    }

    /**
     * Store current theme versions before updates.
     *
     * @param mixed $value Transient value.
     * @return mixed
     */
    public static function capture_theme_versions_before($value) {
        if (!is_object($value) || !isset($value->response) || !is_array($value->response)) {
            return $value;
        }

        $themes = wp_get_themes();
        foreach (array_keys($value->response) as $slug) {
            if (isset($themes[$slug]) && $themes[$slug]->get('Version')) {
                if (!isset($value->response[$slug]->version_before)) {
                    $value->response[$slug]->version_before = $themes[$slug]->get('Version');
                }
            }
        }

        return $value;
    }

    /**
     * Fired when an update process completes.
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array<string, mixed> $options Array of item type, action, etc.
     * @return void
     */
    public static function on_upgrader_process_complete(WP_Upgrader $upgrader, array $options): void {
        if (!get_option('updatescontrol_logging_enabled', true)) {
            return;
        }

        $type = $options['type'] ?? '';
        $action = $options['action'] ?? '';

        if ($type === 'core' && $action === 'update') {
            self::log_core_update();

            return;
        }

        if ($type === 'plugin' && isset($options['plugins']) && is_array($options['plugins'])) {
            foreach ($options['plugins'] as $plugin_file) {
                self::log_plugin_update($plugin_file, $action, $upgrader);
            }
        }

        if ($type === 'theme' && isset($options['themes']) && is_array($options['themes'])) {
            foreach ($options['themes'] as $theme_slug) {
                self::log_theme_update($theme_slug, $action, $upgrader);
            }
        }
    }

    /**
     * Log WordPress core update.
     *
     * @return void
     */
    private static function log_core_update(): void {
        $version_before = get_option('updatescontrol_core_version_before', '');
        $version_after = get_bloginfo('version');

        UpdatesControl_Logger::log(
            'core',
            'update',
            'WordPress',
            'core',
            $version_before,
            $version_after,
            'success',
            ''
        );

        delete_option('updatescontrol_core_version_before');
    }

    /**
     * Log plugin update/install.
     *
     * @param string       $plugin_file Plugin file path.
     * @param string       $action      update or install.
     * @param WP_Upgrader  $upgrader    Upgrader instance.
     * @return void
     */
    private static function log_plugin_update(string $plugin_file, string $action, WP_Upgrader $upgrader): void {
        $version_before = '';
        $version_after = '';
        $name = $plugin_file;

        if (function_exists('get_plugins')) {
            $all = get_plugins();
            if (isset($all[$plugin_file])) {
                $name = $all[$plugin_file]['Name'] ?? $plugin_file;
                $version_before = $all[$plugin_file]['Version'] ?? '';
            }
        }

        if (function_exists('get_plugin_data')) {
            $path = WP_PLUGIN_DIR . '/' . $plugin_file;
            if (file_exists($path)) {
                $data = get_plugin_data($path, false, false);
                $version_after = $data['Version'];
            }
        }

        $slug = dirname($plugin_file);
        if ($slug === '.') {
            $slug = $plugin_file;
        }

        UpdatesControl_Logger::log(
            'plugin',
            $action === 'install' ? 'install' : 'update',
            $name,
            $slug,
            $version_before,
            $version_after,
            'success',
            ''
        );
    }

    /**
     * Log theme update/install.
     *
     * @param string      $theme_slug Theme slug.
     * @param string      $action    update or install.
     * @param WP_Upgrader $upgrader  Upgrader instance.
     * @return void
     */
    private static function log_theme_update(string $theme_slug, string $action, WP_Upgrader $upgrader): void {
        $version_before = '';
        $version_after = '';
        $name = $theme_slug;

        $themes = wp_get_themes();
        if (isset($themes[$theme_slug])) {
            $name = $themes[$theme_slug]->get('Name') ?: $theme_slug;
            $version_before = $themes[$theme_slug]->get('Version') ?: '';
        }

        if (isset($themes[$theme_slug])) {
            $version_after = $themes[$theme_slug]->get('Version') ?: '';
        }

        UpdatesControl_Logger::log(
            'theme',
            $action === 'install' ? 'install' : 'update',
            $name,
            $theme_slug,
            $version_before,
            $version_after,
            'success',
            ''
        );
    }
}
