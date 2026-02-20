/**
 * Activity log DataView constants.
 *
 * Activity layout options follow the view API:
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 * - fullWidth: when false, the list wrapper is constrained (max-width); when true, full width.
 * - groupBy: { field, direction, showLabel } applied to view and defaultLayouts.activity.
 */
import { __ } from '@wordpress/i18n';

export const LAYOUT_ACTIVITY = 'activity';

/** Activity layout settings (fullWidth, groupBy per DataViews view API). */
export const ACTIVITY_LAYOUT_CONFIG = {
	fullWidth: true,
	groupBy: {
		field: 'dateGroup',
		direction: 'desc',
		showLabel: true,
	},
};

export const ACTION_LABELS = {
	update: __('Update', 'updatescontrol'),
	downgrade: __('Downgrade', 'updatescontrol'),
	install: __('Install', 'updatescontrol'),
	same_version: __('Reset', 'updatescontrol'),
	failed: __('Failed', 'updatescontrol'),
	uninstall: __('Uninstall', 'updatescontrol'),
	delete: __('Delete', 'updatescontrol'),
};
