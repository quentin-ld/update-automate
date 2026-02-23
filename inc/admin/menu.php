<?php

/**
 * Admin menu: Update Automate under Tools and Dashboard.
 *
 * @package updateautomate
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'updateautomate_add_option_page');
/**
 * Adds Update Automate under Tools and a link under Dashboard > Update Automate.
 *
 * @return void
 */
function updateautomate_add_option_page(): void {
    add_management_page(
        __('Update Automate options', 'update-automate'),
        __('Update Automate', 'update-automate'),
        'manage_options',
        'update-automate',
        'updateautomate_options_page'
    );
    add_submenu_page(
        'index.php',
        __('Update Automate', 'update-automate'),
        __('Manage updates', 'update-automate'),
        'manage_options',
        'update-automate',
        'updateautomate_options_page'
    );
}

/**
 * Outputs Update Automate settings page (shell; React app mounts in #updateautomate-settings).
 *
 * @return void
 */
function updateautomate_options_page(): void {
    $plugin_data = get_file_data(updateautomate_PLUGIN_FILE, ['Version' => 'Version'], 'plugin');
    $plugin_version = $plugin_data['Version'] ?? '';
    ?>
    <div class="wrap updateautomate-dashboard-wrap">
        <header class="updateautomate-header">
            <div class="updateautomate-header-title">
                <h1><?php echo esc_html__('Update Automate', 'update-automate'); ?></h1>
                <?php if ($plugin_version) { ?>
                    <p class="updateautomate-plugin-version">
                        <?php echo esc_html__('Version', 'update-automate') . ' ' . esc_html($plugin_version) . ' - '; ?>
                        <a href="https://wordpress.org/plugins/update-automate/#developers"
                           target="_blank"
                           rel="noopener noreferrer"
                           aria-label="<?php echo esc_attr__('View Update Automate changelog on WordPress.org (opens in a new tab)', 'update-automate'); ?>">
                            <?php echo esc_html__('What\'s new ?', 'update-automate'); ?>
                        </a>
                    </p>
                <?php } ?>
            </div>
            <div class="updateautomate-header-navigation">
                <a href="https://holdmywp.com/update-automate/"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr__('Read the Update Automate documentation (opens in a new tab)', 'update-automate'); ?>">
                    <?php echo esc_html__('Documentation', 'update-automate'); ?>
                </a>
                <a href="https://wordpress.org/plugins/update-automate/#reviews"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr__('Leave a review for Update Automate on WordPress.org (opens in a new tab)', 'update-automate'); ?>">
                    <?php echo esc_html__('Leave a review (helps a lot)', 'update-automate'); ?>
                </a>
                <a href="https://buymeacoffee.com/quentinld"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="components-button is-next-40px-default-size is-tertiary"
                   aria-label="<?php echo esc_attr__('Support development: Buy me a coffee (opens in a new tab)', 'update-automate'); ?>">
                    <?php echo esc_html__('Buy me a coffee', 'update-automate'); ?> <span aria-hidden="true">☕</span>
                </a>
            </div>
        </header>
        <main id="updateautomate-settings" class="updateautomate-settings">
            <div class="updateautomate-loading card">
                <div class="updateautomate-loading-body">
                    <p class="updateautomate-loading-text">
                        <?php echo esc_html__('Loading your Update Automate settings…', 'update-automate'); ?>
                    </p>
                </div>
            </div>
        </main>
        <footer class="updateautomate-footer">
            <div class="updateautomate-footer-title">
                <p>
                    <?php echo esc_html__('Made ', 'update-automate'); ?>
                    <span aria-hidden="true"> x ❤️ </span>
                    <?php echo esc_html__(' by Quentin Le Duff - Your WordPress Partner', 'update-automate'); ?>
                </p>
            </div>
            <div class="updateautomate-footer-navigation">
                <a href="https://holdmywp.com/"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr__('Visit the developer website', 'update-automate'); ?>">
                    <?php echo esc_html__('My place', 'update-automate'); ?>
                </a>
                <a href="https://github.com/quentin-ld/update-automate/"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr__('Review the code on Github', 'update-automate'); ?>">
                    <?php echo esc_html__('Update Automate code repository', 'update-automate'); ?>
                </a>
            </div>
        </footer>
    </div>
    <?php
}
