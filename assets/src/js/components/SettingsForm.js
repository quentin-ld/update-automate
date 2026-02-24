import {
	Button,
	ToggleControl,
	TextControl,
	CheckboxControl,
	// NumberControl is the documented stable API; package still exports as __experimentalNumberControl.
	// See https://developer.wordpress.org/block-editor/reference-guides/components/number-control/
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- NumberControl is the documented component for number input.
	__experimentalNumberControl as NumberControl,
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
			<div className="updateautomate-settings-section">
				<h3 className="updateautomate-settings-section-title">
					{__('Logging', 'update-automate')}
				</h3>
				<ToggleControl
					label={__('Enable update logging', 'update-automate')}
					help={__(
						'Record core, themes, plugins, and translations updates in the database.',
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
				<NumberControl
					label={__('Retention (days)', 'update-automate')}
					help={__(
						'Automatically delete logs older than this many days. Cron runs daily.',
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
			</div>
			<div className="updateautomate-settings-section">
				<h3 className="updateautomate-settings-section-title">
					{__('Update notifications', 'update-automate')}
				</h3>
				<ToggleControl
					label={__('Manage update notifications', 'update-automate')}
					help={__(
						'Controls how native WordPress update emails are handled. Category checkboxes decide which update notifications are allowed. When this switch is ON, allowed emails are redirected to the recipient below. When OFF, recipient redirection is disabled.',
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
				<TextControl
					label={__(
						'Recipient email (for redirected emails)',
						'update-automate'
					)}
					help={__(
						'Used only when "Manage update notifications" is ON. Allowed native update emails are sent to this address instead of admin_email.',
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
					disabled={!settings.notify_enabled}
					placeholder={__('admin@example.com', 'update-automate')}
				/>
				<div className="updateautomate-settings-checkboxes">
					<p className="updateautomate-settings-label">
						{__('Notify on', 'update-automate')}
					</p>
					<p className="updateautomate-settings-help">
						{__(
							'Checked categories allow those native WordPress update notifications. Unchecked update categories are suppressed by this plugin.',
							'update-automate'
						)}
					</p>
					<CheckboxControl
						label={__('WordPress core updates', 'update-automate')}
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
									: prev.notifyOn.filter((x) => x !== 'core'),
							}))
						}
						disabled={!settings.notify_enabled}
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
						disabled={!settings.notify_enabled}
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
						disabled={!settings.notify_enabled}
					/>
					<CheckboxControl
						label={__(
							'Translation updates (via native debug email)',
							'update-automate'
						)}
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
						disabled={!settings.notify_enabled}
					/>
					<CheckboxControl
						label={__(
							'Errors (failed plugin/theme/core update runs)',
							'update-automate'
						)}
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
						disabled={!settings.notify_enabled}
					/>
					<CheckboxControl
						label={__(
							'Technical issue emails (Recovery Mode)',
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
						disabled={!settings.notify_enabled}
					/>
				</div>
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
