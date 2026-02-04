<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues script and style on updatescontrol settings page only.
 */
add_action('admin_enqueue_scripts', 'updatescontrol_admin_enqueue_scripts');
function updatescontrol_admin_enqueue_scripts(string $admin_page): void {
    $allowed = ['tools_page_updates-control', 'dashboard_page_updates-control'];
    if (!in_array($admin_page, $allowed, true)) {
        return;
    }

    $asset_file = updatescontrol_PLUGIN_DIR . 'assets/build/index.asset.php';
    if (!file_exists($asset_file)) {
        return;
    }

    $asset = include $asset_file;
    if (!is_array($asset) || empty($asset['dependencies']) || empty($asset['version'])) {
        return;
    }

    wp_enqueue_script(
        'updatescontrol-scripts',
        plugins_url('assets/build/index.js', updatescontrol_PLUGIN_FILE),
        (array) $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_enqueue_style(
        'updatescontrol-style',
        plugins_url('assets/build/index.css', updatescontrol_PLUGIN_FILE),
        array_filter(
            (array) $asset['dependencies'],
            static function (string $style): bool {
                return wp_style_is($style, 'registered');
            }
        ),
        $asset['version']
    );
}

/**
 * Localizes REST URL and nonce for the Updates Control settings page.
 */
add_action('admin_enqueue_scripts', 'updatescontrol_localize_settings');
function updatescontrol_localize_settings(string $admin_page): void {
    $allowed = ['tools_page_updates-control', 'dashboard_page_updates-control'];
    if (!in_array($admin_page, $allowed, true)) {
        return;
    }

    wp_localize_script('updatescontrol-scripts', 'updatescontrolSettings', [
        'restUrl' => esc_url_raw(rest_url()),
        'namespace' => 'updatescontrol/v1',
        'nonce' => wp_create_nonce('wp_rest'),
        'options' => [
            'logging_enabled' => (bool) get_option('updatescontrol_logging_enabled', true),
            'retention_days' => (int) get_option('updatescontrol_retention_days', 90),
            'notify_enabled' => (bool) get_option('updatescontrol_notify_enabled', false),
            'notify_emails' => (string) get_option('updatescontrol_notify_emails', ''),
            'notify_on' => (array) get_option('updatescontrol_notify_on', ['error']),
        ],
    ]);
}
