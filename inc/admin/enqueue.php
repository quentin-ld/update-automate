<?php

/**
 * Admin asset enqueuing for the Update Automate settings page.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_enqueue_scripts', 'updateautomate_admin_enqueue_scripts');
/**
 * Enqueues script and style on updateautomate settings page only.
 *
 * @param string $admin_page Current admin page hook suffix.
 * @return void
 */
function updateautomate_admin_enqueue_scripts(string $admin_page): void {
    $allowed = ['tools_page_update-automate', 'dashboard_page_update-automate'];
    if (!in_array($admin_page, $allowed, true)) {
        return;
    }

    $asset_file = updateautomate_PLUGIN_DIR . 'assets/build/index.asset.php';
    if (!file_exists($asset_file)) {
        return;
    }

    $asset = include $asset_file;
    if (!is_array($asset) || empty($asset['dependencies']) || empty($asset['version'])) {
        return;
    }

    wp_enqueue_script(
        'updateautomate-scripts',
        plugins_url('assets/build/index.js', updateautomate_PLUGIN_FILE),
        (array) $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_enqueue_style(
        'updateautomate-style',
        plugins_url('assets/build/index.css', updateautomate_PLUGIN_FILE),
        array_merge(
            ['wp-components'],
            array_filter(
                (array) $asset['dependencies'],
                static function (string $style): bool {
                    return wp_style_is($style, 'registered');
                }
            )
        ),
        $asset['version']
    );
}

add_action('admin_enqueue_scripts', 'updateautomate_localize_settings');
/**
 * Localizes REST URL and nonce for the Update Automate settings page.
 *
 * @param string $admin_page Current admin page hook suffix.
 * @return void
 */
function updateautomate_localize_settings(string $admin_page): void {
    $allowed = ['tools_page_update-automate', 'dashboard_page_update-automate'];
    if (!in_array($admin_page, $allowed, true)) {
        return;
    }

    $options = updateautomate_get_settings();
    wp_localize_script('updateautomate-scripts', 'updateautomateSettings', [
        'restUrl' => esc_url_raw(rest_url()),
        'namespace' => 'updateautomate/v1',
        'nonce' => wp_create_nonce('wp_rest'),
        'options' => $options,
    ]);
}
