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
import { usePluginSettings } from '../hooks/usePluginSettings';

/**
 * Settings form for logging and notifications.
 * Uses official Gutenberg components: NumberControl, TextControl (email), ToggleControl, CheckboxControl.
 *
 * @return {JSX.Element} Settings form UI.
 */
export const SettingsForm = () => {
	const { settings, setSettings, saveSettings, saving } = usePluginSettings();

	return (
		<div className="updatescontrol-settings-form">
			<div className="updatescontrol-settings-section">
				<h3 className="updatescontrol-settings-section-title">
					{__('Logging', 'updatescontrol')}
				</h3>
				<ToggleControl
					label={__('Enable update logging', 'updatescontrol')}
					help={__(
						'Record core, themes, plugins, and translations updates in the database.',
						'updatescontrol'
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
					label={__('Retention (days)', 'updatescontrol')}
					help={__(
						'Automatically delete logs older than this many days. Cron runs daily.',
						'updatescontrol'
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
			<div className="updatescontrol-settings-section">
				<h3 className="updatescontrol-settings-section-title">
					{__('Email notifications', 'updatescontrol')}
				</h3>
				<ToggleControl
					label={__('Enable email notifications', 'updatescontrol')}
					help={__(
						'Redirect WordPress native update notification emails to the recipient below.',
						'updatescontrol'
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
					label={__('Recipient email', 'updatescontrol')}
					help={__(
						'Native WordPress update emails will be sent to this address instead of the site admin.',
						'updatescontrol'
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
					placeholder={__('admin@example.com', 'updatescontrol')}
				/>
				<div className="updatescontrol-settings-checkboxes">
					<p className="updatescontrol-settings-label">
						{__('Notify on', 'updatescontrol')}
					</p>
					<CheckboxControl
						label={__(
							'WordPress core automatic updates',
							'updatescontrol'
						)}
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
						label={__('Plugin automatic updates', 'updatescontrol')}
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
						label={__('Theme automatic updates', 'updatescontrol')}
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
							'Translation automatic updates',
							'updatescontrol'
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
						label={__('Errors (failed updates)', 'updatescontrol')}
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
				</div>
			</div>
			<Button
				variant="primary"
				onClick={saveSettings}
				isBusy={saving}
				disabled={saving}
			>
				{saving
					? __('Saving…', 'updatescontrol')
					: __('Save settings', 'updatescontrol')}
			</Button>
		</div>
	);
};
