<?php

/**
 * Compatibility layer for WP Mail SMTP.
 *
 * The goal is to keep WordPress core update emails working when
 * WP Mail SMTP is active and using API mailers such as Brevo.
 *
 * We force immediate sending (no queue) for update emails so that:
 * - The email is sent in the same request as the update.
 * - The `wp_mail` filter runs with all plugins attached (e.g. WP Mail Catcher),
 *   so logging and catching work. When WP Mail SMTP sends from the queue it
 *   temporarily removes all wp_mail-related hooks, so loggers never see the mail.
 *
 * We also bridge WP Mail SMTP's send-failed action to `wp_mail_failed` so that
 * when API mailers (e.g. Brevo) fail, core only catches PHPMailer\PHPMailer\Exception
 * and not the generic Exception thrown by the plugin — loggers like WP Mail Catcher
 * still receive the failure via `wp_mail_failed`.
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
     * Uses priority 0 so we run before other listeners and reliably prevent
     * enqueueing of update emails.
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
            0,
            2
        );

        add_action(
            'wp_mail_smtp_mailcatcher_send_failed',
            [self::class, 'bridge_wp_mail_failed'],
            10,
            3
        );
    }

    /**
     * Fire wp_mail_failed when WP Mail SMTP's API send fails.
     *
     * API mailers (e.g. Brevo) throw generic Exception; core only catches
     * PHPMailer\PHPMailer\Exception, so wp_mail_failed never runs. This bridge
     * ensures loggers (e.g. WP Mail Catcher) still receive the failure.
     *
     * @param string $error_message Error message from the mailer.
     * @param object $mailcatcher   MailCatcherInterface (PHPMailer) instance.
     * @param string $mailer_slug   Mailer slug (e.g. 'sendinblue').
     * @return void
     */
    public static function bridge_wp_mail_failed(string $error_message, object $mailcatcher, string $mailer_slug): void {
        $mail_data = self::build_mail_data_from_mailcatcher($mailcatcher);
        $mail_data['phpmailer_exception_code'] = 0;
        do_action('wp_mail_failed', new \WP_Error('wp_mail_failed', $error_message, $mail_data));
    }

    /**
     * Build wp_mail-style mail_data array from a MailCatcher (PHPMailer) instance.
     *
     * @param object $phpmailer MailCatcherInterface with PHPMailer-style API.
     * @return array<string, mixed> to, subject, message, headers, attachments, embeds.
     */
    private static function build_mail_data_from_mailcatcher(object $phpmailer): array {
        $to = [];
        if (method_exists($phpmailer, 'getToAddresses')) {
            $addresses = $phpmailer->getToAddresses();
            if (is_array($addresses)) {
                foreach ($addresses as $addr) {
                    $email = isset($addr[0]) ? (string) $addr[0] : '';
                    if ($email !== '') {
                        $to[] = $email;
                    }
                }
            }
        }
        $subject = isset($phpmailer->Subject) ? (string) $phpmailer->Subject : '';
        $message = isset($phpmailer->Body) ? (string) $phpmailer->Body : '';
        $headers = [];
        if (method_exists($phpmailer, 'getCustomHeaders')) {
            $raw = $phpmailer->getCustomHeaders();
            if (is_array($raw)) {
                foreach ($raw as $h) {
                    if (isset($h[0], $h[1]) && $h[0] !== '' && $h[1] !== '') {
                        $headers[] = (string) $h[0] . ': ' . (string) $h[1];
                    }
                }
            }
        }
        $attachments = [];
        if (method_exists($phpmailer, 'getAttachments')) {
            $raw = $phpmailer->getAttachments();
            if (is_array($raw)) {
                foreach ($raw as $a) {
                    if (isset($a[0]) && is_string($a[0])) {
                        $attachments[] = $a[0];
                    }
                }
            }
        }

        return [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments,
            'embeds' => [],
        ];
    }

    /**
     * Decide whether WP Mail SMTP should enqueue an email or send it now.
     *
     * When the email originates from WordPress core and/or looks like an
     * automatic update notification, we force immediate sending so that
     * wp_mail runs in the same request and plugins like WP Mail Catcher can log it.
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

        $is_update_email = self::is_wordpress_update_email($wp_mail_args);

        if ($is_update_email) {
            return false;
        }

        return $should_enqueue;
    }

    /**
     * Detect if the email is a WordPress core/plugin/theme update notification.
     *
     * Uses subject/message patterns first (so we bypass queue even when initiator
     * is not set or wrong, e.g. in cron). Then uses WP Mail SMTP initiator type
     * when available for wp-core emails with update-like subject.
     *
     * @param array<string, mixed> $wp_mail_args Filtered `wp_mail` arguments.
     * @return bool
     */
    private static function is_wordpress_update_email(array $wp_mail_args): bool {
        $subject = isset($wp_mail_args['subject']) ? (string) $wp_mail_args['subject'] : '';
        $message = isset($wp_mail_args['message']) ? (string) $wp_mail_args['message'] : '';

        if (self::subject_or_message_looks_like_update($subject, $message)) {
            return true;
        }

        try {
            $instance = wp_mail_smtp();
            $initiator = $instance->get_wp_mail_initiator();
        } catch (Throwable $e) {
            return false;
        }

        if (!is_object($initiator) || !method_exists($initiator, 'get_type')) {
            return false;
        }

        if ($initiator->get_type() !== 'wp-core') {
            return false;
        }

        $is_update_related =
            stripos($subject, 'WordPress') !== false &&
            (stripos($subject, 'update') !== false || stripos($subject, 'updates') !== false);

        /**
         * Filter to fine-tune which core emails should bypass the queue.
         *
         * @param bool  $is_update_related Detected as update-related.
         * @param array $wp_mail_args       Filtered `wp_mail` arguments.
         */
        $is_update_related = (bool) apply_filters(
            'updateautomate_wp_mail_smtp_is_core_update_email',
            $is_update_related,
            $wp_mail_args
        );

        return $is_update_related;
    }

    /**
     * Check if subject or message body match WordPress update notification patterns.
     *
     * @param string $subject Email subject.
     * @param string $message Email body.
     * @return bool
     */
    private static function subject_or_message_looks_like_update(string $subject, string $message): bool {
        $has_wordpress = stripos($subject, 'WordPress') !== false || stripos($message, 'WordPress') !== false;
        $has_update = stripos($subject, 'update') !== false || stripos($subject, 'updates') !== false
            || stripos($message, 'update') !== false || stripos($message, 'updates') !== false;

        return $has_wordpress && $has_update;
    }
}
