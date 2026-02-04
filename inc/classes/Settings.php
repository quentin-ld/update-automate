<?php

/**
 * Provides admin interface for viewing logs and configuring settings.
 *
 * @package updatescontrol
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API and settings for logs and options.
 */
final class UpdatesControl_Settings {
    /**
     * REST namespace.
     *
     * @var string
     */
    public const REST_NAMESPACE = 'updatescontrol/v1';

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
                    'enum' => ['', 'core', 'plugin', 'theme'],
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
                        'items' => ['type' => 'string', 'enum' => ['error', 'core', 'all']],
                    ],
                ],
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
        return new WP_REST_Response([
            'options' => [
                'logging_enabled' => (bool) get_option('updatescontrol_logging_enabled', true),
                'retention_days' => (int) get_option('updatescontrol_retention_days', 90),
                'notify_enabled' => (bool) get_option('updatescontrol_notify_enabled', false),
                'notify_emails' => (string) get_option('updatescontrol_notify_emails', ''),
                'notify_on' => (array) get_option('updatescontrol_notify_on', ['error']),
            ],
        ], 200);
    }

    /**
     * Permission callback: user can manage options.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return bool
     */
    public static function rest_can_manage_logs(\WP_REST_Request $request): bool {
        return UpdatesControl_Security::user_can_manage_logs();
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

        $logs = UpdatesControl_Logger::get_logs($args);
        $total = UpdatesControl_Logger::get_logs_count($args);

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
            $log->performed_by_display = __('System', 'updates-control');
            $log->user_edit_link = '';
        } else {
            $user = get_userdata($user_id);
            /* translators: %d: WordPress user ID when display name is not available */
            $log->performed_by_display = $user ? $user->display_name : sprintf(__('User #%d', 'updates-control'), $user_id);
            $log->user_edit_link = get_edit_user_link($user_id) ?: '';
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
        $deleted = UpdatesControl_Logger::delete_log($id);

        if (!$deleted) {
            return new WP_REST_Response(['message' => __('Failed to delete log.', 'updates-control')], 500);
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
        $days = (int) get_option('updatescontrol_retention_days', 90);
        $deleted = UpdatesControl_Logger::delete_older_than($days);

        return new WP_REST_Response(['deleted' => $deleted], 200);
    }

    /**
     * REST: Update plugin settings.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request Request.
     * @return WP_REST_Response
     */
    public static function rest_update_settings(\WP_REST_Request $request): WP_REST_Response {
        $options = [
            'updatescontrol_logging_enabled' => 'logging_enabled',
            'updatescontrol_retention_days' => 'retention_days',
            'updatescontrol_notify_enabled' => 'notify_enabled',
            'updatescontrol_notify_emails' => 'notify_emails',
            'updatescontrol_notify_on' => 'notify_on',
        ];
        foreach ($options as $option_name => $param) {
            if ($request->has_param($param)) {
                $value = $request->get_param($param);
                if ($param === 'retention_days') {
                    $value = max(1, min(365, (int) $value));
                }
                if ($param === 'notify_on' && is_array($value)) {
                    $value = array_values(array_intersect($value, ['error', 'core', 'all']));
                }
                update_option($option_name, $value);
            }
        }

        return new WP_REST_Response([
            'options' => [
                'logging_enabled' => (bool) get_option('updatescontrol_logging_enabled', true),
                'retention_days' => (int) get_option('updatescontrol_retention_days', 90),
                'notify_enabled' => (bool) get_option('updatescontrol_notify_enabled', false),
                'notify_emails' => (string) get_option('updatescontrol_notify_emails', ''),
                'notify_on' => (array) get_option('updatescontrol_notify_on', ['error']),
            ],
        ], 200);
    }
}
