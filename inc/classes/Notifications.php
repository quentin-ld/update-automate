<?php

/**
 * Redirect native WordPress update notification emails to a custom recipient.
 *
 * When email notifications are enabled, the native admin update emails
 * (core, plugin, theme) are sent to the configured recipient instead of admin_email.
 * No custom emails are sent; only WordPress core email behaviour is redirected.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Redirect native WordPress update emails to the plugin's recipient.
 */
final class UpdateAutomate_Notifications {
    /**
     * Register filters for native WordPress update notification emails.
     *
     * @return void
     */
    public static function register(): void {
        add_filter('send_core_update_notification_email', [self::class, 'filter_send_core_update_notification_email'], 10, 2);
        add_filter('auto_core_update_send_email', [self::class, 'filter_core_send_email'], 10, 4);
        add_filter('auto_core_update_email', [self::class, 'filter_core_email_to'], 10, 4);
        add_filter('auto_plugin_update_send_email', [self::class, 'filter_plugin_send_email'], 10, 2);
        add_filter('auto_theme_update_send_email', [self::class, 'filter_theme_send_email'], 10, 2);
        add_filter('auto_plugin_theme_update_email', [self::class, 'filter_plugin_theme_email_to'], 10, 4);
        add_filter('automatic_updates_send_debug_email', [self::class, 'filter_should_send_debug_email'], 10, 1);
        add_filter('automatic_updates_debug_email', [self::class, 'filter_debug_email_to'], 10, 3);
        add_filter('recovery_mode_email', [self::class, 'filter_recovery_mode_email_to'], 10, 2);
    }

    /**
     * Whether notifications are enabled and a valid recipient is set.
     *
     * @return bool
     */
    private static function should_redirect(): bool {
        $s = updateautomate_get_settings();
        if (!$s['notify_enabled']) {
            return false;
        }
        if ($s['notify_emails'] === '') {
            return false;
        }
        $recipients = array_filter(array_map('sanitize_email', explode(',', $s['notify_emails'])));

        return $recipients !== [];
    }

    /**
     * Get recipient email(s). First address only if single, else array for wp_mail().
     *
     * @return string|array<string>
     */
    private static function get_recipient(): string|array {
        $emails = updateautomate_get_settings()['notify_emails'];
        $recipients = array_values(array_filter(array_map('sanitize_email', explode(',', $emails))));
        if ($recipients === []) {
            return '';
        }

        return count($recipients) === 1 ? $recipients[0] : $recipients;
    }

    /**
     * Get notify_on option.
     *
     * @return array<string>
     */
    private static function get_notify_on(): array {
        return updateautomate_get_settings()['notify_on'];
    }

    /**
     * Whether notify_on includes a key.
     *
     * @param string $key Setting key.
     * @return bool
     */
    private static function has_notify(string $key): bool {
        return in_array($key, self::get_notify_on(), true);
    }

    /**
     * Whether update results contain at least one failure.
     *
     * @param array<int, object> $update_results Update results.
     * @return bool
     */
    private static function has_failed_updates(array $update_results): bool {
        foreach ($update_results as $item) {
            if (!isset($item->result)) {
                continue;
            }
            if ($item->result !== true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Only send core update email when "core" is in notify_on; for fail/critical also require "error".
     *
     * @param bool   $send        Whether to send. Default true.
     * @param string $type        success, fail, manual, critical.
     * @param object $core_update The update offer.
     * @param mixed  $result      The result.
     * @return bool
     */
    public static function filter_core_send_email(bool $send, string $type, mixed $core_update, mixed $result): bool {
        if (!updateautomate_get_settings()['notify_enabled']) {
            return $send;
        }

        if (!$send) {
            return false;
        }

        if (in_array($type, ['fail', 'critical'], true)) {
            return self::has_notify('core') || self::has_notify('error');
        }

        return self::has_notify('core');
    }

    /**
     * Decide whether to send native "WordPress update available" manual notifications.
     *
     * @param bool  $notify Whether WordPress would send this notification.
     * @param mixed $item   Core update item.
     * @return bool
     */
    public static function filter_send_core_update_notification_email(bool $notify, mixed $item): bool {
        if (!updateautomate_get_settings()['notify_enabled']) {
            return $notify;
        }

        if (!$notify) {
            return false;
        }

        return self::has_notify('core');
    }

    /**
     * Redirect core update email to plugin recipient when notifications enabled.
     *
     * @param array<string, string> $email       to, subject, body, headers.
     * @param string               $type       success, fail, manual, critical.
     * @param object                $core_update The update offer.
     * @param mixed                 $result      The result.
     * @return array<string, string>
     */
    public static function filter_core_email_to(array $email, string $type, mixed $core_update, mixed $result): array {
        if (self::should_redirect()) {
            $email['to'] = self::get_recipient();
        }

        return $email;
    }

    /**
     * Only send plugin update email when "plugin" is in notify_on.
     *
     * @param bool               $enabled        Default true.
     * @param array<int, object> $update_results Plugin update results.
     * @return bool
     */
    public static function filter_plugin_send_email(bool $enabled, array $update_results): bool {
        if (!updateautomate_get_settings()['notify_enabled']) {
            return $enabled;
        }

        if (!$enabled) {
            return false;
        }

        if (self::has_notify('plugin')) {
            return true;
        }

        return self::has_notify('error') && self::has_failed_updates($update_results);
    }

    /**
     * Only send theme update email when "theme" is in notify_on.
     *
     * @param bool               $enabled        Default true.
     * @param array<int, object> $update_results Theme update results.
     * @return bool
     */
    public static function filter_theme_send_email(bool $enabled, array $update_results): bool {
        if (!updateautomate_get_settings()['notify_enabled']) {
            return $enabled;
        }

        if (!$enabled) {
            return false;
        }

        if (self::has_notify('theme')) {
            return true;
        }

        return self::has_notify('error') && self::has_failed_updates($update_results);
    }

    /**
     * Redirect plugin/theme update email to plugin recipient when notifications enabled.
     *
     * @param array<string, string>              $email             to, subject, body, headers.
     * @param string                             $type              success, fail, mixed.
     * @param array<string, array<int, object>> $successful_updates Successful updates.
     * @param array<string, array<int, object>> $failed_updates     Failed updates.
     * @return array<string, string>
     */
    public static function filter_plugin_theme_email_to(array $email, string $type, array $successful_updates, array $failed_updates): array {
        if (self::should_redirect()) {
            $email['to'] = self::get_recipient();
        }

        return $email;
    }

    /**
     * Enable debug email when translation/error notifications require it.
     *
     * @param bool $development_version WordPress default for debug mail sending.
     * @return bool
     */
    public static function filter_should_send_debug_email(bool $development_version): bool {
        if (!$development_version && !self::should_redirect()) {
            return false;
        }

        if (self::has_notify('translation') || self::has_notify('error')) {
            return true;
        }

        return $development_version;
    }

    /**
     * Redirect debug email (includes translation results) to plugin recipient when "translation" in notify_on.
     * The debug email is sent in development versions and includes core, plugin, theme, and translation results.
     *
     * @param array<string, string>              $email   to, subject, body, headers.
     * @param int                                $failures Number of failures.
     * @param array<string, array<int, object>> $results  All update results.
     * @return array<string, string>
     */
    public static function filter_debug_email_to(array $email, int $failures, array $results): array {
        if (!self::should_redirect()) {
            return $email;
        }

        $has_translation = !empty($results['translation']);
        $should_route = false;

        if (self::has_notify('translation') && $has_translation) {
            $should_route = true;
        }
        if (self::has_notify('error') && $failures > 0) {
            $should_route = true;
        }
        if ($should_route) {
            $email['to'] = self::get_recipient();
        }

        return $email;
    }

    /**
     * Redirect WordPress recovery-mode technical email to plugin recipient.
     *
     * @param array<string, mixed> $email Recovery-mode email payload.
     * @param string               $url   Recovery URL.
     * @return array<string, mixed>
     */
    public static function filter_recovery_mode_email_to(array $email, string $url): array {
        if (!self::should_redirect()) {
            return $email;
        }
        if (!self::has_notify('technical') && !self::has_notify('error')) {
            return $email;
        }
        $email['to'] = self::get_recipient();

        return $email;
    }
}
