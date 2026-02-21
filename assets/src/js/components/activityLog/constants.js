/**
 * Activity log DataView constants.
 *
 * Activity layout options follow the view API:
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 */
import { __ } from '@wordpress/i18n';

export const LAYOUT_ACTIVITY = 'activity';

export const ACTION_LABELS = {
	update: __('Update', 'updatescontrol'),
	downgrade: __('Downgrade', 'updatescontrol'),
	install: __('Install', 'updatescontrol'),
	same_version: __('Reset', 'updatescontrol'),
	failed: __('Failed', 'updatescontrol'),
	uninstall: __('Uninstall', 'updatescontrol'),
	delete: __('Delete', 'updatescontrol'),
};
