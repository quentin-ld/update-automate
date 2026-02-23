<?php

/**
 * Optional email notifications for important updates or errors.
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Send email when critical updates occur or errors happen.
 */
final class UpdatesControl_Notifications {
    /**
     * Register hooks: after a log is written, optionally send email.
     *
     * @return void
     */
    public static function register(): void {
        add_action('updatescontrol_after_log', [self::class, 'maybe_send_notification'], 10, 2);
    }

    /**
     * Check if notifications are enabled and send email for errors or important updates.
     *
     * @param int $log_id Log ID.
     * @param array<string, mixed> $data Log data (type, status, item_name, etc.).
     * @return void
     */
    public static function maybe_send_notification(int $log_id, array $data): void {
        $enabled = (bool) get_option('updatescontrol_notify_enabled', false);
        if (!$enabled) {
            return;
        }

        $emails = get_option('updatescontrol_notify_emails', '');
        if ($emails === '') {
            return;
        }

        $recipients = array_filter(array_map('sanitize_email', explode(',', $emails)));
        if ($recipients === []) {
            return;
        }

        $notify_on = (array) get_option('updatescontrol_notify_on', ['error']);
        $status = $data['status'] ?? 'success';
        $log_type = $data['log_type'] ?? 'plugin';

        $should_send = false;
        if (in_array('error', $notify_on, true) && $status === 'error') {
            $should_send = true;
        }
        if (in_array('core', $notify_on, true) && $log_type === 'core') {
            $should_send = true;
        }
        if (in_array('all', $notify_on, true)) {
            $should_send = true;
        }

        if (!$should_send) {
            return;
        }

        self::send_email($recipients, $data);
    }

    /**
     * Send notification email.
     *
     * @param array<string> $recipients Email addresses.
     * @param array<string, mixed> $data Log entry data.
     * @return void
     */
    private static function send_email(array $recipients, array $data): void {
        $subject = sprintf(
            '[%s] %s: %s %s',
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            __('Updates Control', 'updates-control'),
            $data['status'] ?? 'success',
            $data['item_name'] ?? __('Update', 'updates-control')
        );

        /* translators: 1: log type, 2: action type, 3: item name, 4: version before, 5: version after, 6: status, 7: message, 8: created date/time */
        $message = sprintf(
            __("Update log entry:\n\nType: %1\$s\nAction: %2\$s\nItem: %3\$s\nVersion: %4\$s → %5\$s\nStatus: %6\$s\nMessage: %7\$s\n\nTime: %8\$s\n", 'updates-control'),
            $data['log_type'] ?? '',
            $data['action_type'] ?? '',
            $data['item_name'] ?? '',
            $data['version_before'] ?? '',
            $data['version_after'] ?? '',
            $data['status'] ?? '',
            $data['message'] ?? '',
            $data['created_at'] ?? current_time('mysql')
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        wp_mail($recipients, $subject, $message, $headers);
    }
}
