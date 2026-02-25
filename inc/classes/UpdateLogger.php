<?php

/**
 * Update logger: observes and logs core/plugin/theme/translation updates. Hooks into
 * WordPress update flow only to add version_before to transients for audit logging
 * when updates complete. It does not modify or block updates.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

final class UpdateAutomate_Update_Logger {
    /**
     * Register hooks for update events.
     *
     * @return void
     */
    public static function register(): void {
        add_action('pre_auto_update', [self::class, 'pre_auto_update']);
        add_action('upgrader_process_complete', [self::class, 'on_upgrader_process_complete'], 10, 2);
        add_action('automatic_updates_complete', [self::class, 'log_automatic_updates'], 10, 1);
        add_filter('upgrader_pre_install', [self::class, 'store_core_version_before'], 5, 2);
        add_filter('upgrader_package_options', [self::class, 'initialize_pending_logs'], 10, 1);
        add_filter('upgrader_source_selection', [self::class, 'store_plugin_version_before_upload_overwrite'], 20, 4);
        add_filter('upgrader_source_selection', [self::class, 'store_theme_version_before_upload_overwrite'], 20, 4);
        add_filter('upgrader_pre_download', [self::class, 'init_core_feedback_on_download'], 5, 3);
        add_filter('upgrader_pre_download', [self::class, 'start_bulk_post_flush_buffer'], 10, 3);
        // phpcs:ignore plugin_updater_detected, update_modification_detected -- Update logger (logs updates only); we only add version_before to the transient for audit logging. We do not implement a plugin updater or alter what gets updated.
        add_filter('set_site_transient_update_plugins', [self::class, 'capture_plugin_versions_before'], 10, 1);
        add_filter('set_site_transient_update_themes', [self::class, 'capture_theme_versions_before'], 10, 1);
        register_shutdown_function([self::class, 'maybe_flush_pending_logs']);
        add_action('delete_plugin', [self::class, 'log_plugin_uninstall'], 10, 1);
        add_action('delete_theme', [self::class, 'log_theme_uninstall'], 10, 1);
    }

    /** Whether the current request is an automatic update run. */
    private static bool $auto_update = false;

    /**
     * Items already logged by upgrader_process_complete (to avoid duplicate logging
     * when automatic_updates_complete fires afterwards). Keyed by "{type}:{item}".
     *
     * @var array<string, true>
     */
    private static array $already_logged = [];

    /**
     * Pending log entries keyed by type and item (for shutdown fallback).
     *
     * @var array<string, array<string, array{name: string, slug: string, version_before: string, version_after: string}>>
     */
    private static array $pending_logs = [];

    /**
     * Whether we are currently in an automatic update context.
     *
     * @return bool
     */
    public static function is_automatic_update(): bool {
        return self::$auto_update;
    }

    /**
     * Set automatic-update flag (EUM-style).
     *
     * @return void
     */
    public static function pre_auto_update(): void {
        self::$auto_update = true;
    }

    /**
     * Initialize pending log data before update runs (for shutdown fallback).
     *
     * Uses callback OB when skin flushes output (Bulk_*_Skin::flush_output, or show_message
     * in translation flow). Plugin/theme bulk: is_multi + Bulk_Upgrader_Skin. Translation:
     * Language_Pack_Upgrader_Skin uses show_message (flushes). Core updates use
     * update_feedback filter only; no OB here. PHP forbids ob_start() inside an OB
     * handler, so the callback only appends and clears the flag; the next run() will
     * start a new OB.
     *
     * @param array<string, mixed> $options Package options with hook_extra.
     * @return array<string, mixed> Unchanged options.
     */
    public static function initialize_pending_logs(array $options): array {
        $hook_extra = $options['hook_extra'] ?? [];
        $action = $hook_extra['action'] ?? '';
        $type = $hook_extra['type'] ?? '';
        $has_plugin = isset($hook_extra['plugin']) && is_string($hook_extra['plugin']);
        $has_theme = isset($hook_extra['theme']) && is_string($hook_extra['theme']);
        $has_translation = isset($hook_extra['language_update']);
        $is_plugin_or_theme = $type === 'plugin' || $type === 'theme' || $has_plugin || $has_theme;
        $is_translation = $has_translation;
        $needs_feedback_ob = $is_plugin_or_theme || $is_translation;

        if ($needs_feedback_ob && !self::$feedback_ob_started) {
            $is_multi = !empty($options['is_multi']);
            if ($is_multi || $is_translation) {
                if (self::$feedback_ob_callback === null) {
                    self::$feedback_ob_callback = function (string $buf): string {
                        self::$captured_bulk_feedback .= $buf;
                        self::$feedback_ob_started = false;
                        self::$bulk_flush_happened = true;

                        return $buf;
                    };
                }
                ob_start(self::$feedback_ob_callback);
            } else {
                ob_start();
            }
            self::$feedback_ob_started = true;
        }

        if ($action !== 'update' && !$has_plugin && !$has_theme && !$has_translation) {
            return $options;
        }

        if ($has_plugin) {
            $file = $hook_extra['plugin'];
            $current = get_site_transient('update_plugins');
            if (is_object($current) && isset($current->response[$file])) {
                $plugins = function_exists('get_plugins') ? get_plugins() : [];
                if (!isset(self::$pending_logs['plugin'])) {
                    self::$pending_logs['plugin'] = [];
                }
                self::$pending_logs['plugin'][$file] = [
                    'name' => isset($plugins[$file]['Name']) ? (string) $plugins[$file]['Name'] : $file,
                    'slug' => dirname($file) === '.' ? $file : dirname($file),
                    'version_before' => isset($plugins[$file]['Version']) ? (string) $plugins[$file]['Version'] : '',
                    'version_after' => isset($current->response[$file]->new_version) ? (string) $current->response[$file]->new_version : '',
                ];
            }
        } elseif ($has_theme) {
            $slug = $hook_extra['theme'];
            $current = get_site_transient('update_themes');
            $theme_response = is_object($current) && isset($current->response[$slug]) ? $current->response[$slug] : null;
            if (is_array($theme_response)) {
                $themes = wp_get_themes();
                if (!isset(self::$pending_logs['theme'])) {
                    self::$pending_logs['theme'] = [];
                }
                $version_before = isset($themes[$slug]) ? (string) $themes[$slug]->get('Version') : '';
                self::$pending_logs['theme'][$slug] = [
                    'name' => isset($themes[$slug]) ? (string) $themes[$slug]->get('Name') : $slug,
                    'slug' => $slug,
                    'version_before' => $version_before,
                    'version_after' => isset($theme_response['new_version']) ? (string) $theme_response['new_version'] : '',
                ];
            }
        } elseif (isset($hook_extra['language_update_type'], $hook_extra['language_update']) && is_object($hook_extra['language_update'])) {
            $lu = $hook_extra['language_update'];
            $lang = isset($lu->language) ? (string) $lu->language : '';
            $ver_from = isset($lu->version) ? (string) $lu->version : '';
            $slug = isset($lu->slug) ? (string) $lu->slug : '';
            $type = $hook_extra['language_update_type'] ?? '';
            if ($type === 'core') {
                if (!isset(self::$pending_logs['translation'])) {
                    self::$pending_logs['translation'] = [];
                }
                $key = 'core_' . $lang;
                $current = get_site_transient('update_core');
                $ver_to = '';
                if (is_object($current) && !empty($current->translations)) {
                    foreach ($current->translations as $t) {
                        if (isset($t['language']) && $t['language'] === $lang && isset($t['version'])) {
                            $ver_to = (string) $t['version'];
                            break;
                        }
                    }
                }
                self::$pending_logs['translation'][$key] = [
                    'name' => 'WordPress (' . $lang . ')',
                    'slug' => $slug ?: $lang,
                    'version_before' => $ver_from,
                    'version_after' => $ver_to,
                ];
            } else {
                $ver_to = '';
                if ($type === 'plugin' && $slug) {
                    $current = get_site_transient('update_plugins');
                    if (is_object($current) && !empty($current->translations)) {
                        foreach ($current->translations as $t) {
                            if (isset($t['slug']) && $t['slug'] === $slug && isset($t['version'])) {
                                $ver_to = (string) $t['version'];
                                break;
                            }
                        }
                    }
                    $name = $slug . ' (' . $lang . ')';
                } else {
                    $current = get_site_transient('update_themes');
                    if (is_array($current) && !empty($current['translations'])) {
                        foreach ($current['translations'] as $t) {
                            if (isset($t['slug']) && $t['slug'] === $slug && isset($t['version'])) {
                                $ver_to = (string) $t['version'];
                                break;
                            }
                        }
                    }
                    $name = $slug . ' (' . $lang . ')';
                }
                if (!isset(self::$pending_logs['translation'])) {
                    self::$pending_logs['translation'] = [];
                }
                self::$pending_logs['translation'][$slug . '_' . $lang] = [
                    'name' => $name,
                    'slug' => $slug,
                    'version_before' => $ver_from,
                    'version_after' => $ver_to,
                ];
            }
        }

        return $options;
    }

    /**
     * On shutdown: log any pending updates that were not logged (e.g. fatal error).
     *
     * @return void
     */
    public static function maybe_flush_pending_logs(): void {
        if (empty(self::$pending_logs)) {
            return;
        }
        if (!updateautomate_get_settings()['logging_enabled']) {
            return;
        }
        if (!UpdateAutomate_Database::table_exists()) {
            return;
        }
        $trace = UpdateAutomate_ErrorHandler::capture_trace();
        $performed_as = self::$auto_update ? 'automatic' : 'manual';
        $status = 'error';
        foreach (self::$pending_logs as $log_type => $items) {
            foreach ($items as $key => $data) {
                $data = array_merge(['name' => '', 'slug' => '', 'version_before' => '', 'version_after' => ''], $data);
                $name = $data['name'];
                $slug = $data['slug'];
                $version_before = $data['version_before'];
                $version_after = $data['version_after'];
                UpdateAutomate_Logger::log(
                    $log_type,
                    'update',
                    $name,
                    $slug,
                    $version_before,
                    $version_after,
                    $status,
                    __('This update may not have completed. It was logged when the process ended unexpectedly.', 'update-automate'),
                    $trace,
                    $performed_as
                );
            }
        }
        self::$pending_logs = [];
    }

    /**
     * Log automatic updates when automatic_updates_complete fires (in case upgrader_process_complete did not).
     *
     * @param array<string, mixed> $update_results Results keyed by type (core, plugin, theme, translation); values are arrays of result objects.
     * @return void
     */
    public static function log_automatic_updates(array $update_results): void {
        if (!updateautomate_get_settings()['logging_enabled']) {
            return;
        }
        $trace = UpdateAutomate_ErrorHandler::capture_trace();
        $performed_as = 'automatic';
        $all_plugins = function_exists('get_plugins') ? get_plugins() : [];
        $all_themes = wp_get_themes();
        foreach ($update_results as $type => $results) {
            if (!is_array($results)) {
                continue;
            }
            foreach ($results as $result) {
                if (!is_object($result)) {
                    continue;
                }
                $name = 'Unknown';
                $slug = '';
                $version_before = '';
                $version_after = '';
                $status = 'success';
                $action_type = 'update';
                $notes = '';
                if (isset($result->item)) {
                    $item = $result->item;
                    if ($type === 'plugin' && isset($item->plugin)) {
                        $file = $item->plugin;
                        if (isset(self::$already_logged['plugin:' . $file])) {
                            unset(self::$pending_logs['plugin'][$file]);
                            continue;
                        }
                        $slug = dirname($file) === '.' ? $file : dirname($file);
                        if (isset(self::$pending_logs['plugin'][$file])) {
                            $p = self::$pending_logs['plugin'][$file];
                            $name = $p['name'];
                            $version_before = $p['version_before'];
                            $version_after = $p['version_after'];
                        } else {
                            $name = $all_plugins[$file]['Name'] ?? $file;
                            $version_after = $all_plugins[$file]['Version'] ?? '';
                        }
                        unset(self::$pending_logs['plugin'][$file]);
                    } elseif ($type === 'theme' && isset($item->theme)) {
                        $slug = $item->theme;
                        if (isset(self::$already_logged['theme:' . $slug])) {
                            unset(self::$pending_logs['theme'][$slug]);
                            continue;
                        }
                        if (isset(self::$pending_logs['theme'][$slug])) {
                            $p = self::$pending_logs['theme'][$slug];
                            $name = $p['name'];
                            $version_before = $p['version_before'];
                            $version_after = $p['version_after'];
                        } else {
                            $name = isset($all_themes[$slug]) ? (string) $all_themes[$slug]->get('Name') : $slug;
                            $version_after = isset($all_themes[$slug]) ? (string) $all_themes[$slug]->get('Version') : '';
                        }
                        unset(self::$pending_logs['theme'][$slug]);
                    } elseif ($type === 'translation' && isset($item->slug, $item->language)) {
                        $slug = $item->slug . '_' . $item->language;
                        if (isset(self::$already_logged['translation:' . $slug])) {
                            unset(self::$pending_logs['translation'][$slug]);
                            continue;
                        }
                        if (isset(self::$pending_logs['translation'][$slug])) {
                            $p = self::$pending_logs['translation'][$slug];
                            $name = $p['name'];
                            $version_before = $p['version_before'];
                            $version_after = $p['version_after'];
                        } else {
                            $name = $item->slug . ' (' . $item->language . ')';
                            $version_after = $item->version ?? '';
                        }
                        unset(self::$pending_logs['translation'][$slug]);
                    } elseif ($type === 'core') {
                        if (isset(self::$already_logged['core:core'])) {
                            if (!empty(self::$pending_logs['core'])) {
                                array_shift(self::$pending_logs['core']);
                            }
                            continue;
                        }
                        $name = 'WordPress';
                        $version_before = get_option(self::OPTION_CORE_VERSION_BEFORE, '');
                        $version_after = get_bloginfo('version');
                        if (!empty(self::$pending_logs['core'])) {
                            array_shift(self::$pending_logs['core']);
                        }
                    }
                }
                if (isset($result->result) && is_wp_error($result->result)) {
                    $status = 'error';
                }
                if (isset($result->messages) && is_array($result->messages)) {
                    $notes = implode("\n", array_map('strip_tags', $result->messages));
                }
                UpdateAutomate_Logger::log(
                    $type === 'translation' ? 'translation' : $type,
                    $action_type,
                    $name,
                    $slug,
                    $version_before,
                    $version_after,
                    $status,
                    $notes,
                    $trace,
                    $performed_as
                );
            }
        }
    }

    private const OPTION_CORE_VERSION_BEFORE = 'updateautomate_core_version_before';
    private const OPTION_PLUGIN_VERSIONS_BEFORE = 'updateautomate_plugin_versions_before';
    private const OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE = 'updateautomate_plugin_versions_before_by_mainfile';
    private const OPTION_THEME_VERSIONS_BEFORE = 'updateautomate_theme_versions_before';

    /** @var array<string> Collected core update feedback (update_feedback filter). */
    private static array $core_feedback = [];

    /** Package URL for core (to build "Downloading from..." step). */
    private static string $core_package_url = '';

    /** Whether we started an output buffer to capture WordPress feedback (plugin/theme manual flow). */
    private static bool $feedback_ob_started = false;

    /** Captured feedback from WordPress show_message() during the last run (plugin/theme). */
    private static string $captured_feedback = '';

    /** Accumulated output from bulk upgrade runs (bulk skin flushes the OB per item; we capture via callback). */
    private static string $captured_bulk_feedback = '';

    /** Set by bulk OB callback when skin flushed so we start a new buffer in upgrader_pre_download. */
    private static bool $bulk_flush_happened = false;

    /** True when we started an OB in upgrader_pre_download to capture post-flush feedback (Downloading…, etc.). */
    private static bool $post_flush_ob_started = false;

    /** Callable for ob_start used in bulk flow so we can re-start OB after each flush. */
    private static ?\Closure $feedback_ob_callback = null;

    /**
     * Store current core/plugin/theme versions before upgrade runs. For core, also hook update_feedback to collect process.
     *
     * @param bool                 $result     Whether the install was successful (filter pass-through).
     * @param array<string, mixed> $hook_extra Extra args (type, plugin, theme).
     * @return bool
     */
    public static function store_core_version_before(bool $result, array $hook_extra = []): bool {
        $type = $hook_extra['type'] ?? '';
        if ($type === 'core') {
            update_option(self::OPTION_CORE_VERSION_BEFORE, get_bloginfo('version'));
        }
        if (!empty($hook_extra['plugin']) && is_string($hook_extra['plugin'])) {
            $file = $hook_extra['plugin'];
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $all = get_plugins();
            $version = isset($all[$file]['Version']) ? (string) $all[$file]['Version'] : '';
            if ($version !== '') {
                $stored = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, []);
                $stored[$file] = $version;
                update_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, $stored);
            }
        }
        if (!empty($hook_extra['theme']) && is_string($hook_extra['theme'])) {
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
     * After bulk skin flushes output (before() → flush_output), start a new buffer so we capture the real log
     * (Downloading…, Unpacking…, Installing…, success, etc.) in download_package / install_package.
     *
     * @param bool|\WP_Error       $reply      Whether to short-circuit.
     * @param string               $package    Package URL.
     * @param \WP_Upgrader         $upgrader   Upgrader instance.
     * @param array<string, mixed> $hook_extra Extra args.
     * @return bool|\WP_Error Unchanged.
     */
    public static function start_bulk_post_flush_buffer(bool|\WP_Error $reply, string $package, \WP_Upgrader $upgrader, array $hook_extra = []): bool|\WP_Error {
        if (self::$bulk_flush_happened && $upgrader->skin instanceof \Bulk_Upgrader_Skin) {
            self::$bulk_flush_happened = false;
            ob_start(function (string $buf): string {
                self::$captured_bulk_feedback .= $buf;
                self::$post_flush_ob_started = false;

                return $buf;
            });
            self::$post_flush_ob_started = true;
        }

        return $reply;
    }

    /**
     * When core package is about to download, start collecting update_feedback (EUM-style; catches manual core update flow).
     *
     * @param bool|\WP_Error $reply    Whether to short-circuit.
     * @param string         $package  Package URL.
     * @param \WP_Upgrader   $upgrader Upgrader instance.
     * @return bool|\WP_Error Unchanged.
     */
    public static function init_core_feedback_on_download(bool|\WP_Error $reply, string $package, \WP_Upgrader $upgrader): bool|\WP_Error {
        if (!$upgrader instanceof \Core_Upgrader) {
            return $reply;
        }
        $version_before = get_bloginfo('version');
        update_option(self::OPTION_CORE_VERSION_BEFORE, $version_before);
        self::$core_feedback = [];
        self::$core_package_url = $package;
        $version_after = '';
        $current = get_site_transient('update_core');
        if (is_object($current) && !empty($current->updates)) {
            foreach ($current->updates as $u) {
                $packages = is_array($u->packages ?? null) ? $u->packages : (array) ($u->packages ?? []);
                if ($packages !== [] && in_array($package, $packages, true)) {
                    $version_after = (string) ($u->current ?? '');
                    break;
                }
            }
        }
        self::$pending_logs['core'] = [
            'core' => [
                'name' => 'WordPress',
                'slug' => 'core',
                'version_before' => $version_before,
                'version_after' => $version_after,
            ],
        ];
        add_filter('update_feedback', [self::class, 'collect_core_feedback'], 1, 1);

        return $reply;
    }

    /**
     * Collect core update step messages (update_feedback filter).
     *
     * @param mixed $feedback Message or error.
     * @return mixed Unchanged.
     */
    public static function collect_core_feedback(mixed $feedback): mixed {
        if (is_string($feedback) && $feedback !== '') {
            self::$core_feedback[] = wp_strip_all_tags(str_replace('&#8230;', '…', $feedback));
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
                $version = (string) $themes[$slug]->get('Version');
                if (!isset($value->response[$slug]['version_before'])) {
                    $value->response[$slug]['version_before'] = $version;
                }
            }
        }

        return $value;
    }

    /**
     * Store current plugin version before upload overwrite (update.php?action=upload-plugin, Replace).
     * Ensures version_before is available when upgrader_process_complete runs so we can log update/downgrade.
     *
     * @param string|\WP_Error     $source        Path to the unpacked package source (or WP_Error).
     * @param string               $remote_source Remote source (unused).
     * @param \WP_Upgrader         $upgrader      Upgrader instance.
     * @param array<string, mixed> $hook_extra    hook_extra from the upgrader run.
     * @return string|\WP_Error Unchanged source path or error.
     */
    public static function store_plugin_version_before_upload_overwrite(string|\WP_Error $source, string $remote_source, \WP_Upgrader $upgrader, array $hook_extra): string|\WP_Error {
        if (!is_string($source) || $source === '') {
            return $source;
        }
        if (($hook_extra['type'] ?? '') !== 'plugin' || ($hook_extra['action'] ?? '') !== 'install') {
            return $source;
        }
        if (!$upgrader instanceof \Plugin_Upgrader) {
            return $source;
        }
        $slug = basename(str_replace('\\', '/', trim($source, '/')));
        if ($slug === '' || $slug === '.') {
            return $source;
        }
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = null;
        $version = '';

        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (is_dir($plugin_dir)) {
            $plugins = get_plugins('/' . $slug);
            $plugin_file = array_key_first($plugins);
            if ($plugin_file !== null && $plugin_file !== '') {
                $version = isset($plugins[$plugin_file]['Version']) ? (string) $plugins[$plugin_file]['Version'] : '';
            }
        }

        if (($plugin_file === null || $plugin_file === '' || $version === '') && !empty($upgrader->new_plugin_data['Name'])) {
            $uploaded_name = (string) $upgrader->new_plugin_data['Name'];
            $all = get_plugins();
            foreach ($all as $file => $data) {
                if (isset($data['Name']) && (string) $data['Name'] === $uploaded_name) {
                    $plugin_file = $file;
                    $version = isset($data['Version']) ? (string) $data['Version'] : '';
                    break;
                }
            }
        }

        if ($plugin_file !== null && $plugin_file !== '' && $version !== '') {
            $stored = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, []);
            $stored[$plugin_file] = $version;
            update_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, $stored);
            $by_mainfile = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, []);
            $by_mainfile[basename($plugin_file)] = $version;
            update_option(self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, $by_mainfile);
        }

        return $source;
    }

    /**
     * Store current theme version before upload overwrite (update.php?action=upload-theme, Replace).
     *
     * @param string|\WP_Error     $source        Path to the unpacked package source (or WP_Error).
     * @param string               $remote_source Remote source (unused).
     * @param \WP_Upgrader         $upgrader      Upgrader instance.
     * @param array<string, mixed> $hook_extra    hook_extra from the upgrader run.
     * @return string|\WP_Error Unchanged source path or error.
     */
    public static function store_theme_version_before_upload_overwrite(string|\WP_Error $source, string $remote_source, \WP_Upgrader $upgrader, array $hook_extra): string|\WP_Error {
        if (!is_string($source) || $source === '') {
            return $source;
        }
        if (($hook_extra['type'] ?? '') !== 'theme' || ($hook_extra['action'] ?? '') !== 'install') {
            return $source;
        }
        if (!$upgrader instanceof \Theme_Upgrader) {
            return $source;
        }
        $slug = basename(str_replace('\\', '/', trim($source, '/')));
        if ($slug === '' || $slug === '.') {
            return $source;
        }

        $theme_slug = null;
        $version = '';

        $theme_root = get_theme_root();
        $theme_dir = $theme_root . '/' . $slug;
        if (is_dir($theme_dir)) {
            $theme = wp_get_theme($slug);
            if ($theme->exists()) {
                $theme_slug = $theme->get_stylesheet();
                $version = (string) $theme->get('Version');
            }
        }

        if (($theme_slug === null || $version === '') && !empty($upgrader->new_theme_data['Name'])) {
            $uploaded_name = (string) $upgrader->new_theme_data['Name'];
            $themes = wp_get_themes();
            foreach ($themes as $s => $t) {
                if ($t->get('Name') === $uploaded_name) {
                    $theme_slug = $s;
                    $version = (string) $t->get('Version');
                    break;
                }
            }
        }

        if ($theme_slug !== null && $version !== '') {
            $stored = (array) get_option(self::OPTION_THEME_VERSIONS_BEFORE, []);
            $stored[$theme_slug] = $version;
            update_option(self::OPTION_THEME_VERSIONS_BEFORE, $stored);
        }

        return $source;
    }

    /**
     * Fired when an update process completes.
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array<string, mixed> $options Array of item type, action, etc.
     * @return void
     */
    public static function on_upgrader_process_complete(WP_Upgrader $upgrader, array $options): void {
        if (!updateautomate_get_settings()['logging_enabled']) {
            return;
        }

        if (is_wp_error($upgrader->skin->result)) {
            return;
        }
        if ($upgrader instanceof \Plugin_Upgrader && is_wp_error($upgrader->result)) {
            return;
        }
        if ($upgrader instanceof \Theme_Upgrader && is_wp_error($upgrader->result)) {
            return;
        }

        $type = $options['type'] ?? '';
        $action = $options['action'] ?? '';

        if ($type === 'plugin' || $type === 'theme' || $type === 'translation') {
            $buffer = '';
            if (self::$feedback_ob_started) {
                $ob = ob_get_clean();
                self::$feedback_ob_started = false;
                $buffer = is_string($ob) ? $ob : '';
            } elseif (self::$post_flush_ob_started) {
                ob_get_clean();
                self::$post_flush_ob_started = false;
                $buffer = self::$captured_bulk_feedback;
                self::$captured_bulk_feedback = '';
            }
            if (self::$captured_bulk_feedback !== '') {
                $buffer = self::$captured_bulk_feedback . $buffer;
                self::$captured_bulk_feedback = '';
            }
            if ($buffer !== '') {
                self::$captured_feedback = self::feedback_html_to_plain($buffer);
                if (!$upgrader->skin instanceof \Bulk_Upgrader_Skin) {
                    echo wp_kses_post($buffer);
                }
            }
        }

        $skin_message = UpdateAutomate_ErrorHandler::get_skin_process_message($upgrader);
        $process_message = $skin_message;
        if (self::$captured_feedback !== '') {
            $ob_message = self::$captured_feedback;
            self::$captured_feedback = '';
            $process_message = $process_message !== '' ? $process_message . "\n" . $ob_message : $ob_message;
        }
        if ($process_message === '' && $type !== 'core' && $action === 'install') {
            $process_message = __('Installed from an uploaded file.', 'update-automate');
        }

        $trace = UpdateAutomate_ErrorHandler::capture_trace();
        $performed_as = self::$auto_update ? 'automatic' : 'manual';
        $update_context = (($type === 'plugin' || $type === 'theme') && $upgrader->skin instanceof \Bulk_Upgrader_Skin) ? 'bulk' : (($type === 'plugin' || $type === 'theme') ? 'single' : '');

        if ($type === 'core' && $action === 'update') {
            self::log_core_update($upgrader, $process_message, $trace, $performed_as);
            self::$already_logged['core:core'] = true;
            if (!empty(self::$pending_logs['core'])) {
                array_shift(self::$pending_logs['core']);
            }

            return;
        }

        if ($type === 'plugin') {
            $plugins = isset($options['plugins']) && is_array($options['plugins'])
                ? $options['plugins']
                : (isset($options['plugin']) && is_string($options['plugin']) ? [$options['plugin']] : []);

            if (empty($plugins) && $action === 'install' && $upgrader instanceof \Plugin_Upgrader) {
                $plugin_file = $upgrader->plugin_info();
                if (is_string($plugin_file) && $plugin_file !== '') {
                    $stored = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, []);
                    $by_mainfile = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, []);
                    $has_version_before = isset($stored[$plugin_file]) || isset($by_mainfile[basename($plugin_file)]);
                    $log_action = $has_version_before ? 'update' : 'install';
                    $plugin_performed_as = $has_version_before ? 'upload' : $performed_as;
                    self::log_plugin_update($plugin_file, $log_action, $upgrader, $process_message, $trace, $plugin_performed_as, $update_context);
                    self::$already_logged['plugin:' . $plugin_file] = true;
                }
            } else {
                foreach ($plugins as $plugin_file) {
                    self::log_plugin_update($plugin_file, $action, $upgrader, $process_message, $trace, $performed_as, $update_context);
                    self::$already_logged['plugin:' . $plugin_file] = true;
                }
            }
        }

        if ($type === 'theme') {
            $themes = isset($options['themes']) && is_array($options['themes'])
                ? $options['themes']
                : (isset($options['theme']) && is_string($options['theme']) ? [$options['theme']] : []);

            if (empty($themes) && $action === 'install' && $upgrader instanceof \Theme_Upgrader) {
                $theme_info = $upgrader->theme_info();
                if ($theme_info) {
                    $theme_slug = $theme_info->get_stylesheet();
                    if ($theme_slug !== '') {
                        $stored = (array) get_option(self::OPTION_THEME_VERSIONS_BEFORE, []);
                        $log_action = isset($stored[$theme_slug]) ? 'update' : 'install';
                        $theme_performed_as = isset($stored[$theme_slug]) ? 'upload' : $performed_as;
                        self::log_theme_update($theme_slug, $log_action, $upgrader, $process_message, $trace, $theme_performed_as, $update_context);
                        self::$already_logged['theme:' . $theme_slug] = true;
                    }
                }
            } else {
                foreach ($themes as $theme_slug) {
                    self::log_theme_update($theme_slug, $action, $upgrader, $process_message, $trace, $performed_as, $update_context);
                    self::$already_logged['theme:' . $theme_slug] = true;
                }
            }
        }

        if ($type === 'translation' && !empty($options['translations']) && is_array($options['translations'])) {
            foreach ($options['translations'] as $trans) {
                $t_type = $trans['type'] ?? '';
                $t_slug = $trans['slug'] ?? '';
                $t_lang = $trans['language'] ?? '';
                $key = $t_type === 'core' ? 'core_' . $t_lang : $t_slug . '_' . $t_lang;
                $name = 'Unknown';
                $slug = $t_slug ?: $t_lang;
                $version_before = '';
                $version_after = $trans['version'] ?? '';
                if (isset(self::$pending_logs['translation'][$key])) {
                    $p = self::$pending_logs['translation'][$key];
                    $name = $p['name'];
                    $version_before = $p['version_before'];
                    $version_after = $p['version_after'];
                } else {
                    if ($t_type === 'core') {
                        $name = 'WordPress (' . $t_lang . ')';
                    } elseif ($t_type === 'plugin' && function_exists('get_plugins')) {
                        $all = get_plugins();
                        foreach ($all as $file => $data) {
                            if (dirname($file) === $t_slug || strpos($file, $t_slug . '/') === 0) {
                                $name = ($data['Name'] ?? $t_slug) . ' (' . $t_lang . ')';
                                break;
                            }
                        }
                    } else {
                        $themes = wp_get_themes();
                        $name = (isset($themes[$t_slug]) ? $themes[$t_slug]->get('Name') : $t_slug) . ' (' . $t_lang . ')';
                    }
                }
                unset(self::$pending_logs['translation'][$key]);
                UpdateAutomate_Logger::log(
                    'translation',
                    'update',
                    $name,
                    $slug,
                    $version_before,
                    $version_after,
                    'success',
                    $process_message,
                    $trace,
                    $performed_as
                );
                self::$already_logged['translation:' . $key] = true;
            }
        }
    }

    /**
     * Log WordPress core update or downgrade.
     *
     * @param WP_Upgrader $upgrader        Upgrader instance (for process message).
     * @param string     $process_message Optional process log (e.g. from skin).
     * @param string     $trace           Optional call stack trace.
     * @param string     $performed_as    manual or automatic.
     * @return void
     */
    private static function log_core_update(WP_Upgrader $upgrader, string $process_message = '', string $trace = '', string $performed_as = 'manual'): void {
        remove_filter('update_feedback', [self::class, 'collect_core_feedback'], 1);

        $version_before = get_option(self::OPTION_CORE_VERSION_BEFORE, '');
        $version_after = get_bloginfo('version');
        $action_type = self::resolve_action_type($version_before, $version_after, 'update');

        $steps = self::$core_feedback;
        if (self::$core_package_url !== '') {
            array_unshift(
                $steps,
                sprintf(
                    /* translators: %s: package download URL */
                    __('Downloading the update from %s…', 'update-automate'),
                    self::$core_package_url
                ),
                __('Unpacking the update…', 'update-automate')
            );
        }

        $message = self::format_note_like_wp_screen(
            sprintf(
                /* translators: %s: WordPress version number */
                __('Core update to WordPress %s', 'update-automate'),
                $version_after
            ),
            $steps,
            $process_message
        );

        UpdateAutomate_Logger::log(
            'core',
            $action_type,
            'WordPress',
            'core',
            $version_before,
            $version_after,
            'success',
            $message,
            $trace,
            $performed_as
        );

        delete_option(self::OPTION_CORE_VERSION_BEFORE);
        self::$core_feedback = [];
        self::$core_package_url = '';
    }

    /**
     * Convert WordPress feedback HTML (from show_message) to plain text for logging.
     *
     * @param string $html Output captured from show_message() (e.g. "<p>Unpacking…</p>\n").
     * @return string Plain text, one message per line.
     */
    private static function feedback_html_to_plain(string $html): string {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>\s*/is', '', $html);
        $text = wp_strip_all_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s*\n\s*/', "\n", $text);
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $lines = array_filter($lines, function (string $line): bool {
            if ($line === 'More details.') {
                return false;
            }
            if (str_starts_with($line, 'jQuery(')) {
                return false;
            }

            return true;
        });
        $lines = array_map(function (string $line): string {
            $suffix = ' More details.';
            if (str_ends_with($line, $suffix)) {
                return substr($line, 0, -strlen($suffix));
            }

            return $line;
        }, $lines);

        return implode("\n", $lines);
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
     * Resolve action type: downgrade, same_version, or update.
     *
     * @param string $version_before Previous version.
     * @param string $version_after  Current version.
     * @param string $default        Default when versions not comparable (e.g. update).
     * @return string One of: downgrade, same_version, update.
     */
    private static function resolve_action_type(string $version_before, string $version_after, string $default = 'update'): string {
        if ($version_before !== '' && $version_after !== '') {
            $cmp = version_compare($version_after, $version_before);
            if ($cmp < 0) {
                return 'downgrade';
            }
            if ($cmp === 0) {
                return 'same_version';
            }
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
     * @param string       $performed_as    manual or automatic.
     * @param string       $update_context  bulk or single (empty for legacy).
     * @return void
     */
    private static function log_plugin_update(string $plugin_file, string $action, WP_Upgrader $upgrader, string $process_message = '', string $trace = '', string $performed_as = 'manual', string $update_context = ''): void {
        $stored = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, []);
        $by_mainfile = (array) get_option(self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, []);
        $version_before = isset($stored[$plugin_file]) ? (string) $stored[$plugin_file] : '';
        if ($version_before === '' && isset($by_mainfile[basename($plugin_file)])) {
            $version_before = (string) $by_mainfile[basename($plugin_file)];
        }
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
                $version_after = (string) $data['Version'];
            }
        }

        $slug = dirname($plugin_file);
        if ($slug === '.') {
            $slug = $plugin_file;
        }

        $action_type = $action === 'install' ? 'install' : self::resolve_action_type($version_before, $version_after, 'update');

        $title = self::format_plugin_log_title($action_type, $name, $version_after);
        $message = self::format_plugin_log_message($title, $process_message);

        UpdateAutomate_Logger::log(
            'plugin',
            $action_type,
            $name,
            $slug,
            $version_before,
            $version_after,
            'success',
            $message,
            $trace,
            $performed_as,
            $update_context
        );

        unset($stored[$plugin_file]);
        unset($by_mainfile[basename($plugin_file)]);
        unset(self::$pending_logs['plugin'][$plugin_file]);
        update_option(self::OPTION_PLUGIN_VERSIONS_BEFORE, $stored);
        update_option(self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, $by_mainfile);
    }

    /**
     * Log plugin uninstall/deletion (runs on delete_plugin before the plugin directory is removed).
     *
     * @param string $plugin_file Plugin file path relative to wp-content/plugins (e.g. "akismet/akismet.php").
     * @return void
     */
    public static function log_plugin_uninstall(string $plugin_file): void {
        if (!updateautomate_get_settings()['logging_enabled']) {
            return;
        }
        if (!UpdateAutomate_Database::table_exists()) {
            return;
        }

        $version_before = '';
        $name = basename($plugin_file);
        if (function_exists('get_plugins')) {
            $all = get_plugins();
            if (isset($all[$plugin_file])) {
                $name = $all[$plugin_file]['Name'] ?? $name;
                $version_before = (string) ($all[$plugin_file]['Version'] ?? '');
            }
        }

        $slug = dirname($plugin_file);
        if ($slug === '.') {
            $slug = $plugin_file;
        }

        $title = self::format_plugin_log_title('uninstall', $name, $version_before);
        $trace = UpdateAutomate_ErrorHandler::capture_trace();

        UpdateAutomate_Logger::log(
            'plugin',
            'uninstall',
            $name,
            $slug,
            $version_before,
            '',
            'success',
            $title,
            $trace,
            'manual',
            ''
        );
    }

    /**
     * Log theme uninstall/deletion (runs on delete_theme before the theme directory is removed).
     *
     * @param string $stylesheet Theme stylesheet (slug), e.g. "twentytwentyfour".
     * @return void
     */
    public static function log_theme_uninstall(string $stylesheet): void {
        if (!updateautomate_get_settings()['logging_enabled']) {
            return;
        }
        if (!UpdateAutomate_Database::table_exists()) {
            return;
        }

        $version_before = '';
        $name = $stylesheet;
        $themes = wp_get_themes();
        if (isset($themes[$stylesheet])) {
            $theme = $themes[$stylesheet];
            $name = (string) ($theme->get('Name') ?: $stylesheet);
            $version_before = (string) ($theme->get('Version') ?: '');
        }

        $title = self::format_plugin_log_title('uninstall', $name, $version_before);
        $trace = UpdateAutomate_ErrorHandler::capture_trace();

        UpdateAutomate_Logger::log(
            'theme',
            'uninstall',
            $name,
            $stylesheet,
            $version_before,
            '',
            'success',
            $title,
            $trace,
            'manual',
            ''
        );
    }

    /**
     * Format plugin log title by action type.
     *
     * @param string $action_type   install, update, downgrade, same_version, uninstall.
     * @param string $name          Plugin name.
     * @param string $version_after Version after update (or version before for uninstall).
     * @return string
     */
    private static function format_plugin_log_title(string $action_type, string $name, string $version_after): string {
        if ($action_type === 'install') {
            /* translators: 1: item name (plugin or theme), 2: version number */
            return sprintf(__('Installed %1$s %2$s', 'update-automate'), $name, $version_after ?: '');
        }
        if ($action_type === 'uninstall') {
            /* translators: 1: item name (plugin or theme), 2: version number */
            return sprintf(__('Uninstalled %1$s %2$s', 'update-automate'), $name, $version_after ?: '');
        }
        if ($action_type === 'downgrade') {
            /* translators: 1: item name (plugin or theme), 2: version number */
            return sprintf(__('Rolled back %1$s to %2$s', 'update-automate'), $name, $version_after ?: '');
        }
        if ($action_type === 'same_version') {
            /* translators: 1: item name (plugin or theme), 2: version number */
            return sprintf(__('Reinstalled %1$s %2$s (same version)', 'update-automate'), $name, $version_after ?: '');
        }

        /* translators: 1: item name (plugin or theme), 2: version number */
        return sprintf(__('Updated %1$s to %2$s', 'update-automate'), $name, $version_after ?: '');
    }

    /**
     * Format plugin log message: title plus process message from WordPress (captured or skin).
     *
     * @param string $title           First line (e.g. "Downgrade to X Y").
     * @param string $process_message Message from WordPress (output buffer or skin get_upgrade_messages).
     * @return string
     */
    private static function format_plugin_log_message(string $title, string $process_message): string {
        if ($process_message === '') {
            return $title;
        }

        return $title . "\n\n" . trim($process_message);
    }

    /**
     * Log theme update/install/downgrade.
     *
     * @param string      $theme_slug      Theme slug.
     * @param string      $action          update or install.
     * @param WP_Upgrader $upgrader        Upgrader instance.
     * @param string      $process_message Optional process log (e.g. from skin).
     * @param string      $trace           Optional call stack trace.
     * @param string      $performed_as    manual or automatic.
     * @param string      $update_context  bulk or single (empty for legacy).
     * @return void
     */
    private static function log_theme_update(string $theme_slug, string $action, WP_Upgrader $upgrader, string $process_message = '', string $trace = '', string $performed_as = 'manual', string $update_context = ''): void {
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

        $title = self::format_plugin_log_title($action_type, $name, $version_after);
        $message = self::format_note_like_wp_screen($title, [], $process_message);

        UpdateAutomate_Logger::log(
            'theme',
            $action_type,
            $name,
            $theme_slug,
            $version_before,
            $version_after,
            'success',
            $message,
            $trace,
            $performed_as,
            $update_context
        );

        unset($stored[$theme_slug]);
        unset(self::$pending_logs['theme'][$theme_slug]);
        update_option(self::OPTION_THEME_VERSIONS_BEFORE, $stored);
    }
}
