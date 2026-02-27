<?php

if (!defined('ABSPATH')) {
    exit;
}

define('UPDATEAUTOMATE_VERSION', '0.1');
define('updateautomate_PLUGIN_FILE', '');
define('updateautomate_PLUGIN_DIR', '');

if (!defined('DB_NAME')) {
    define('DB_NAME', '');
}

if (!function_exists('wp_mail_smtp')) {
    /**
     * Stub for the WP Mail SMTP global helper (static analysis only).
     *
     * @return object
     */
    function wp_mail_smtp(): object {
        return (object) [];
    }
}
