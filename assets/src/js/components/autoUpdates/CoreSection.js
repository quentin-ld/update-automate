/**
 * Core auto-update mode section (minor / all / disabled).
 */

import { Icon, RadioControl } from '@wordpress/components';
import { dashboard as dashboardIcon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { ConstantNotices } from './ConstantNotices';

/**
 * @param {Object} constants Map from PHP.
 * @param {string} section   'core' | 'plugins' | 'themes' | 'translations'.
 * @return {boolean} True if the section is locked by a constant.
 */
function isSectionLocked(constants, section) {
	if (!constants) {
		return false;
	}
	return Object.values(constants).some(
		(info) => info.locks && info.value && info.affects.includes(section)
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.core        { mode, overridden_by_constant }.
 * @param {Object}   props.constants   Constant info from API.
 * @param {Function} props.setCoreMode (value) => void.
 * @param {boolean}  props.busy
 */
export function CoreSection({ core, constants, setCoreMode, busy }) {
	const locked =
		core.overridden_by_constant || isSectionLocked(constants, 'core');

	const options = [
		{
			label: __(
				'Minor releases only (default — e.g. 6.4.1 to 6.4.2)',
				'update-automate'
			),
			value: 'minor',
		},
		{
			label: __(
				'All releases — major and minor (e.g. 6.4 to 6.5)',
				'update-automate'
			),
			value: 'all',
		},
		{
			label: __(
				'Disabled — no automatic core updates',
				'update-automate'
			),
			value: 'disabled',
		},
	];

	return (
		<div className="updateautomate-autoupdates-section">
			<h3 className="updateautomate-autoupdates-section-title">
				<Icon icon={dashboardIcon} size={24} />
				{__('Core updates', 'update-automate')}
			</h3>
			<ConstantNotices
				constants={constants}
				sections={['core']}
				lockingOnly
			/>
			<RadioControl
				label={__('Core auto-update mode', 'update-automate')}
				selected={core.mode}
				options={options}
				onChange={(value) => setCoreMode(value)}
				disabled={locked || busy}
				help={
					locked
						? __(
								'A constant in your wp-config.php file controls this setting. To change it, edit that file directly.',
								'update-automate'
							)
						: ''
				}
			/>
		</div>
	);
}
