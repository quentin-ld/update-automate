<?php

/**
 * Registers plugin options: logging, retention, notifications (REST + sanitize).
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'updatescontrol_register_settings');

/**
 * Register options for the Updates Control plugin.
 *
 * @return void
 */
function updatescontrol_register_settings(): void {
    register_setting(
        'updatescontrol',
        'updatescontrol_logging_enabled',
        [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'updatescontrol_sanitize_bool',
            'show_in_rest' => true,
        ]
    );

    register_setting(
        'updatescontrol',
        'updatescontrol_retention_days',
        [
            'type' => 'integer',
            'default' => 90,
            'sanitize_callback' => 'updatescontrol_sanitize_retention_days',
            'show_in_rest' => true,
        ]
    );

    register_setting(
        'updatescontrol',
        'updatescontrol_notify_enabled',
        [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'updatescontrol_sanitize_bool',
            'show_in_rest' => true,
        ]
    );

    register_setting(
        'updatescontrol',
        'updatescontrol_notify_emails',
        [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'updatescontrol_sanitize_emails',
            'show_in_rest' => true,
        ]
    );

    register_setting(
        'updatescontrol',
        'updatescontrol_notify_on',
        [
            'type' => 'array',
            'default' => ['error'],
            'sanitize_callback' => 'updatescontrol_sanitize_notify_on',
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => ['error', 'core', 'all']],
                ],
            ],
        ]
    );
}

/**
 * Sanitize boolean option.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function updatescontrol_sanitize_bool(mixed $value): bool {
    return (bool) $value;
}

/**
 * Sanitize retention days (1–365).
 *
 * @param mixed $value Raw value.
 * @return int
 */
function updatescontrol_sanitize_retention_days(mixed $value): int {
    $v = (int) $value;

    return max(1, min(365, $v));
}

/**
 * Sanitize comma-separated email list.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function updatescontrol_sanitize_emails(mixed $value): string {
    $emails = array_filter(array_map('sanitize_email', explode(',', (string) $value)));

    return implode(', ', $emails);
}

/**
 * Sanitize notify_on array.
 *
 * @param mixed $value Raw value.
 * @return array<string>
 */
function updatescontrol_sanitize_notify_on(mixed $value): array {
    $allowed = ['error', 'core', 'all'];
    $arr = array_values(array_intersect(array_filter((array) $value, 'is_string'), $allowed));

    return $arr !== [] ? $arr : ['error'];
}
