/**
 * Settings panel — logging and notification configuration.
 * Uses Gutenberg components: NumberControl, TextControl, ToggleControl, CheckboxControl.
 */

import { memo } from '@wordpress/element';
import {
	Button,
	ToggleControl,
	TextControl,
	CheckboxControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const NOTIFY_TYPES = [
	{ key: 'core', label: __('Core updates', 'update-automate') },
	{ key: 'plugin', label: __('Plugin updates', 'update-automate') },
	{ key: 'theme', label: __('Theme updates', 'update-automate') },
	{ key: 'translation', label: __('Translation updates', 'update-automate') },
	{ key: 'error', label: __('Update errors', 'update-automate') },
	{
		key: 'technical',
		label: __('Technical issues (recovery mode)', 'update-automate'),
	},
];

/**
 * @param {Object}   props
 * @param {Object}   props.settings     Current settings.
 * @param {Function} props.setSettings  Setter for settings.
 * @param {Function} props.saveSettings Async save to REST API.
 * @param {boolean}  props.saving       Whether save is in progress.
 * @return {JSX.Element}
 */
export const SettingsPanel = memo(function SettingsPanel({
	settings,
	setSettings,
	saveSettings,
	saving,
}) {
	const handleNotifyChange = (key, checked) => {
		setSettings((prev) => ({
			...prev,
			notifyOn: checked
				? [...prev.notifyOn.filter((x) => x !== key), key]
				: prev.notifyOn.filter((x) => x !== key),
		}));
	};

	return (
		<div className="updateautomate-settings-form">
			<h2 className="updateautomate-panel-title">
				{__('Settings', 'update-automate')}
			</h2>
			<Text variant="muted">
				{__(
					'Set up logging, choose how long to keep records, and manage email notifications.',
					'update-automate'
				)}
			</Text>
			<div className="updateautomate-settings-section">
				<h3 className="updateautomate-settings-section-title">
					{__('Logging', 'update-automate')}
				</h3>
				<ToggleControl
					label={__('Update logging', 'update-automate')}
					help={__(
						'Keep a record of all core, plugin, theme, and translation updates.',
						'update-automate'
					)}
					checked={settings.logging_enabled}
					onChange={(value) =>
						setSettings((prev) => ({
							...prev,
							logging_enabled: value,
						}))
					}
				/>
				<fieldset
					disabled={!settings.logging_enabled}
					className="updateautomate-settings-fieldset"
				>
					<NumberControl
						label={__('Keep logs for (days)', 'update-automate')}
						help={__(
							'Logs older than this number of days are automatically removed once a day.',
							'update-automate'
						)}
						min={1}
						max={365}
						value={settings.retention_days}
						onChange={(value) =>
							setSettings((prev) => ({
								...prev,
								retention_days: Math.max(
									1,
									Math.min(365, Number(value) ?? 90)
								),
							}))
						}
					/>
				</fieldset>
			</div>
			<div className="updateautomate-settings-section">
				<h3 className="updateautomate-settings-section-title">
					{__('Update notifications', 'update-automate')}
				</h3>
				<ToggleControl
					label={__('Manage update notifications', 'update-automate')}
					help={__(
						'When on, WordPress built-in update emails are sent to the address you choose below. Use the checkboxes to pick which types of updates you want to hear about.',
						'update-automate'
					)}
					checked={settings.notify_enabled}
					onChange={(value) =>
						setSettings((prev) => ({
							...prev,
							notify_enabled: value,
						}))
					}
				/>
				<fieldset
					disabled={!settings.notify_enabled}
					className="updateautomate-settings-fieldset"
				>
					<TextControl
						label={__('Send notifications to', 'update-automate')}
						help={__(
							'Update emails will go to this address instead of the default admin email.',
							'update-automate'
						)}
						type="email"
						value={settings.notify_emails}
						onChange={(value) =>
							setSettings((prev) => ({
								...prev,
								notify_emails: value || '',
							}))
						}
						placeholder={__('admin@example.com', 'update-automate')}
					/>
					<div className="updateautomate-settings-checkboxes">
						<p className="updateautomate-settings-label">
							{__('Notification types', 'update-automate')}
						</p>
						<p className="updateautomate-settings-help">
							{__(
								'Check the types of updates you want to receive emails about. Unchecked types will not send emails.',
								'update-automate'
							)}
						</p>
						{NOTIFY_TYPES.map(({ key, label }) => (
							<CheckboxControl
								key={key}
								label={label}
								checked={settings.notifyOn.includes(key)}
								onChange={(checked) =>
									handleNotifyChange(key, checked)
								}
							/>
						))}
					</div>
				</fieldset>
			</div>
			<Button
				variant="primary"
				onClick={saveSettings}
				isBusy={saving}
				disabled={saving}
			>
				{saving
					? __('Saving…', 'update-automate')
					: __('Save settings', 'update-automate')}
			</Button>
		</div>
	);
});
