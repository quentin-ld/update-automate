import {
	Button,
	ToggleControl,
	TextControl,
	CheckboxControl,
	// NumberControl is the documented stable API; package still exports as __experimentalNumberControl.
	// See https://developer.wordpress.org/block-editor/reference-guides/components/number-control/
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- NumberControl is the documented component for number input.
	__experimentalNumberControl as NumberControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Text is the documented typography component.
	__experimentalText as Text,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Settings form for logging and notifications.
 * Uses official Gutenberg components: NumberControl, TextControl (email), ToggleControl, CheckboxControl.
 *
 * @param {Object}   props
 * @param {Object}   props.settings     Current settings (logging_enabled, retention_days, etc.).
 * @param {Function} props.setSettings  Setter for settings (e.g. (prev) => ({ ...prev, key: value })).
 * @param {Function} props.saveSettings Async save to REST API.
 * @param {boolean}  props.saving       Whether save is in progress.
 * @return {JSX.Element} Settings form UI.
 */
export const SettingsForm = ({
	settings,
	setSettings,
	saveSettings,
	saving,
}) => {
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
						<CheckboxControl
							label={__('Core updates', 'update-automate')}
							checked={settings.notifyOn.includes('core')}
							onChange={(checked) =>
								setSettings((prev) => ({
									...prev,
									notifyOn: checked
										? [
												...prev.notifyOn.filter(
													(x) => x !== 'core'
												),
												'core',
											]
										: prev.notifyOn.filter(
												(x) => x !== 'core'
											),
								}))
							}
						/>
						<CheckboxControl
							label={__('Plugin updates', 'update-automate')}
							checked={settings.notifyOn.includes('plugin')}
							onChange={(checked) =>
								setSettings((prev) => ({
									...prev,
									notifyOn: checked
										? [
												...prev.notifyOn.filter(
													(x) => x !== 'plugin'
												),
												'plugin',
											]
										: prev.notifyOn.filter(
												(x) => x !== 'plugin'
											),
								}))
							}
						/>
						<CheckboxControl
							label={__('Theme updates', 'update-automate')}
							checked={settings.notifyOn.includes('theme')}
							onChange={(checked) =>
								setSettings((prev) => ({
									...prev,
									notifyOn: checked
										? [
												...prev.notifyOn.filter(
													(x) => x !== 'theme'
												),
												'theme',
											]
										: prev.notifyOn.filter(
												(x) => x !== 'theme'
											),
								}))
							}
						/>
						<CheckboxControl
							label={__('Translation updates', 'update-automate')}
							checked={settings.notifyOn.includes('translation')}
							onChange={(checked) =>
								setSettings((prev) => ({
									...prev,
									notifyOn: checked
										? [
												...prev.notifyOn.filter(
													(x) => x !== 'translation'
												),
												'translation',
											]
										: prev.notifyOn.filter(
												(x) => x !== 'translation'
											),
								}))
							}
						/>
						<CheckboxControl
							label={__('Update errors', 'update-automate')}
							checked={settings.notifyOn.includes('error')}
							onChange={(checked) =>
								setSettings((prev) => ({
									...prev,
									notifyOn: checked
										? [
												...prev.notifyOn.filter(
													(x) => x !== 'error'
												),
												'error',
											]
										: prev.notifyOn.filter(
												(x) => x !== 'error'
											),
								}))
							}
						/>
						<CheckboxControl
							label={__(
								'Technical issues (recovery mode)',
								'update-automate'
							)}
							checked={settings.notifyOn.includes('technical')}
							onChange={(checked) =>
								setSettings((prev) => ({
									...prev,
									notifyOn: checked
										? [
												...prev.notifyOn.filter(
													(x) => x !== 'technical'
												),
												'technical',
											]
										: prev.notifyOn.filter(
												(x) => x !== 'technical'
											),
								}))
							}
						/>
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
};
