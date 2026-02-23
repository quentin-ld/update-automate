<?php

/**
 * Ensures secure database interactions and sanitization of inputs.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security helpers for sanitization, escaping, and capability checks.
 */
final class UpdateAutomate_Security {
    /**
     * Allowed log types.
     *
     * @var array<string>
     */
    public const ALLOWED_LOG_TYPES = ['core', 'plugin', 'theme', 'translation'];

    /**
     * Allowed action types.
     *
     * @var array<string>
     */
    public const ALLOWED_ACTION_TYPES = ['update', 'downgrade', 'install', 'same_version', 'failed', 'uninstall'];

    /**
     * Allowed status values.
     *
     * @var array<string>
     */
    public const ALLOWED_STATUSES = ['success', 'error', 'cancelled'];

    /**
     * Allowed performed_as values: manual, automatic, or file upload (update.php upload flow).
     *
     * @var array<string>
     */
    public const ALLOWED_PERFORMED_AS = ['manual', 'automatic', 'upload'];

    /** Update context: bulk (e.g. update-core iframe) or single (e.g. Plugins screen). Empty for core/translation/legacy. */
    public const ALLOWED_UPDATE_CONTEXT = ['bulk', 'single', ''];

    /**
     * Sanitize log type.
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function sanitize_log_type(string $value): string {
        $value = sanitize_key($value);

        return in_array($value, self::ALLOWED_LOG_TYPES, true) ? $value : 'plugin';
    }

    /**
     * Sanitize action type.
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function sanitize_action_type(string $value): string {
        $value = sanitize_key($value);

        return in_array($value, self::ALLOWED_ACTION_TYPES, true) ? $value : 'update';
    }

    /**
     * Sanitize status.
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function sanitize_status(string $value): string {
        $value = sanitize_key($value);

        return in_array($value, self::ALLOWED_STATUSES, true) ? $value : 'success';
    }

    /**
     * Sanitize performed_as (manual or automatic).
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function sanitize_performed_as(string $value): string {
        $value = sanitize_key($value);

        return in_array($value, self::ALLOWED_PERFORMED_AS, true) ? $value : 'manual';
    }

    /**
     * Sanitize update_context (bulk or single).
     *
     * @param string $value Raw value.
     * @return string 'bulk', 'single', or ''.
     */
    public static function sanitize_update_context(string $value): string {
        $value = sanitize_key($value);

        return in_array($value, self::ALLOWED_UPDATE_CONTEXT, true) ? $value : '';
    }

    /**
     * Sanitize string for DB storage (short).
     *
     * @param string $value Raw value.
     * @param int    $max_length Max length (default 255).
     * @return string
     */
    public static function sanitize_string(string $value, int $max_length = 255): string {
        $value = sanitize_text_field($value);

        return mb_substr($value, 0, $max_length);
    }

    /**
     * Sanitize message (long text). Decodes HTML entities (e.g. &#8230; → …) for readable logs.
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function sanitize_message(string $value): string {
        $value = wp_kses_post(wp_unslash($value));

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize version string.
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function sanitize_version(string $value): string {
        return mb_substr(preg_replace('/[^a-zA-Z0-9._-]/', '', $value) ?: '', 0, 64);
    }

    /**
     * Sanitize trace (call stack) for DB storage.
     *
     * @param string $value Raw trace.
     * @param int    $max_length Max length (default 65535).
     * @return string
     */
    public static function sanitize_trace(string $value, int $max_length = 65535): string {
        $value = wp_strip_all_tags($value);

        return mb_substr($value, 0, $max_length);
    }

    /**
     * Check if current user can manage update logs.
     *
     * @return bool
     */
    public static function user_can_manage_logs(): bool {
        return current_user_can('manage_options');
    }
}
