import { useState, useCallback, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const API_BASE = 'updateautomate/v1/auto-updates';

/**
 * Hook to read and mutate native WordPress auto-update settings via REST.
 *
 * Every mutation returns the full refreshed dataset from the server so the UI
 * stays in sync without a second GET.
 *
 * @return {Object} Auto-update state and mutation helpers.
 */
export function useAutoUpdates() {
	const { createSuccessNotice, createErrorNotice } =
		useDispatch(noticesStore);

	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(true);
	const [busy, setBusy] = useState(false);

	const fetchData = useCallback(async () => {
		setLoading(true);
		try {
			const response = await apiFetch({ path: API_BASE });
			setData(response);
		} catch (e) {
			createErrorNotice(
				e?.message ||
					__('Failed to load auto-update data.', 'update-automate')
			);
		} finally {
			setLoading(false);
		}
	}, [createErrorNotice]);

	useEffect(() => {
		fetchData();
	}, [fetchData]);

	const setCoreMode = useCallback(
		async (mode) => {
			setBusy(true);
			try {
				const response = await apiFetch({
					path: `${API_BASE}/core`,
					method: 'POST',
					data: { mode },
				});
				setData(response);
				createSuccessNotice(
					__('Core auto-update setting saved.', 'update-automate')
				);
			} catch (e) {
				createErrorNotice(
					e?.message ||
						__(
							'Failed to save core auto-update setting.',
							'update-automate'
						)
				);
			} finally {
				setBusy(false);
			}
		},
		[createSuccessNotice, createErrorNotice]
	);

	const togglePlugin = useCallback(
		async (pluginFile, enable) => {
			setBusy(true);
			try {
				const response = await apiFetch({
					path: `${API_BASE}/plugin`,
					method: 'POST',
					data: { plugin: pluginFile, enable },
				});
				setData(response);
			} catch (e) {
				createErrorNotice(
					e?.message ||
						__(
							'Failed to toggle plugin auto-update.',
							'update-automate'
						)
				);
			} finally {
				setBusy(false);
			}
		},
		[createErrorNotice]
	);

	const toggleTheme = useCallback(
		async (stylesheet, enable) => {
			setBusy(true);
			try {
				const response = await apiFetch({
					path: `${API_BASE}/theme`,
					method: 'POST',
					data: { stylesheet, enable },
				});
				setData(response);
			} catch (e) {
				createErrorNotice(
					e?.message ||
						__(
							'Failed to toggle theme auto-update.',
							'update-automate'
						)
				);
			} finally {
				setBusy(false);
			}
		},
		[createErrorNotice]
	);

	const toggleTranslation = useCallback(
		async (enable) => {
			setBusy(true);
			try {
				const response = await apiFetch({
					path: `${API_BASE}/translation`,
					method: 'POST',
					data: { enable },
				});
				setData(response);
				createSuccessNotice(
					__(
						'Translation auto-update setting saved.',
						'update-automate'
					)
				);
			} catch (e) {
				createErrorNotice(
					e?.message ||
						__(
							'Failed to toggle translation auto-updates.',
							'update-automate'
						)
				);
			} finally {
				setBusy(false);
			}
		},
		[createSuccessNotice, createErrorNotice]
	);

	const dismissConstant = useCallback(
		async (constant) => {
			try {
				const response = await apiFetch({
					path: `${API_BASE}/dismiss-constant`,
					method: 'POST',
					data: { constant },
				});
				setData(response);
			} catch (e) {
				createErrorNotice(
					e?.message ||
						__('Failed to dismiss notice.', 'update-automate')
				);
			}
		},
		[createErrorNotice]
	);

	return {
		data,
		loading,
		busy,
		setCoreMode,
		togglePlugin,
		toggleTheme,
		toggleTranslation,
		dismissConstant,
	};
}
