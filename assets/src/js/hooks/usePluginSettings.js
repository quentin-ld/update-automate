import { useState, useCallback, useMemo } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Hook to read and save plugin settings (from localize + REST).
 * Dispatches native Notice (success / warning / error) on save (default context, like zenpress).
 *
 * @return {Object} Settings state and save action.
 */
export function usePluginSettings() {
	const { createSuccessNotice, createErrorNotice, createWarningNotice } =
		useDispatch(noticesStore);

	const initial = useMemo(() => {
		const opts =
			typeof window !== 'undefined' &&
			window.updateautomateSettings?.options;
		const allowedNotifyOn = [
			'core',
			'plugin',
			'theme',
			'translation',
			'error',
		];
		let notifyOn = ['error'];
		if (opts && Array.isArray(opts.notify_on)) {
			notifyOn = opts.notify_on.includes('all')
				? [...allowedNotifyOn]
				: opts.notify_on.filter((x) => allowedNotifyOn.includes(x));
			if (notifyOn.length === 0) {
				notifyOn = ['error'];
			}
		}
		return opts
			? {
					logging_enabled: !!opts.logging_enabled,
					retention_days: Number(opts.retention_days) || 90,
					notify_enabled: !!opts.notify_enabled,
					notify_emails: String(opts.notify_emails || ''),
					notifyOn,
				}
			: {
					logging_enabled: true,
					retention_days: 90,
					notify_enabled: false,
					notify_emails: '',
					notifyOn,
				};
	}, []);

	const [settings, setSettings] = useState(initial);
	const [saving, setSaving] = useState(false);

	const saveSettings = useCallback(async () => {
		setSaving(true);
		try {
			const payload = {
				logging_enabled: settings.logging_enabled,
				retention_days: settings.retention_days,
				notify_enabled: settings.notify_enabled,
				notify_emails: settings.notify_emails,
				notify_on: settings.notifyOn,
			};
			const response = await apiFetch({
				path: 'updateautomate/v1/settings',
				method: 'PUT',
				data: payload,
			});
			if (response?.options) {
				const { notify_on: notifyOnFromApi, ...rest } =
					response.options;
				setSettings({ ...rest, notifyOn: notifyOnFromApi });
				createSuccessNotice(
					__('Settings saved successfully.', 'update-automate')
				);
			} else {
				createWarningNotice(
					__('Settings saved with no response.', 'update-automate')
				);
			}
		} catch (e) {
			const message =
				e?.message || __('Failed to save settings.', 'update-automate');
			createErrorNotice(message);
		} finally {
			setSaving(false);
		}
	}, [settings, createSuccessNotice, createErrorNotice, createWarningNotice]);

	return {
		settings,
		setSettings,
		saveSettings,
		saving,
	};
}
