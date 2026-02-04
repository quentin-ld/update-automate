<?php

/**
 * Update manager: logs core/plugin/theme updates;
 *
 * This class hooks into WordPress update flow only to add version_before to transients
 * for audit logging when updates complete. It does not modify or block updates.
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Update manager: observes and logs core/plugin/theme updates.
 */
final class UpdatesControl_Update_Manager {
    /**
     * Register hooks for update events.
     *
     * @return void
     */
    public static function register(): void {
        add_action('upgrader_process_complete', [self::class, 'on_upgrader_process_complete'], 10, 2);
        add_filter('upgrader_pre_install', [self::class, 'store_core_version_before'], 5, 2);
        add_filter('upgrader_pre_download', [self::class, 'init_core_feedback_on_download'], 5, 3);
        // phpcs:ignore plugin_updater_detected, update_modification_detected -- Update manager (logs updates only); we only add version_before to the transient for audit logging. We do not implement a plugin updater or alter what gets updated.
        add_filter('set_site_transient_update_plugins', [self::class, 'capture_plugin_versions_before'], 10, 1);
        add_filter('set_site_transient_update_themes', [self::class, 'capture_theme_versions_before'], 10, 1);
    }

    private const OPTION_CORE_VERSION_BEFORE = 'updatescontrol_core_version_before';
    private const OPTION_PLUGIN_VERSIONS_BEFORE = 'updatescontrol_plugin_versions_before';
    private const OPTION_THEME_VERSIONS_BEFORE = 'updatescontrol_theme_versions_before';

    /** @var array<string> Collected core update feedback (update_feedback filter). */
    private static array $core_feedback = [];

    /** Package URL for core (to build "Downloading from..." step). */
    private static string $core_package_url = '';

    /**
     * Store current core/plugin/theme versions before upgrade runs. For core, also hook update_feedback to collect process.
     *
     * @param array<string, mixed> $hook_extra Extra args (type, plugin, theme).
     */
    public static function store_core_version_before(bool $result, array $hook_extra = []): bool {
        $type = $hook_extra['type'] ?? '';
        if ($type === 'core') {
            update_option(self::OPTION_CORE_VERSION_BEFORE, get_bloginfo('version'));
        }
        if ($type === 'plugin' && !empty($hook_extra['plugin']) && is_string($hook_extra['plugin'])) {
            $file = $hook_extra['plugin'];
            if (function_exists('get_plugins')) {
                $all = get_plugins();
                $version = isset($all[$file]['Version']) ? (string) $all[$file]['Version'] : '';
                if ($version !== '') {
                    $stored = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, []);
                    $stored[$file] = $version;
                    update_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, $stored);
                }
            }
        }
        if ($type === 'theme' && !empty($hook_extra['theme']) && is_string($hook_extra['theme'])) {
            $slug = $hook_extra['theme'];
            $themes = wp_get_themes();
            $version = isset($themes[$slug]) ? (string) $themes[$slug]->get('Version') : '';
            if ($version !== '') {
                $stored = (array) get_option(self::OPTION_THEME_VERSIONS_BEFORE, []);
                $stored[$slug] = $version;
                update_option(self::OPTION_THEME_VERSIONS_BEFORE, $stored);
            }
        }

        return $result;
    }

    /**
     * When core package is about to download, start collecting update_feedback (EUM-style; catches manual core update flow).
     *
     * @param bool|WP_Error $reply    Whether to short-circuit.
     * @param string       $package  Package URL.
     * @param WP_Upgrader  $upgrader Upgrader instance.
     * @return bool|WP_Error Unchanged.
     */
    public static function init_core_feedback_on_download($reply, string $package, WP_Upgrader $upgrader) {
        if (!$upgrader instanceof \Core_Upgrader) {
            return $reply;
        }
        update_option(self::OPTION_CORE_VERSION_BEFORE, get_bloginfo('version'));
        self::$core_feedback = [];
        self::$core_package_url = $package;
        add_filter('update_feedback', [self::class, 'collect_core_feedback'], 1, 1);

        return $reply;
    }

    /**
     * Collect core update step messages (update_feedback filter).
     *
     * @param string|WP_Error $feedback Message or error.
     * @return string|WP_Error Unchanged.
     */
    public static function collect_core_feedback($feedback) {
        if (is_string($feedback) && $feedback !== '') {
            self::$core_feedback[] = strip_tags(str_replace('&#8230;', '…', $feedback));
        }

        return $feedback;
    }

    /**
     * Store current plugin versions before updates (to get version_before).
     *
     * @param mixed $value Transient value.
     * @return mixed
     */
    public static function capture_plugin_versions_before(mixed $value): mixed {
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
    public static function capture_theme_versions_before(mixed $value): mixed {
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

        $process_message = UpdatesControl_ErrorHandler::get_skin_process_message($upgrader);
        $trace = UpdatesControl_ErrorHandler::capture_trace();

        if ($type === 'core' && $action === 'update') {
            self::log_core_update($upgrader, $process_message, $trace);

            return;
        }

        if ($type === 'plugin') {
            $plugins = isset($options['plugins']) && is_array($options['plugins'])
                ? $options['plugins']
                : (isset($options['plugin']) && is_string($options['plugin']) ? [$options['plugin']] : []);
            foreach ($plugins as $plugin_file) {
                self::log_plugin_update($plugin_file, $action, $upgrader, $process_message, $trace);
            }
        }

        if ($type === 'theme') {
            $themes = isset($options['themes']) && is_array($options['themes'])
                ? $options['themes']
                : (isset($options['theme']) && is_string($options['theme']) ? [$options['theme']] : []);
            foreach ($themes as $theme_slug) {
                self::log_theme_update($theme_slug, $action, $upgrader, $process_message, $trace);
            }
        }
    }

    /**
     * Log WordPress core update or downgrade.
     *
     * @param WP_Upgrader $upgrader        Upgrader instance (for process message).
     * @param string     $process_message Optional process log (e.g. from skin).
     * @param string     $trace           Optional call stack trace.
     * @return void
     */
    private static function log_core_update(WP_Upgrader $upgrader, string $process_message = '', string $trace = ''): void {
        remove_filter('update_feedback', [self::class, 'collect_core_feedback'], 1);

        $version_before = get_option(self::OPTION_CORE_VERSION_BEFORE, '');
        $version_after = get_bloginfo('version');
        $action_type = self::resolve_action_type($version_before, $version_after, 'update');

        $steps = self::$core_feedback;
        if (self::$core_package_url !== '') {
            array_unshift(
                $steps,
                sprintf(
                    /* translators: %s: download URL */
                    __('Downloading update from %s…', 'updates-control'),
                    self::$core_package_url
                ),
                __('Unpacking the update…', 'updates-control')
            );
        }

        $message = self::format_note_like_wp_screen(
            sprintf(
                /* translators: %s: WordPress version number */
                __('Update to WordPress %s', 'updates-control'),
                $version_after
            ),
            $steps,
            $process_message
        );

        UpdatesControl_Logger::log(
            'core',
            $action_type,
            'WordPress',
            'core',
            $version_before,
            $version_after,
            'success',
            $message,
            $trace
        );

        delete_option(self::OPTION_CORE_VERSION_BEFORE);
        self::$core_feedback = [];
        self::$core_package_url = '';
    }

    /**
     * Format note like the WordPress update process screen: title, blank line, then one step per line (EUM-style).
     *
     * @param string       $title     First line (e.g. "Update to WordPress 6.9.1").
     * @param array<string> $steps    Collected step messages.
     * @param string       $fallback  Optional extra lines (e.g. from skin get_upgrade_messages).
     * @return string
     */
    private static function format_note_like_wp_screen(string $title, array $steps, string $fallback = ''): string {
        $lines = array_filter(array_merge([$title], $steps));
        if ($fallback !== '') {
            $lines[] = '';
            $lines[] = trim($fallback);
        }

        return implode("\n", $lines);
    }

    /**
     * Resolve action type: downgrade when version went down, else update.
     *
     * @param string $version_before Previous version.
     * @param string $version_after  Current version.
     * @param string $default        Default when not a downgrade (e.g. update).
     * @return string One of: downgrade, update.
     */
    private static function resolve_action_type(string $version_before, string $version_after, string $default = 'update'): string {
        if ($version_before !== '' && $version_after !== '' && version_compare($version_after, $version_before, '<')) {
            return 'downgrade';
        }

        return $default;
    }

    /**
     * Log plugin update/install/downgrade.
     *
     * @param string       $plugin_file     Plugin file path.
     * @param string       $action          update or install.
     * @param WP_Upgrader  $upgrader        Upgrader instance.
     * @param string       $process_message Optional process log (e.g. from skin).
     * @param string       $trace           Optional call stack trace.
     * @return void
     */
    private static function log_plugin_update(string $plugin_file, string $action, WP_Upgrader $upgrader, string $process_message = '', string $trace = ''): void {
        $stored = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, []);
        $version_before = isset($stored[$plugin_file]) ? (string) $stored[$plugin_file] : '';
        $version_after = '';
        $name = $plugin_file;

        if (function_exists('get_plugins')) {
            $all = get_plugins();
            if (isset($all[$plugin_file])) {
                $name = $all[$plugin_file]['Name'] ?? $plugin_file;
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

        $action_type = $action === 'install' ? 'install' : self::resolve_action_type($version_before, $version_after, 'update');

        $title = $action === 'install'
            ? sprintf(__('Installation of %1$s %2$s', 'updates-control'), $name, $version_after ?: '')
            : sprintf(__('Update to %1$s %2$s', 'updates-control'), $name, $version_after ?: '');
        $message = self::format_note_like_wp_screen($title, [], $process_message);

        UpdatesControl_Logger::log(
            'plugin',
            $action_type,
            $name,
            $slug,
            $version_before,
            $version_after,
            'success',
            $message,
            $trace
        );

        unset($stored[$plugin_file]);
        update_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, $stored);
    }

    /**
     * Log theme update/install/downgrade.
     *
     * @param string      $theme_slug      Theme slug.
     * @param string      $action          update or install.
     * @param WP_Upgrader $upgrader        Upgrader instance.
     * @param string      $process_message Optional process log (e.g. from skin).
     * @param string      $trace           Optional call stack trace.
     * @return void
     */
    private static function log_theme_update(string $theme_slug, string $action, WP_Upgrader $upgrader, string $process_message = '', string $trace = ''): void {
        $stored = (array) get_option(self::OPTION_THEME_VERSIONS_BEFORE, []);
        $version_before = isset($stored[$theme_slug]) ? (string) $stored[$theme_slug] : '';
        $themes = wp_get_themes();
        $theme = $themes[$theme_slug] ?? null;
        $name = $theme_slug;
        $version_after = '';
        if ($theme !== null) {
            $name = $theme->get('Name') ?: $theme_slug;
            $version_after = $theme->get('Version') ?: '';
        }

        $action_type = $action === 'install' ? 'install' : self::resolve_action_type($version_before, $version_after, 'update');

        $title = $action === 'install'
            ? sprintf(__('Installation of %1$s %2$s', 'updates-control'), $name, $version_after ?: '')
            : sprintf(__('Update to %1$s %2$s', 'updates-control'), $name, $version_after ?: '');
        $message = self::format_note_like_wp_screen($title, [], $process_message);

        UpdatesControl_Logger::log(
            'theme',
            $action_type,
            $name,
            $theme_slug,
            $version_before,
            $version_after,
            'success',
            $message,
            $trace
        );

        unset($stored[$theme_slug]);
        update_option(self::OPTION_THEME_VERSIONS_BEFORE, $stored);
    }
}
