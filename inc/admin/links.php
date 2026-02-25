<?php

/**
 * Plugin action and row meta links (Settings, Changelog, Docs, Support).
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('plugin_action_links_' . plugin_basename(updateautomate_PLUGIN_FILE), 'updateautomate_add_settings_link');
/**
 * Adds "Settings" to plugin action links on Plugins screen.
 *
 * @param array<int, string> $links Existing action links.
 * @return array<int, string> Action links with Settings added.
 */
function updateautomate_add_settings_link(array $links): array {
    $url = add_query_arg(['page' => 'update-automate', 'tab' => 'settings'], admin_url('tools.php'));
    $links[] = sprintf(
        '<a href="%s" aria-label="%s">%s</a>',
        esc_url($url),
        esc_attr__('Open Update Automate settings', 'update-automate'),
        esc_html__('Settings', 'update-automate')
    );

    return $links;
}

add_filter('plugin_row_meta', 'updateautomate_plugin_row_meta', 10, 2);
/**
 * Adds Changelog, Docs, Support to plugin row meta for Update Automate.
 *
 * @param array<int, string> $links Existing row meta.
 * @param string             $file  Plugin basename.
 * @return array<int, string> Row meta.
 */
function updateautomate_plugin_row_meta(array $links, string $file): array {
    if ($file === plugin_basename(updateautomate_PLUGIN_FILE)) {
        $extra_links = [
            sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url('https://wordpress.org/plugins/update-automate/#developers'),
                esc_attr__('View Update Automate changelog on WordPress.org (opens in a new tab)', 'update-automate'),
                esc_html__('Changelog', 'update-automate')
            ),
            sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url('https://holdmywp.com/update-automate/'),
                esc_attr__('Read Update Automate documentation (opens in a new tab)', 'update-automate'),
                esc_html__('Docs', 'update-automate')
            ),
            sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url('https://buymeacoffee.com/quentinld'),
                esc_attr__('Support the development of Update Automate (opens in a new tab)', 'update-automate'),
                esc_html__('Support', 'update-automate') . ' ☕'
            )
        ];
        $links = array_merge($links, $extra_links);
    }

    return $links;
}
