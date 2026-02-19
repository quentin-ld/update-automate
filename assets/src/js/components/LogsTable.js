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
	const { logs, total, loading, error, fetchLogs, deleteLog } = useLogs();
	const [confirmAction, setConfirmAction] = useState({
		type: null,
		id: null,
	});
	const [notesLog, setNotesLog] = useState(null);

	useEffect(() => {
		fetchLogs({ per_page: 50, page: 1 });
	}, [fetchLogs]);

	const handleDeleteClick = (id) => {
		setConfirmAction({ type: 'delete', id });
	};

	const closeConfirm = () => {
		setConfirmAction({ type: null, id: null });
	};

	const openNotes = (log) => {
		setNotesLog(log);
	};
	const closeNotes = () => {
		setNotesLog(null);
	};

	const handleConfirm = async () => {
		if (confirmAction.type === 'delete' && confirmAction.id) {
			await deleteLog(confirmAction.id);
		}
		closeConfirm();
	};

	const getActionTypeLabel = (actionType, updateContext = '') => {
		const labels = {
			update: __('Update', 'updatescontrol'),
			downgrade: __('Downgrade', 'updatescontrol'),
			install: __('Install', 'updatescontrol'),
			same_version: __('Reset', 'updatescontrol'),
			failed: __('Failed', 'updatescontrol'),
			delete: __('Delete', 'updatescontrol'), // Legacy.
		};
		const base = labels[actionType] || actionType;
		if (updateContext === 'bulk') {
			return `${base} (${__('Bulk', 'updatescontrol')})`;
		}
		if (updateContext === 'single') {
			return `${base} (${__('Single', 'updatescontrol')})`;
		}
		return base;
	};

	const confirmMessage = __('Delete this log entry?', 'updatescontrol');

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
					title={__('Delete log', 'updatescontrol')}
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
			{notesLog && (
				<Modal
					title={__('Logs', 'updatescontrol')}
					onRequestClose={closeNotes}
					className="updatescontrol-notes-modal"
				>
					<div className="updatescontrol-notes-content">
						{(notesLog.message || notesLog.trace) && (
							<>
								{notesLog.message && (
									<div className="updatescontrol-notes-section">
										<h4>{__('Message', 'updatescontrol')}</h4>
										<pre
											className="updatescontrol-notes-text"
											style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}
										>
											{notesLog.message}
										</pre>
									</div>
								)}
								{notesLog.trace && (
									<div className="updatescontrol-notes-section">
										<h4>{__('Trace', 'updatescontrol')}</h4>
										<pre
											className="updatescontrol-notes-trace"
											style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-all', fontSize: '12px' }}
										>
											{notesLog.trace}
										</pre>
									</div>
								)}
							</>
						)}
						{!notesLog.message && !notesLog.trace && (
							<p>{__('No message or trace for this entry.', 'updatescontrol')}</p>
						)}
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
									<th>{__('From version', 'updatescontrol')}</th>
									<th>{__('To version', 'updatescontrol')}</th>
									<th>{__('Status', 'updatescontrol')}</th>
									<th>{__('Trigger', 'updatescontrol')}</th>
									<th>{__('Logs', 'updatescontrol')}</th>
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
										<td colSpan="11">
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
											<td>{getActionTypeLabel(log.action_type, log.update_context)}</td>
											<td>{log.item_name || '—'}</td>
											<td>{log.version_before || '—'}</td>
											<td>{log.version_after || '—'}</td>
											<td>
												<span
													className={`updatescontrol-status updatescontrol-status--${log.status}`}
												>
													{log.status}
												</span>
											</td>
											<td>{log.action_display || '—'}</td>
											<td>
												{log.message || log.trace ? (
													<Button
														variant="link"
														isSmall
														onClick={() => openNotes(log)}
													>
														{__('View logs', 'updatescontrol')}
													</Button>
												) : (
													'—'
												)}
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
