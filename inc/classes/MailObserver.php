<?php

/**
 * Observe mail delivery failures and log them.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks into wp_mail failure events for audit logs.
 */
final class UpdateAutomate_MailObserver {
    /**
     * Register WordPress mail hooks.
     *
     * @return void
     */
    public static function register(): void {
        add_action('wp_mail_failed', [self::class, 'on_mail_failed'], 10, 1);
    }

    /**
     * Log a failed wp_mail call.
     *
     * @param \WP_Error $error Mail error.
     * @return void
     */
    public static function on_mail_failed(\WP_Error $error): void {
        if (!updateautomate_get_settings()['logging_enabled']) {
            return;
        }

        $message = self::build_message($error);
        $trace = UpdateAutomate_ErrorHandler::capture_trace();

        UpdateAutomate_Logger::log(
            'core',
            'failed',
            'Email Delivery',
            'wp_mail',
            '',
            '',
            'error',
            $message,
            $trace,
            'manual'
        );
    }

    /**
     * Build a compact text summary from WP_Error.
     *
     * @param \WP_Error $error Mail error.
     * @return string
     */
    private static function build_message(\WP_Error $error): string {
        $lines = ['wp_mail failed.'];
        $codes = $error->get_error_codes();

        foreach ($codes as $code) {
            $line = '- [' . $code . '] ' . $error->get_error_message($code);
            $data = $error->get_error_data($code);
            if (!empty($data)) {
                $json = wp_json_encode($data);
                if ($json !== false) {
                    $line .= ' | data: ' . $json;
                }
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
