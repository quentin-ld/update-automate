<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds Updates Control under Tools and a link under Dashboard > Update controls.
 */
add_action('admin_menu', 'updatescontrol_add_option_page');
function updatescontrol_add_option_page(): void {
    add_management_page(
        __('Updates Control options', 'updates-control'),
        __('Updates Control', 'updates-control'),
        'manage_options',
        'updates-control',
        'updatescontrol_options_page'
    );
    add_submenu_page(
        'index.php',
        __('Updates Control', 'updates-control'),
        __('Update controls', 'updates-control'),
        'manage_options',
        'updates-control',
        'updatescontrol_options_page'
    );
}

/**
 * Outputs updatescontrol settings page (shell; React app mounts in #updatescontrol-settings).
 */
function updatescontrol_options_page(): void {
    $plugin_data = get_file_data(updatescontrol_PLUGIN_FILE, ['Version' => 'Version'], 'plugin');
    $plugin_version = $plugin_data['Version'] ?? '';
    ?>
    <div class="wrap updatescontrol-dashboard-wrap">
        <header class="updatescontrol-header">
            <div class="updatescontrol-header-title">
                <h1><?php echo esc_html__('Updates Control', 'updates-control'); ?></h1>
                <?php if ($plugin_version) { ?>
                    <p class="updatescontrol-plugin-version">
                        <?php echo esc_html__('Version', 'updates-control') . ' ' . esc_html($plugin_version) . ' - '; ?>
                        <a href="https://wordpress.org/plugins/updatescontrol/#developers"
                           target="_blank"
                           rel="noopener noreferrer"
                           aria-label="<?php echo esc_attr__('View Updates Control changelog on WordPress.org (opens in a new tab)', 'updates-control'); ?>">
                            <?php echo esc_html__('What\'s new ?', 'updates-control'); ?>
                        </a>
                    </p>
                <?php } ?>
            </div>
            <div class="updatescontrol-header-navigation">
                <a href="https://holdmywp.com/updatescontrol/"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr__('Read the Updates Control documentation (opens in a new tab)', 'updates-control'); ?>">
                    <?php echo esc_html__('Documentation', 'updates-control'); ?>
                </a>
                <a href="https://wordpress.org/plugins/updatescontrol/#reviews"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr__('Leave a review for Updates Control on WordPress.org (opens in a new tab)', 'updates-control'); ?>">
                    <?php echo esc_html__('Leave a review (helps a lot)', 'updates-control'); ?>
                </a>
                <a href="https://buymeacoffee.com/quentinld"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="components-button is-next-40px-default-size is-tertiary"
                   aria-label="<?php echo esc_attr__('Support development: Buy me a coffee (opens in a new tab)', 'updates-control'); ?>">
                    <?php echo esc_html__('Buy me a coffee', 'updates-control'); ?> <span aria-hidden="true">☕</span>
                </a>
            </div>
        </header>
        <main id="updatescontrol-settings" class="updatescontrol-settings">
            <div class="updatescontrol-loading card">
                <div class="updatescontrol-loading-body">
                    <p class="updatescontrol-loading-text">
                        <?php echo esc_html__('Loading your Updates Control settings…', 'updates-control'); ?>
                    </p>
                </div>
            </div>
        </main>
        <footer class="updatescontrol-footer">
            <div class="updatescontrol-footer-title">
                <p>
                    <?php echo esc_html__('Made ', 'updates-control'); ?>
                    <span aria-hidden="true"> x ❤️ </span>
                    <?php echo esc_html__(' by Quentin Le Duff - Your WordPress Partner', 'updates-control'); ?>
                </p>
            </div>
            <div class="updatescontrol-footer-navigation">
                <a href="https://holdmywp.com/"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr__('Visit the developer website', 'updates-control'); ?>">
                    <?php echo esc_html__('My place', 'updates-control'); ?>
                </a>
                <a href="https://github.com/quentin-ld/updatescontrol"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr__('Review the code on Github', 'updates-control'); ?>">
                    <?php echo esc_html__('Updates Control code repository', 'updates-control'); ?>
                </a>
            </div>
        </footer>
    </div>
    <?php
}
