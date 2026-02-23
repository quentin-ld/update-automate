import { useState, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Hook to fetch, delete, and cleanup update logs.
 *
 * @return {Object} Logs state and actions.
 */
export function useLogs() {
	const [logs, setLogs] = useState([]);
	const [total, setTotal] = useState(0);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	const fetchLogs = useCallback(async (params = {}) => {
		setLoading(true);
		setError(null);
		try {
			const query = new URLSearchParams({
				per_page: String(params.per_page || 50),
				page: String(params.page || 1),
				log_type: params.log_type || '',
				status: params.status || '',
			}).toString();
			const response = await apiFetch({
				path: `updateautomate/v1/logs?${query}`,
			});
			setLogs(response.logs || []);
			setTotal(response.total ?? 0);
		} catch (e) {
			setError(
				e?.message || __('Failed to load logs.', 'update-automate')
			);
		} finally {
			setLoading(false);
		}
	}, []);

	const deleteLog = useCallback(async (id) => {
		try {
			await apiFetch({
				path: `updateautomate/v1/logs/${id}`,
				method: 'DELETE',
			});
			setLogs((prev) =>
				prev.filter((log) => Number(log.id) !== Number(id))
			);
			setTotal((prev) => Math.max(0, prev - 1));
			return true;
		} catch (e) {
			return false;
		}
	}, []);

	return {
		logs,
		total,
		loading,
		error,
		fetchLogs,
		deleteLog,
	};
}
