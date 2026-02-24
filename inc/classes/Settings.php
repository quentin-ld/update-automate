<?php

/**
 * Provides admin interface for viewing logs and configuring settings.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API and settings for logs and options.
 */
final class UpdateAutomate_Settings {
    /**
     * REST namespace.
     *
     * @var string
     */
    public const REST_NAMESPACE = 'updateautomate/v1';

    /**
     * Register REST routes and settings.
     *
     * @return void
     */
    public static function register(): void {
        add_action('rest_api_init', [self::class, 'register_rest_routes']);
    }

    /**
     * Register REST routes for logs and settings.
     *
     * @return void
     */
    public static function register_rest_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/logs', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'rest_get_logs'],
            'permission_callback' => [self::class, 'rest_can_manage_logs'],
            'args' => [
                'per_page' => [
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 200,
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'log_type' => [
                    'type' => 'string',
                    'enum' => ['', 'core', 'plugin', 'theme', 'translation'],
                ],
                'performed_as' => [
                    'type' => 'string',
                    'enum' => ['', 'manual', 'automatic', 'upload'],
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['', 'success', 'error', 'cancelled'],
                ],
                'site_id' => [
                    'type' => 'integer',
                    'default' => null,
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/logs/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'rest_delete_log'],
            'permission_callback' => [self::class, 'rest_can_manage_logs'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/logs/cleanup', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_cleanup_logs'],
            'permission_callback' => [self::class, 'rest_can_manage_logs'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/settings', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [self::class, 'rest_get_settings'],
                'permission_callback' => [self::class, 'rest_can_manage_logs'],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'rest_update_settings'],
                'permission_callback' => [self::class, 'rest_can_manage_logs'],
                'args' => [
                    'logging_enabled' => ['type' => 'boolean'],
                    'retention_days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                    'notify_enabled' => ['type' => 'boolean'],
                    'notify_emails' => ['type' => 'string'],
                    'notify_on' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['core', 'plugin', 'theme', 'translation', 'error', 'technical']],
                    ],
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auto-updates', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'rest_get_auto_updates'],
            'permission_callback' => [self::class, 'rest_can_manage_logs'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auto-updates/core', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_set_core_mode'],
            'permission_callback' => [self::class, 'rest_can_manage_logs'],
            'args' => [
                'mode' => [
                    'type' => 'string',
                    'enum' => ['all', 'minor', 'disabled'],
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auto-updates/plugin', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_toggle_plugin'],
            'permission_callback' => [self::class, 'rest_can_manage_logs'],
            'args' => [
                'plugin' => ['type' => 'string', 'required' => true],
                'enable' => ['type' => 'boolean', 'required' => true],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auto-updates/theme', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_toggle_theme'],
            'permission_callback' => [self::class, 'rest_can_manage_logs'],
            'args' => [
                'stylesheet' => ['type' => 'string', 'required' => true],
                'enable' => ['type' => 'boolean', 'required' => true],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auto-updates/translation', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_toggle_translation'],
            'permission_callback' => [self::class, 'rest_can_manage_logs'],
            'args' => [
                'enable' => ['type' => 'boolean', 'required' => true],
            ],
        ]);
    }

    /**
     * REST: Get plugin settings.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_get_settings(\WP_REST_Request $request): WP_REST_Response {
        $options = updateautomate_get_settings();

        return new WP_REST_Response(['options' => $options], 200);
    }

    /**
     * Permission callback: user can manage options.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return bool
     */
    public static function rest_can_manage_logs(\WP_REST_Request $request): bool {
        return UpdateAutomate_Security::user_can_manage_logs();
    }

    /**
     * REST: Get logs list.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_get_logs(\WP_REST_Request $request): WP_REST_Response {
        $args = [
            'per_page' => $request->get_param('per_page'),
            'page' => $request->get_param('page'),
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        if ($request->get_param('log_type') !== '') {
            $args['log_type'] = $request->get_param('log_type');
        }
        if ($request->get_param('status') !== '') {
            $args['status'] = $request->get_param('status');
        }
        if ($request->get_param('site_id') !== null) {
            $args['site_id'] = $request->get_param('site_id');
        }
        if ($request->get_param('performed_as') !== '') {
            $args['performed_as'] = $request->get_param('performed_as');
        }

        $logs = UpdateAutomate_Logger::get_logs($args);
        $total = UpdateAutomate_Logger::get_logs_count($args);

        $user_ids = array_unique(array_filter(array_map(
            static fn (object $log): int => (int) ($log->user_id ?? 0),
            $logs
        )));
        if ($user_ids !== []) {
            cache_users($user_ids);
        }

        $logs = array_map([self::class, 'enrich_log_for_display'], $logs);

        return new WP_REST_Response([
            'logs' => $logs,
            'total' => $total,
        ], 200);
    }

    /**
     * Add performed_by_display and user_edit_link to a log object for the UI.
     *
     * @param object $log Log row object from get_logs().
     * @return object Same object with performed_by_display and user_edit_link added.
     */
    public static function enrich_log_for_display(object $log): object {
        $user_id = (int) ($log->user_id ?? 0);
        $performed_by = $log->performed_by ?? 'system';

        if ($performed_by === 'system' || $user_id <= 0) {
            $log->performed_by_display = __('System', 'update-automate');
            $log->user_edit_link = '';
        } else {
            $user = get_userdata($user_id);
            /* translators: %d: WordPress user ID when display name is not available */
            $log->performed_by_display = $user ? $user->display_name : sprintf(__('User #%d', 'update-automate'), $user_id);
            $log->user_edit_link = get_edit_user_link($user_id) ?: '';
        }

        $performed_as = $log->performed_as ?? 'manual';
        if ($performed_as === 'automatic') {
            $log->action_display = __('Automatic', 'update-automate');
        } elseif ($performed_as === 'upload') {
            $log->action_display = __('File upload', 'update-automate');
        } else {
            $log->action_display = __('Manual', 'update-automate');
        }

        return $log;
    }

    /**
     * REST: Delete single log.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_delete_log(\WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $deleted = UpdateAutomate_Logger::delete_log($id);

        if (!$deleted) {
            return new WP_REST_Response(['message' => __('Failed to delete log.', 'update-automate')], 500);
        }

        return new WP_REST_Response(['deleted' => true], 200);
    }

    /**
     * REST: Run cleanup (delete old logs by retention setting).
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_cleanup_logs(\WP_REST_Request $request): WP_REST_Response {
        $settings = updateautomate_get_settings();
        $deleted = UpdateAutomate_Logger::delete_older_than($settings['retention_days']);

        return new WP_REST_Response(['deleted' => $deleted], 200);
    }

    /**
     * REST: Update plugin settings.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_update_settings(\WP_REST_Request $request): WP_REST_Response {
        $current = updateautomate_get_settings();
        $next = [
            'logging_enabled' => $request->has_param('logging_enabled') ? (bool) $request->get_param('logging_enabled') : $current['logging_enabled'],
            'retention_days' => $request->has_param('retention_days') ? max(1, min(365, (int) $request->get_param('retention_days'))) : $current['retention_days'],
            'notify_enabled' => $request->has_param('notify_enabled') ? (bool) $request->get_param('notify_enabled') : $current['notify_enabled'],
            'notify_emails' => $request->has_param('notify_emails') ? updateautomate_sanitize_emails($request->get_param('notify_emails')) : $current['notify_emails'],
            'notify_on' => $request->has_param('notify_on') && is_array($request->get_param('notify_on'))
                ? array_values(array_intersect(array_filter($request->get_param('notify_on'), 'is_string'), ['core', 'plugin', 'theme', 'translation', 'error', 'technical']))
                : $current['notify_on'],
        ];
        $json = wp_json_encode($next);
        if ($json !== false) {
            update_option(UPDATEAUTOMATE_OPTION_SETTINGS, $json);
        }

        return new WP_REST_Response(['options' => updateautomate_get_settings()], 200);
    }

    /**
     * REST: Get all auto-update data (constants, core, plugins, themes, translations).
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_get_auto_updates(\WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(UpdateAutomate_AutoUpdates::get_data(), 200);
    }

    /**
     * REST: Set core auto-update mode.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_set_core_mode(\WP_REST_Request $request): WP_REST_Response {
        $mode = sanitize_key($request->get_param('mode'));
        $ok = UpdateAutomate_AutoUpdates::set_core_mode($mode);

        if (!$ok) {
            return new WP_REST_Response([
                'message' => __('Core auto-update mode is overridden by a wp-config constant.', 'update-automate'),
            ], 403);
        }

        return new WP_REST_Response(UpdateAutomate_AutoUpdates::get_data(), 200);
    }

    /**
     * REST: Toggle auto-update for a single plugin.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_toggle_plugin(\WP_REST_Request $request): WP_REST_Response {
        $plugin = sanitize_text_field($request->get_param('plugin'));
        $enable = (bool) $request->get_param('enable');
        $ok = UpdateAutomate_AutoUpdates::toggle_plugin($plugin, $enable);

        if (!$ok) {
            return new WP_REST_Response([
                'message' => __('Plugin not found.', 'update-automate'),
            ], 404);
        }

        return new WP_REST_Response(UpdateAutomate_AutoUpdates::get_data(), 200);
    }

    /**
     * REST: Toggle auto-update for a single theme.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_toggle_theme(\WP_REST_Request $request): WP_REST_Response {
        $stylesheet = sanitize_text_field($request->get_param('stylesheet'));
        $enable = (bool) $request->get_param('enable');
        $ok = UpdateAutomate_AutoUpdates::toggle_theme($stylesheet, $enable);

        if (!$ok) {
            return new WP_REST_Response([
                'message' => __('Theme not found.', 'update-automate'),
            ], 404);
        }

        return new WP_REST_Response(UpdateAutomate_AutoUpdates::get_data(), 200);
    }

    /**
     * REST: Toggle translation auto-updates.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_toggle_translation(\WP_REST_Request $request): WP_REST_Response {
        $enable = (bool) $request->get_param('enable');
        UpdateAutomate_AutoUpdates::set_translations($enable);

        return new WP_REST_Response(UpdateAutomate_AutoUpdates::get_data(), 200);
    }
}
