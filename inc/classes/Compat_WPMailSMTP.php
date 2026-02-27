<?php

/**
 * Compatibility layer for WP Mail SMTP.
 *
 * The goal is to keep WordPress core update emails working when
 * WP Mail SMTP is active and using API mailers such as Brevo.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Mail SMTP compatibility for Update Automate.
 */
final class UpdateAutomate_Compat_WPMailSMTP {
    /**
     * Register compatibility hooks when WP Mail SMTP is available.
     *
     * @return void
     */
    public static function register(): void {
        if (!function_exists('wp_mail_smtp')) {
            return;
        }

        $enabled = apply_filters('updateautomate_wp_mail_smtp_compat_enabled', true);
        if ($enabled !== true) {
            return;
        }

        add_filter(
            'wp_mail_smtp_mail_catcher_send_enqueue_email',
            [self::class, 'maybe_bypass_queue_for_core_updates'],
            20,
            2
        );
    }

    /**
     * Decide whether WP Mail SMTP should enqueue an email or send it now.
     *
     * When the email originates from WordPress core and looks like an
     * automatic update notification, we force immediate sending to
     * reduce the chances of queue-related delivery issues with API mailers.
     *
     * @param bool                    $should_enqueue Whether WP Mail SMTP plans to enqueue.
     * @param array<string, mixed>    $wp_mail_args   Filtered `wp_mail` arguments.
     * @return bool
     */
    public static function maybe_bypass_queue_for_core_updates(bool $should_enqueue, array $wp_mail_args): bool {
        $should_enqueue = (bool) apply_filters(
            'updateautomate_wp_mail_smtp_should_enqueue_default',
            $should_enqueue,
            $wp_mail_args
        );

        if ($should_enqueue === false) {
            return false;
        }

        try {
            $instance = wp_mail_smtp();
            $initiator = $instance->get_wp_mail_initiator();
        } catch (Throwable $e) {
            return $should_enqueue;
        }

        if (!is_object($initiator) || !method_exists($initiator, 'get_type')) {
            return $should_enqueue;
        }

        $type = $initiator->get_type();

        if ($type !== 'wp-core') {
            return $should_enqueue;
        }

        $subject = isset($wp_mail_args['subject']) ? (string) $wp_mail_args['subject'] : '';

        $is_update_related =
            stripos($subject, 'WordPress') !== false &&
            (stripos($subject, 'update') !== false || stripos($subject, 'updates') !== false);

        /**
         * Filter to fine‑tune which core emails should bypass the queue.
         *
         * @param bool  $is_update_related Detected as update-related.
         * @param array $wp_mail_args      Filtered `wp_mail` arguments.
         */
        $is_update_related = (bool) apply_filters(
            'updateautomate_wp_mail_smtp_is_core_update_email',
            $is_update_related,
            $wp_mail_args
        );

        if ($is_update_related) {
            return false;
        }

        return $should_enqueue;
    }
}
