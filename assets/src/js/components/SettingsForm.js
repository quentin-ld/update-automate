import {
	Button,
	ToggleControl,
	TextControl,
	CheckboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { usePluginSettings } from '../hooks/usePluginSettings';

/**
 * Settings form for logging and notifications.
 *
 * @return {JSX.Element} Settings form UI.
 */
export const SettingsForm = () => {
	const { settings, setSettings, saveSettings, saving, saveError } =
		usePluginSettings();

	return (
		<div className="updatescontrol-settings-form">
			<div className="updatescontrol-settings-section">
				<h3 className="updatescontrol-settings-section-title">
					{__('Logging', 'updatescontrol')}
				</h3>
				<ToggleControl
					label={__('Enable update logging', 'updatescontrol')}
					help={__(
						'Record core, plugin, and theme updates in the database.',
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
				<TextControl
					label={__('Retention (days)', 'updatescontrol')}
					help={__(
						'Automatically delete logs older than this many days. Cron runs daily.',
						'updatescontrol'
					)}
					type="number"
					min={1}
					max={365}
					value={String(settings.retention_days)}
					onChange={(value) =>
						setSettings((prev) => ({
							...prev,
							retention_days: Math.max(
								1,
								Math.min(365, Number(value) || 90)
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
					checked={settings.notify_enabled}
					onChange={(value) =>
						setSettings((prev) => ({
							...prev,
							notify_enabled: value,
						}))
					}
				/>
				<TextControl
					label={__('Recipient emails', 'updatescontrol')}
					help={__(
						'Comma-separated list of email addresses.',
						'updatescontrol'
					)}
					value={settings.notify_emails}
					onChange={(value) =>
						setSettings((prev) => ({
							...prev,
							notify_emails: value || '',
						}))
					}
					disabled={!settings.notify_enabled}
				/>
				<div className="updatescontrol-settings-checkboxes">
					<p className="updatescontrol-settings-label">
						{__('Notify on', 'updatescontrol')}
					</p>
					<CheckboxControl
						label={__('Errors (failed updates)', 'updatescontrol')}
						checked={settings.notify_on.includes('error')}
						onChange={(checked) =>
							setSettings((prev) => ({
								...prev,
								notify_on: checked
									? [
											...prev.notify_on.filter(
												(x) => x !== 'error'
											),
											'error',
										]
									: prev.notify_on.filter(
											(x) => x !== 'error'
										),
							}))
						}
						disabled={!settings.notify_enabled}
					/>
					<CheckboxControl
						label={__('Core WordPress updates', 'updatescontrol')}
						checked={settings.notify_on.includes('core')}
						onChange={(checked) =>
							setSettings((prev) => ({
								...prev,
								notify_on: checked
									? [
											...prev.notify_on.filter(
												(x) => x !== 'core'
											),
											'core',
										]
									: prev.notify_on.filter(
											(x) => x !== 'core'
										),
							}))
						}
						disabled={!settings.notify_enabled}
					/>
					<CheckboxControl
						label={__(
							'All updates (plugins & themes too)',
							'updatescontrol'
						)}
						checked={settings.notify_on.includes('all')}
						onChange={(checked) =>
							setSettings((prev) => ({
								...prev,
								notify_on: checked
									? [
											...prev.notify_on.filter(
												(x) => x !== 'all'
											),
											'all',
										]
									: prev.notify_on.filter((x) => x !== 'all'),
							}))
						}
						disabled={!settings.notify_enabled}
					/>
				</div>
			</div>
			{saveError && (
				<div className="updatescontrol-settings-error notice notice-error">
					<p>{saveError}</p>
				</div>
			)}
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
