<?php

/**
 * Registers a single plugin option (JSON) for all settings. Logs live in a dedicated table.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Option key for the single JSON settings. */
const UPDATEAUTOMATE_OPTION_SETTINGS = 'updateautomate_settings';

/** Default settings (keys only; used when decoding). */
const UPDATEAUTOMATE_SETTINGS_DEFAULTS = [
    'logging_enabled' => true,
    'retention_days' => 90,
    'notify_enabled' => false,
    'notify_emails' => '',
    'notify_on' => [],
    'auto_update_translations' => true,
    'dismissed_constants' => [],
];

add_action('init', 'updateautomate_register_settings');

/**
 * Register the single plugin option (JSON-encoded settings).
 *
 * @return void
 */
function updateautomate_register_settings(): void {
    register_setting(
        'updateautomate',
        UPDATEAUTOMATE_OPTION_SETTINGS,
        [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'updateautomate_sanitize_settings_json',
            'show_in_rest' => false,
        ]
    );
}

/**
 * Get plugin settings from the single JSON option.
 *
 * @return array{logging_enabled: bool, retention_days: int, notify_enabled: bool, notify_emails: string, notify_on: array<string>, auto_update_translations: bool, dismissed_constants: array<string>}
 */
function updateautomate_get_settings(): array {
    $raw = get_option(UPDATEAUTOMATE_OPTION_SETTINGS, '');
    $decoded = [];
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
    }

    $defaults = UPDATEAUTOMATE_SETTINGS_DEFAULTS;
    $out = [
        'logging_enabled' => isset($decoded['logging_enabled']) ? (bool) $decoded['logging_enabled'] : $defaults['logging_enabled'],
        'retention_days' => isset($decoded['retention_days']) ? max(1, min(365, (int) $decoded['retention_days'])) : $defaults['retention_days'],
        'notify_enabled' => isset($decoded['notify_enabled']) ? (bool) $decoded['notify_enabled'] : $defaults['notify_enabled'],
        'notify_emails' => isset($decoded['notify_emails']) ? (string) $decoded['notify_emails'] : $defaults['notify_emails'],
        'notify_on' => updateautomate_normalize_notify_on($decoded['notify_on'] ?? $defaults['notify_on']),
        'auto_update_translations' => isset($decoded['auto_update_translations']) ? (bool) $decoded['auto_update_translations'] : $defaults['auto_update_translations'],
        'dismissed_constants' => isset($decoded['dismissed_constants']) && is_array($decoded['dismissed_constants'])
            ? array_values(array_filter($decoded['dismissed_constants'], 'is_string'))
            : $defaults['dismissed_constants'],
    ];

    return $out;
}

/**
 * Sanitize incoming settings (REST or form) into a JSON string for the option.
 *
 * @param mixed $value Raw value (array or JSON string).
 * @return string JSON string to store.
 */
function updateautomate_sanitize_settings_json(mixed $value): string {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        $value = [];
    }
    $allowed_notify = ['core', 'plugin', 'theme', 'translation', 'error', 'technical'];
    $out = [
        'logging_enabled' => (bool) ($value['logging_enabled'] ?? true),
        'retention_days' => max(1, min(365, (int) ($value['retention_days'] ?? 90))),
        'notify_enabled' => (bool) ($value['notify_enabled'] ?? false),
        'notify_emails' => updateautomate_sanitize_emails($value['notify_emails'] ?? ''),
        'notify_on' => array_values(array_intersect(
            array_filter((array) ($value['notify_on'] ?? []), 'is_string'),
            $allowed_notify
        )),
        'auto_update_translations' => (bool) ($value['auto_update_translations'] ?? true),
        'dismissed_constants' => array_values(array_filter((array) ($value['dismissed_constants'] ?? []), 'is_string')),
    ];
    $encoded = wp_json_encode($out);

    return $encoded !== false ? $encoded : '{}';
}

/**
 * Sanitize comma-separated email list.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function updateautomate_sanitize_emails(mixed $value): string {
    $emails = array_filter(array_map('sanitize_email', explode(',', (string) $value)));

    return implode(', ', $emails);
}

/**
 * Normalize notify_on for display (REST/localize). Expands legacy 'all' to all allowed keys.
 *
 * @param array<string>|mixed $notify_on Raw option value.
 * @return array<string>
 */
function updateautomate_normalize_notify_on(mixed $notify_on): array {
    $allowed = ['core', 'plugin', 'theme', 'translation', 'error', 'technical'];
    $arr = array_values(array_intersect(array_filter((array) $notify_on, 'is_string'), $allowed));
    if (in_array('all', (array) $notify_on, true)) {
        return $allowed;
    }

    return $arr;
}
