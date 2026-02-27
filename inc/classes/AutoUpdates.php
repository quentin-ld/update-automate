<?php

/**
 * Centralises detection and mutation of native WordPress auto-update settings.
 *
 * Reads/writes only native WP options and site options — no parallel option structure.
 * Translation preference is stored inside the plugin's existing JSON settings.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

final class UpdateAutomate_AutoUpdates {
    /**
     * Register the translation auto-update filter.
     *
     * @return void
     */
    public static function register(): void {
        add_action('init', [self::class, 'apply_translation_setting']);
    }

    /**
     * Hook `auto_update_translation` based on stored preference.
     *
     * @return void
     */
    public static function apply_translation_setting(): void {
        $settings = updateautomate_get_settings();
        if ($settings['auto_update_translations'] === false) {
            add_filter('auto_update_translation', '__return_false', 20);
        }
    }

    /**
     * Full payload for the GET /auto-updates endpoint.
     *
     * @return array{constants: array<string, array{defined: bool, value: mixed, affects: array<string>, locks: bool}>, dismissed_constants: array<string>, core: array{mode: string, major: string, minor: string, dev: string, overridden_by_constant: bool}, plugins: array<int, array{file: string, slug: string, name: string, description: string, version: string, author: string, plugin_uri: string, icon: string, auto_update: bool, auto_update_available: bool, active: bool}>, themes: array<int, array{stylesheet: string, name: string, description: string, version: string, author: string, theme_uri: string, icon: string, auto_update: bool, auto_update_available: bool, active: bool}>, translations: array{auto_update: bool}}
     */
    public static function get_data(): array {
        return [
            'constants' => self::get_constants(),
            'dismissed_constants' => updateautomate_get_settings()['dismissed_constants'],
            'core' => self::get_core_config(),
            'plugins' => self::get_plugins_data(),
            'themes' => self::get_themes_data(),
            'translations' => self::get_translations_config(),
        ];
    }

    /**
     * Detect wp-config constants that override auto-update behaviour.
     *
     * @return array<string, array{defined: bool, value: mixed, affects: array<string>, locks: bool}>
     */
    public static function get_constants(): array {
        $constants = [];

        if (defined('WP_AUTO_UPDATE_CORE')) {
            $constants['WP_AUTO_UPDATE_CORE'] = [
                'defined' => true,
                'value' => constant('WP_AUTO_UPDATE_CORE'),
                'affects' => ['core'],
                'locks' => true,
            ];
        }

        if (defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED) {
            $constants['AUTOMATIC_UPDATER_DISABLED'] = [
                'defined' => true,
                'value' => true,
                'affects' => ['core', 'plugins', 'themes', 'translations'],
                'locks' => true,
            ];
        }

        if (!wp_is_file_mod_allowed('automatic_updater')) {
            $constants['DISALLOW_FILE_MODS'] = [
                'defined' => true,
                'value' => true,
                'affects' => ['core', 'plugins', 'themes', 'translations'],
                'locks' => true,
            ];
        }

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $constants['DISABLE_WP_CRON'] = [
                'defined' => true,
                'value' => true,
                'affects' => ['core', 'plugins', 'themes', 'translations'],
                'locks' => false,
            ];
        }

        return $constants;
    }

    /**
     * Determine whether a section is blocked by a constant.
     *
     * @param string $section One of 'core', 'plugins', 'themes', 'translations'.
     * @return bool
     */
    public static function is_section_locked(string $section): bool {
        $constants = self::get_constants();
        foreach ($constants as $info) {
            if ($info['locks'] && in_array($section, $info['affects'], true) && $info['value']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Core auto-update configuration.
     *
     * @return array{mode: string, major: string, minor: string, dev: string, overridden_by_constant: bool}
     */
    public static function get_core_config(): array {
        $major = get_site_option('auto_update_core_major', 'unset');
        $minor = get_site_option('auto_update_core_minor', 'enabled');
        $dev = get_site_option('auto_update_core_dev', 'enabled');

        if ($minor === 'enabled' && $major === 'enabled') {
            $mode = 'all';
        } elseif ($minor === 'enabled') {
            $mode = 'minor';
        } else {
            $mode = 'disabled';
        }

        $overridden = defined('WP_AUTO_UPDATE_CORE');
        if ($overridden) {
            $const_value = constant('WP_AUTO_UPDATE_CORE');
            if ($const_value === true || in_array($const_value, ['beta', 'rc', 'development', 'branch-development'], true)) {
                $mode = 'all';
            } elseif ($const_value === 'minor') {
                $mode = 'minor';
            } elseif ($const_value === false) {
                $mode = 'disabled';
            }
        }

        return [
            'mode' => $mode,
            'major' => $major,
            'minor' => $minor,
            'dev' => $dev,
            'overridden_by_constant' => $overridden,
        ];
    }

    /**
     * All installed plugins with their auto-update status and metadata.
     *
     * @return array<int, array{file: string, slug: string, name: string, description: string, version: string, author: string, plugin_uri: string, icon: string, auto_update: bool, auto_update_available: bool, active: bool}>
     */
    public static function get_plugins_data(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $auto_update_plugins = (array) get_site_option('auto_update_plugins', []);

        $icons = [];
        $update_transient = get_site_transient('update_plugins');
        if ($update_transient) {
            foreach (['response', 'no_update'] as $group) {
                if (!empty($update_transient->{$group})) {
                    foreach ($update_transient->{$group} as $file => $data) {
                        if (!empty($data->icons)) {
                            $icons[$file] = (array) $data->icons;
                        }
                    }
                }
            }
        }

        $plugins = [];
        foreach ($all_plugins as $file => $data) {
            $slug = dirname($file);
            if ($slug === '.') {
                $slug = basename($file, '.php');
            }

            $icon_url = '';
            if (isset($icons[$file])) {
                $icon_data = $icons[$file];
                $icon_url = $icon_data['svg'] ?? $icon_data['2x'] ?? $icon_data['1x'] ?? $icon_data['default'] ?? '';
            }

            $auto_update_available = $update_transient
                && (isset($update_transient->response[$file])
                    || isset($update_transient->no_update[$file]));

            $plugins[] = [
                'file' => $file,
                'slug' => $slug,
                'name' => $data['Name'] ?? '',
                'description' => wp_strip_all_tags($data['Description'] ?? ''),
                'version' => $data['Version'] ?? '',
                'author' => wp_strip_all_tags($data['AuthorName'] ?? $data['Author'] ?? ''),
                'plugin_uri' => $data['PluginURI'] ?? '',
                'icon' => $icon_url,
                'auto_update' => in_array($file, $auto_update_plugins, true),
                'auto_update_available' => $auto_update_available,
                'active' => is_plugin_active($file),
            ];
        }

        usort($plugins, static function (array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        });

        return $plugins;
    }

    /**
     * All installed themes with their auto-update status and metadata.
     *
     * @return array<int, array{stylesheet: string, name: string, description: string, version: string, author: string, theme_uri: string, icon: string, auto_update: bool, auto_update_available: bool, active: bool}>
     */
    public static function get_themes_data(): array {
        $all_themes = wp_get_themes();
        $auto_update_themes = (array) get_site_option('auto_update_themes', []);
        $active_stylesheet = get_stylesheet();
        $update_themes_transient = get_site_transient('update_themes');

        $themes = [];
        foreach ($all_themes as $stylesheet => $theme) {
            $auto_update_available = false;
            if ($update_themes_transient) {
                $auto_update_available = isset($update_themes_transient->response[$stylesheet])
                    || isset($update_themes_transient->no_update[$stylesheet]);
            }

            $themes[] = [
                'stylesheet' => $stylesheet,
                'name' => $theme->get('Name'),
                'description' => wp_strip_all_tags($theme->get('Description')),
                'version' => $theme->get('Version'),
                'author' => wp_strip_all_tags($theme->get('Author')),
                'theme_uri' => $theme->get('ThemeURI'),
                'icon' => $theme->get_screenshot() ?: '',
                'auto_update' => in_array($stylesheet, $auto_update_themes, true),
                'auto_update_available' => $auto_update_available,
                'active' => $stylesheet === $active_stylesheet,
            ];
        }

        usort($themes, static function (array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        });

        return $themes;
    }

    /**
     * Translation auto-update config (stored in plugin settings JSON).
     *
     * @return array{auto_update: bool}
     */
    public static function get_translations_config(): array {
        $settings = updateautomate_get_settings();

        return ['auto_update' => $settings['auto_update_translations']];
    }

    /**
     * Set the core auto-update mode.
     *
     * @param string $mode 'all', 'minor', or 'disabled'.
     * @return bool
     */
    public static function set_core_mode(string $mode): bool {
        if (defined('WP_AUTO_UPDATE_CORE')) {
            return false;
        }

        switch ($mode) {
            case 'all':
                update_site_option('auto_update_core_major', 'enabled');
                update_site_option('auto_update_core_minor', 'enabled');
                break;
            case 'minor':
                update_site_option('auto_update_core_major', 'unset');
                update_site_option('auto_update_core_minor', 'enabled');
                break;
            case 'disabled':
                update_site_option('auto_update_core_major', 'unset');
                update_site_option('auto_update_core_minor', 'disabled');
                break;
            default:
                return false;
        }

        return true;
    }

    /**
     * Toggle auto-update for a single plugin (writes native auto_update_plugins option).
     *
     * @param string $plugin_file Plugin path relative to plugins dir.
     * @param bool   $enable      Whether to enable auto-update.
     * @return bool
     */
    public static function toggle_plugin(string $plugin_file, bool $enable): bool {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        if (!array_key_exists($plugin_file, $all_plugins)) {
            return false;
        }

        $auto_updates = (array) get_site_option('auto_update_plugins', []);

        if ($enable) {
            $auto_updates[] = $plugin_file;
            $auto_updates = array_unique($auto_updates);
        } else {
            $auto_updates = array_diff($auto_updates, [$plugin_file]);
        }

        $auto_updates = array_values(array_intersect($auto_updates, array_keys($all_plugins)));
        update_site_option('auto_update_plugins', $auto_updates);

        return true;
    }

    /**
     * Toggle auto-update for a single theme (writes native auto_update_themes option).
     *
     * @param string $stylesheet Theme stylesheet slug.
     * @param bool   $enable     Whether to enable auto-update.
     * @return bool
     */
    public static function toggle_theme(string $stylesheet, bool $enable): bool {
        $all_themes = wp_get_themes();
        if (!array_key_exists($stylesheet, $all_themes)) {
            return false;
        }

        $auto_updates = (array) get_site_option('auto_update_themes', []);

        if ($enable) {
            $auto_updates[] = $stylesheet;
            $auto_updates = array_unique($auto_updates);
        } else {
            $auto_updates = array_diff($auto_updates, [$stylesheet]);
        }

        $auto_updates = array_values(array_intersect($auto_updates, array_keys($all_themes)));
        update_site_option('auto_update_themes', $auto_updates);

        return true;
    }

    /**
     * Dismiss a constant notice (stored in plugin settings JSON).
     *
     * @param string $constant_name The constant name to dismiss.
     * @return bool
     */
    public static function dismiss_constant(string $constant_name): bool {
        $settings = updateautomate_get_settings();
        $dismissed = $settings['dismissed_constants'];
        if (!in_array($constant_name, $dismissed, true)) {
            $dismissed[] = $constant_name;
        }
        $settings['dismissed_constants'] = $dismissed;
        $json = wp_json_encode($settings);
        if ($json !== false) {
            update_option(UPDATEAUTOMATE_OPTION_SETTINGS, $json);
        }

        return true;
    }

    /**
     * Set translation auto-update preference (stored in plugin settings JSON).
     *
     * @param bool $enable Whether to enable translation auto-updates.
     * @return bool
     */
    public static function set_translations(bool $enable): bool {
        $settings = updateautomate_get_settings();
        $settings['auto_update_translations'] = $enable;
        $json = wp_json_encode($settings);
        if ($json !== false) {
            update_option(UPDATEAUTOMATE_OPTION_SETTINGS, $json);
        }

        return true;
    }
}
