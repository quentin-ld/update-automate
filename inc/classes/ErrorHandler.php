<?php

/**
 * Implements error handling to manage and log issues during updates.
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Catches update errors and passes them to the logger.
 */
final class UpdatesControl_ErrorHandler {
    /**
     * Register hooks for update failures and errors.
     *
     * @return void
     */
    public static function register(): void {
        add_filter('wp_redirect', [self::class, 'capture_redirect_error'], 10, 2);
        add_filter('upgrader_pre_download', [self::class, 'capture_download_error'], 10, 3);
        add_action('upgrader_process_complete', [self::class, 'log_upgrader_failure'], 20, 2);
        add_filter('automatic_updates_is_vcs_checkout', [self::class, 'vcs_checkout_check'], 10, 2);
    }

    /**
     * When an upgrade completes, log failure if the upgrader skin result is WP_Error.
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array<string, mixed> $options Options (type, action, plugins, themes, etc.).
     * @return void
     */
    public static function log_upgrader_failure(WP_Upgrader $upgrader, array $options): void {
        if (!get_option('updatescontrol_logging_enabled', true)) {
            return;
        }

        $skin = $upgrader->skin;
        $result = $skin->result;
        if (!$result instanceof \WP_Error) {
            return;
        }

        $message = $result->get_error_message();
        $type = $options['type'] ?? 'plugin';
        $action = $options['action'] ?? 'update';
        $name = __('Unknown', 'updates-control');
        $slug = '';

        if ($type === 'core') {
            $name = 'WordPress';
            $slug = 'core';
        } elseif ($type === 'plugin' && !empty($options['plugins']) && is_array($options['plugins'])) {
            $plugin_file = $options['plugins'][0] ?? '';
            if (function_exists('get_plugins')) {
                $all = get_plugins();
                $name = $all[$plugin_file]['Name'] ?? $plugin_file;
            } else {
                $name = $plugin_file;
            }
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = $plugin_file;
            }
        } elseif ($type === 'theme' && !empty($options['themes']) && is_array($options['themes'])) {
            $theme_slug = $options['themes'][0] ?? '';
            $themes = wp_get_themes();
            $name = isset($themes[$theme_slug]) ? $themes[$theme_slug]->get('Name') : $theme_slug;
            $slug = $theme_slug;
        }

        UpdatesControl_Logger::log(
            $type,
            'failed',
            $name,
            $slug,
            '',
            '',
            'error',
            $message
        );
    }

    /**
     * Capture redirects that may indicate update failures (e.g. to wp-admin/update-core.php?action=do-core-upgrade with error).
     *
     * @param string $location Redirect location.
     * @param int    $status   Status code.
     * @return string Unchanged location.
     */
    public static function capture_redirect_error(string $location, int $status): string {
        if ($status >= 400 && str_contains($location, 'update-core.php')) {
            UpdatesControl_Logger::log(
                'core',
                'failed',
                'WordPress',
                'core',
                '',
                '',
                'error',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('Update redirect with status %d', 'updates-control'),
                    $status
                )
            );
        }

        return $location;
    }

    /**
     * Capture download errors during package install/update.
     *
     * @param bool|WP_Error $reply    Whether to short-circuit. Pass WP_Error to record error.
     * @param string        $package  Package URL.
     * @param WP_Upgrader   $upgrader Upgrader instance.
     * @return bool|WP_Error
     */
    public static function capture_download_error($reply, string $package, WP_Upgrader $upgrader) {
        if (is_wp_error($reply)) {
            $message = $reply->get_error_message();
            $type = 'plugin';
            $name = '';
            if (isset($upgrader->skin->plugin)) {
                $type = 'plugin';
                $name = is_string($upgrader->skin->plugin) ? $upgrader->skin->plugin : '';
            } elseif (isset($upgrader->skin->theme)) {
                $type = 'theme';
                $name = is_string($upgrader->skin->theme) ? $upgrader->skin->theme : '';
            }
            UpdatesControl_Logger::log($type, 'failed', $name ?: 'unknown', '', '', '', 'error', $message);
        }

        return $reply;
    }

    /**
     * Optional: detect VCS checkout to avoid auto-update on dev sites.
     *
     * @param bool   $checkout Whether it's a VCS checkout.
     * @param string $context  Path context.
     * @return bool
     */
    public static function vcs_checkout_check(bool $checkout, string $context): bool {
        return $checkout;
    }
}
