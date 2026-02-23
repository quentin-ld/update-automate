<?php

/**
 * Manages scheduled tasks for log cleanup.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron jobs for log retention cleanup.
 */
final class UpdateAutomate_Cron {
    /**
     * Cron hook name.
     *
     * @var string
     */
    public const HOOK_CLEANUP = 'updateautomate_cleanup_logs';

    /**
     * Register cron schedule and hook.
     *
     * @return void
     */
    public static function register(): void {
        add_action(self::HOOK_CLEANUP, [self::class, 'run_cleanup']);
        add_action('init', [self::class, 'schedule_if_needed']);
    }

    /**
     * Schedule daily cleanup if not already scheduled.
     *
     * @return void
     */
    public static function schedule_if_needed(): void {
        if (wp_next_scheduled(self::HOOK_CLEANUP)) {
            return;
        }

        wp_schedule_event(time(), 'daily', self::HOOK_CLEANUP);
    }

    /**
     * Run cleanup: delete logs older than retention days.
     *
     * @return void
     */
    public static function run_cleanup(): void {
        $days = updateautomate_get_settings()['retention_days'];
        if ($days < 1) {
            return;
        }

        UpdateAutomate_Logger::delete_older_than($days);
    }

    /**
     * Unschedule the cleanup event (e.g. on deactivation).
     *
     * @return void
     */
    public static function unschedule(): void {
        wp_clear_scheduled_hook(self::HOOK_CLEANUP);
    }
}
