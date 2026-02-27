/**
 * @typedef {Object} ConstantInfo
 * @property {boolean}  defined Whether the constant is defined in wp-config.
 * @property {*}        value   The constant value.
 * @property {string[]} affects Sections affected ('core', 'plugins', etc.).
 * @property {boolean}  locks   Whether the constant locks the setting.
 */

import { memo } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const CONSTANT_DESCRIPTIONS = {
	WP_AUTO_UPDATE_CORE: __(
		'WP_AUTO_UPDATE_CORE is set in your wp-config.php file. It controls which core updates run automatically, and this setting cannot be changed here.',
		'update-automate'
	),
	AUTOMATIC_UPDATER_DISABLED: __(
		'AUTOMATIC_UPDATER_DISABLED is set to true in your wp-config.php file. All automatic updates are turned off.',
		'update-automate'
	),
	DISALLOW_FILE_MODS: __(
		'DISALLOW_FILE_MODS is set to true in your wp-config.php file. WordPress cannot change any files, so all automatic updates are blocked.',
		'update-automate'
	),
	DISABLE_WP_CRON: __(
		'DISABLE_WP_CRON is set to true in your wp-config.php file. Automatic updates use WP-Cron and will not run unless you have set up an external cron job.',
		'update-automate'
	),
};

/**
 * Renders warning notices for wp-config constants that affect auto-updates.
 *
 * @param {Object}                        props
 * @param {Object.<string, ConstantInfo>} props.constants         Map of constant name → info.
 * @param {string[]}                      props.sections          Sections to filter ('core', 'plugins', etc.).
 * @param {boolean}                       [props.lockingOnly]     When true, only show constants with locks=true.
 * @param {boolean}                       [props.dismissibleOnly] When true, only show constants with locks=false (dismissible).
 * @param {string[]}                      [props.dismissed]       List of dismissed constant names.
 * @param {Function}                      [props.onDismiss]       Called with constant name when dismissed.
 * @return {JSX.Element|null}
 */
export const ConstantNotices = memo(function ConstantNotices({
	constants,
	sections,
	lockingOnly = false,
	dismissibleOnly = false,
	dismissed = [],
	onDismiss,
}) {
	if (!constants || Object.keys(constants).length === 0) {
		return null;
	}

	const matchesFilter = (info) => {
		if (lockingOnly) {
			return info.locks;
		}
		if (dismissibleOnly) {
			return !info.locks;
		}
		return true;
	};

	const relevant = Object.entries(constants).filter(
		([name, info]) =>
			info.affects.some((s) => sections.includes(s)) &&
			matchesFilter(info) &&
			!dismissed.includes(name)
	);

	if (relevant.length === 0) {
		return null;
	}

	return relevant.map(([name, info]) => (
		<Notice
			key={name}
			status="warning"
			isDismissible={!info.locks}
			onDismiss={
				!info.locks && onDismiss ? () => onDismiss(name) : undefined
			}
		>
			<strong>{name}</strong>
			<br />
			{CONSTANT_DESCRIPTIONS[name] || name}
		</Notice>
	));
});
