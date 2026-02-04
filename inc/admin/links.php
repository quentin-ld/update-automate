<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('plugin_action_links_' . plugin_basename(updatescontrol_PLUGIN_FILE), 'updatescontrol_add_settings_link');
/**
 * Adds "Settings" to plugin action links on Plugins screen.
 *
 * @param array<int, string> $links Existing action links.
 * @return array<int, string> Action links with Settings added.
 */
function updatescontrol_add_settings_link(array $links): array {
    $url = admin_url('options-general.php?page=updates-control');
    $links[] = sprintf(
        '<a href="%s" aria-label="%s">%s</a>',
        esc_url($url),
        esc_attr__('Go to Updates Control settings page', 'updates-control'),
        esc_html__('Settings', 'updates-control')
    );

    return $links;
}

add_filter('plugin_row_meta', 'updatescontrol_plugin_row_meta', 10, 2);
/**
 * Adds Changelog, Docs, Support to plugin row meta for updatescontrol.
 *
 * @param array<int, string> $links Existing row meta.
 * @param string             $file  Plugin basename.
 * @return array<int, string> Row meta.
 */
function updatescontrol_plugin_row_meta(array $links, string $file): array {
    if ($file === plugin_basename(updatescontrol_PLUGIN_FILE)) {
        $extra_links = [
            sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url('https://wordpress.org/plugins/updatescontrol/#developers'),
                esc_attr__('View Updates Control changelog on WordPress.org (opens in a new tab)', 'updates-control'),
                esc_html__('Changelog', 'updates-control')
            ),
            sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url('https://holdmywp.com/updatescontrol/'),
                esc_attr__('Read Updates Control documentation (opens in a new tab)', 'updates-control'),
                esc_html__('Docs', 'updates-control')
            ),
            sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url('https://buymeacoffee.com/quentinld'),
                esc_attr__('Support Updates Control by buying a coffee (opens in a new tab)', 'updates-control'),
                esc_html__('Support ☕', 'updates-control')
            )
        ];
        $links = array_merge($links, $extra_links);
    }

    return $links;
}
