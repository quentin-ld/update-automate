/**
 * Activity log DataView constants.
 *
 * Activity layout options follow the view API:
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 */
import { __ } from '@wordpress/i18n';

export const LAYOUT_ACTIVITY = 'activity';

export const ACTION_LABELS = {
	update: __('Update', 'update-automate'),
	downgrade: __('Rollback', 'update-automate'),
	install: __('Install', 'update-automate'),
	same_version: __('Reinstall', 'update-automate'),
	failed: __('Failed', 'update-automate'),
	uninstall: __('Uninstall', 'update-automate'),
	delete: __('Delete', 'update-automate'),
};
