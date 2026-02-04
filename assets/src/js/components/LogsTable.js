import { useEffect, useState } from '@wordpress/element';
import { Button, Modal, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useLogs } from '../hooks/useLogs';

/**
 * Logs table with filters, delete, and cleanup.
 *
 * @return {JSX.Element} Logs table UI.
 */
export const LogsTable = () => {
	const { logs, total, loading, error, fetchLogs, deleteLog, cleanupLogs } =
		useLogs();
	const [confirmAction, setConfirmAction] = useState({
		type: null,
		id: null,
	});

	useEffect(() => {
		fetchLogs({ per_page: 50, page: 1 });
	}, [fetchLogs]);

	const handleDeleteClick = (id) => {
		setConfirmAction({ type: 'delete', id });
	};

	const handleCleanupClick = () => {
		setConfirmAction({ type: 'cleanup', id: null });
	};

	const closeConfirm = () => {
		setConfirmAction({ type: null, id: null });
	};

	const handleConfirm = async () => {
		if (confirmAction.type === 'delete' && confirmAction.id) {
			await deleteLog(confirmAction.id);
		} else if (confirmAction.type === 'cleanup') {
			await cleanupLogs();
		}
		closeConfirm();
	};

	const confirmMessage =
		confirmAction.type === 'delete'
			? __('Delete this log entry?', 'updatescontrol')
			: __(
					'Delete all logs older than the retention period. This cannot be undone.',
					'updatescontrol'
				);

	const formatDate = (dateStr) => {
		if (!dateStr) {
			return '—';
		}
		try {
			const d = new Date(dateStr);
			return d.toLocaleString();
		} catch {
			return dateStr;
		}
	};

	if (error) {
		return (
			<div className="updatescontrol-logs-error notice notice-error">
				<p>{error}</p>
			</div>
		);
	}

	return (
		<div className="updatescontrol-logs">
			{confirmAction.type && (
				<Modal
					title={
						confirmAction.type === 'delete'
							? __('Delete log', 'updatescontrol')
							: __('Cleanup old logs', 'updatescontrol')
					}
					onRequestClose={closeConfirm}
				>
					<p>{confirmMessage}</p>
					<div
						style={{
							display: 'flex',
							gap: '8px',
							justifyContent: 'flex-end',
							marginTop: '16px',
						}}
					>
						<Button variant="secondary" onClick={closeConfirm}>
							{__('Cancel', 'updatescontrol')}
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={handleConfirm}
						>
							{__('Confirm', 'updatescontrol')}
						</Button>
					</div>
				</Modal>
			)}
			<div className="updatescontrol-logs-actions">
				<Button
					variant="secondary"
					onClick={() => fetchLogs({ per_page: 50, page: 1 })}
					disabled={loading}
				>
					{__('Refresh', 'updatescontrol')}
				</Button>
				<Button
					variant="secondary"
					isDestructive
					onClick={handleCleanupClick}
					disabled={loading}
				>
					{__('Cleanup old logs', 'updatescontrol')}
				</Button>
			</div>
			{loading && logs.length === 0 ? (
				<div className="updatescontrol-logs-loading">
					<Spinner />
					<span>{__('Loading logs…', 'updatescontrol')}</span>
				</div>
			) : (
				<>
					<p className="updatescontrol-logs-count">
						{__('Total entries:', 'updatescontrol')} {total}
					</p>
					<div className="updatescontrol-logs-table-wrap">
						<table className="updatescontrol-logs-table widefat striped">
							<thead>
								<tr>
									<th>{__('Date', 'updatescontrol')}</th>
									<th>{__('Type', 'updatescontrol')}</th>
									<th>{__('Action', 'updatescontrol')}</th>
									<th>{__('Item', 'updatescontrol')}</th>
									<th>{__('Version', 'updatescontrol')}</th>
									<th>{__('Status', 'updatescontrol')}</th>
									<th>{__('Message', 'updatescontrol')}</th>
									<th>{__('Performed by', 'updatescontrol')}</th>
									<th
										aria-label={__(
											'Actions',
											'updatescontrol'
										)}
									/>
								</tr>
							</thead>
							<tbody>
								{logs.length === 0 ? (
									<tr>
										<td colSpan="9">
											{__(
												'No update logs yet.',
												'updatescontrol'
											)}
										</td>
									</tr>
								) : (
									logs.map((log) => (
										<tr key={log.id}>
											<td>
												{formatDate(log.created_at)}
											</td>
											<td>{log.log_type}</td>
											<td>{log.action_type}</td>
											<td>{log.item_name || '—'}</td>
											<td>
												{log.version_before &&
												log.version_after
													? `${log.version_before} → ${log.version_after}`
													: log.version_after ||
														log.version_before ||
														'—'}
											</td>
											<td>
												<span
													className={`updatescontrol-status updatescontrol-status--${log.status}`}
												>
													{log.status}
												</span>
											</td>
											<td>
												{log.message
													? log.message.substring(
															0,
															80
														)
													: '—'}
											</td>
											<td>
												{log.user_edit_link ? (
													<a
														href={log.user_edit_link}
														rel="noopener noreferrer"
														target="_blank"
													>
														{log.performed_by_display}
													</a>
												) : (
													<span>{log.performed_by_display || '—'}</span>
												)}
											</td>
											<td>
												<Button
													variant="link"
													isDestructive
													isSmall
													onClick={() =>
														handleDeleteClick(
															log.id
														)
													}
												>
													{__(
														'Delete',
														'updatescontrol'
													)}
												</Button>
											</td>
										</tr>
									))
								)}
							</tbody>
						</table>
					</div>
				</>
			)}
		</div>
	);
};
