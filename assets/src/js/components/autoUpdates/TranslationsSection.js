/**
 * Translations auto-update toggle section.
 */

import { Icon, ToggleControl } from '@wordpress/components';
import { language as languageIcon } from '@wordpress/icons';
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
 * @param {Object}   props.translations      { auto_update }.
 * @param {Object}   props.constants         Constant info from API.
 * @param {Function} props.toggleTranslation (checked) => void.
 * @param {boolean}  props.busy
 */
export function TranslationsSection({
	translations,
	constants,
	toggleTranslation,
	busy,
}) {
	const locked = isSectionLocked(constants, 'translations');

	return (
		<div className="updateautomate-autoupdates-section">
			<h3 className="updateautomate-autoupdates-section-title">
				<Icon icon={languageIcon} size={24} />
				{__('Translations', 'update-automate')}
			</h3>
			<ConstantNotices
				constants={constants}
				sections={['translations']}
				lockingOnly
			/>
			<ToggleControl
				label={__('Automatic translation updates', 'update-automate')}
				help={__(
					'WordPress updates translations automatically by default. Turn this off to stop automatic translation downloads.',
					'update-automate'
				)}
				checked={translations.auto_update}
				onChange={(checked) => toggleTranslation(checked)}
				disabled={locked || busy}
			/>
		</div>
	);
}
