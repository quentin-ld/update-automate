import { useState, useCallback, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Hook to read and save plugin settings (from localize + REST).
 *
 * @return {Object} Settings state and save action.
 */
export function usePluginSettings() {
	const initial = useMemo(() => {
		const opts =
			typeof window !== 'undefined' &&
			window.updatescontrolSettings?.options;
		return opts
			? {
					logging_enabled: !!opts.logging_enabled,
					retention_days: Number(opts.retention_days) || 90,
					notify_enabled: !!opts.notify_enabled,
					notify_emails: String(opts.notify_emails || ''),
					notify_on: Array.isArray(opts.notify_on)
						? opts.notify_on
						: ['error'],
				}
			: {
					logging_enabled: true,
					retention_days: 90,
					notify_enabled: false,
					notify_emails: '',
					notify_on: ['error'],
				};
	}, []);

	const [settings, setSettings] = useState(initial);
	const [saving, setSaving] = useState(false);
	const [saveError, setSaveError] = useState(null);

	const saveSettings = useCallback(async () => {
		setSaving(true);
		setSaveError(null);
		try {
			const response = await apiFetch({
				path: 'updatescontrol/v1/settings',
				method: 'PUT',
				data: settings,
			});
			if (response?.options) {
				setSettings(response.options);
			}
		} catch (e) {
			setSaveError(e?.message || 'Failed to save settings.');
		} finally {
			setSaving(false);
		}
	}, [settings]);

	return {
		settings,
		setSettings,
		saveSettings,
		saving,
		saveError,
	};
}
