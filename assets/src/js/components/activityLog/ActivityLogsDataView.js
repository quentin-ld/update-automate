/**
 * Activity Logs Data View — Gutenberg DataViews component for update logs.
 *
 * Uses the official activity layout. Orchestrates view state, fields, actions,
 * and modal. Helpers and UI pieces live in sibling modules.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 * @see docs/ACTIVITY-LOGS-DATAVIEW-SPEC.md
 */

import { useMemo, useState, useEffect, useRef } from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useLogs } from '../../hooks/useLogs';
import { LAYOUT_ACTIVITY, ACTIVITY_LAYOUT_CONFIG } from './constants';
import {
	statusToBadgeIntent,
	formatDate,
	getContextLabel,
	getActivityTitle,
	getActivityDescription,
} from './utils';
import { StatusBadge } from './StatusBadge';
import { getIconForLogType } from './logTypeIcon';
import { LogDetailsContent } from './LogDetailsContent';

export function ActivityLogsDataView() {
	const { logs, loading, error, fetchLogs, deleteLog } = useLogs();
	const [view, setView] = useState({
		type: LAYOUT_ACTIVITY,
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		sort: { field: 'date', direction: 'desc' },
		fields: ['date', 'context', 'itemFooter'],
		titleField: 'title',
		descriptionField: 'description',
		mediaField: 'icon',
		groupBy: ACTIVITY_LAYOUT_CONFIG.groupBy,
	});

	const [logForViewModal, setLogForViewModal] = useState(null);
	const viewLogModalRef = useRef({ open: () => {} });
	useEffect(() => {
		viewLogModalRef.current.open = (item) => setLogForViewModal(item);
	}, []);

	useEffect(() => {
		fetchLogs({ per_page: view.perPage, page: view.page });
	}, [fetchLogs, view.perPage, view.page]);

	const fields = useMemo(
		() => [
			{
				id: 'title',
				label: __('Title', 'updatescontrol'),
				getValue: ({ item }) => getActivityTitle(item),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'description',
				label: __('Version', 'updatescontrol'),
				getValue: ({ item }) => getActivityDescription(item),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'icon',
				label: __('Type', 'updatescontrol'),
				getValue: ({ item }) => item.log_type || '',
				render: ({ item, config }) => {
					const size =
						config?.sizes &&
						typeof config.sizes === 'string' &&
						config.sizes.endsWith('px')
							? parseInt(config.sizes, 10)
							: 24;
					return getIconForLogType(item.log_type, size);
				},
				enableSorting: false,
				enableHiding: false,
				filterBy: false,
			},
			{
				id: 'dateGroup',
				label: __('Date', 'updatescontrol'),
				getValue: ({ item }) => {
					if (!item.created_at) {
						return '';
					}
					const d = new Date(item.created_at);
					return d.toISOString().slice(0, 10);
				},
				render: ({ item }) => formatDate(item.created_at),
				enableSorting: true,
				sort: (a, b, direction) => {
					const tA = new Date(a.created_at).getTime();
					const tB = new Date(b.created_at).getTime();
					return direction === 'asc' ? tA - tB : tB - tA;
				},
				enableGlobalSearch: false,
				enableHiding: false,
				filterBy: false,
			},
			{
				id: 'date',
				label: __('Date', 'updatescontrol'),
				getValue: ({ item }) => item.created_at,
				render: ({ item }) => formatDate(item.created_at),
				enableSorting: true,
				sort: (a, b, direction) => {
					const tA = new Date(a.created_at).getTime();
					const tB = new Date(b.created_at).getTime();
					return direction === 'asc' ? tA - tB : tB - tA;
				},
				enableGlobalSearch: false,
			},
			{
				id: 'context',
				label: __('Context', 'updatescontrol'),
				getValue: ({ item }) => getContextLabel(item.update_context),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'user',
				label: __('User', 'updatescontrol'),
				getValue: ({ item }) => item.performed_by_display || '',
				render: ({ item }) =>
					item.user_edit_link ? (
						<a
							href={item.user_edit_link}
							rel="noopener noreferrer"
							target="_blank"
						>
							{item.performed_by_display || '—'}
						</a>
					) : (
						<span>{item.performed_by_display || '—'}</span>
					),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'status',
				label: __('Status', 'updatescontrol'),
				getValue: ({ item }) => item.status || '',
				render: ({ item }) => (
					<StatusBadge intent={statusToBadgeIntent(item.status)}>
						{item.status || '—'}
					</StatusBadge>
				),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'itemFooter',
				label: __('Details', 'updatescontrol'),
				getValue: () => '',
				enableSorting: false,
				enableHiding: false,
				filterBy: false,
				render: ({ item }) => (
					<div className="updatescontrol-activity-item-footer">
						<div className="updatescontrol-activity-item-footer__line">
							{item.user_edit_link ? (
								<a
									href={item.user_edit_link}
									rel="noopener noreferrer"
									target="_blank"
								>
									{item.performed_by_display || '—'}
								</a>
							) : (
								<span>{item.performed_by_display || '—'}</span>
							)}
						</div>
						<div className="updatescontrol-activity-item-footer__line">
							<StatusBadge
								intent={statusToBadgeIntent(item.status)}
							>
								{item.status || '—'}
							</StatusBadge>
						</div>
						<div className="updatescontrol-activity-item-footer__line">
							<Button
								variant="secondary"
								isSmall
								onClick={() =>
									viewLogModalRef.current.open?.(item)
								}
							>
								{__('View log', 'updatescontrol')}
							</Button>
						</div>
					</div>
				),
			},
		],
		[]
	);

	const actions = useMemo(
		() => [
			{
				id: 'view-logs',
				label: __('View logs', 'updatescontrol'),
				modalHeader: __('Log details', 'updatescontrol'),
				isEligible: (item) => !!(item.message || item.trace),
				RenderModal: ({ items }) => (
					<LogDetailsContent log={items[0]} />
				),
			},
			{
				id: 'delete',
				label: __('Delete', 'updatescontrol'),
				modalHeader: __('Delete log', 'updatescontrol'),
				isDestructive: true,
				RenderModal: ({ items, closeModal, onActionPerformed }) => {
					const log = items[0];
					if (!log) {
						return null;
					}
					const handleConfirm = async () => {
						await deleteLog(log.id);
						onActionPerformed?.(items);
						closeModal?.();
					};
					return (
						<>
							<p>
								{__('Delete this log entry?', 'updatescontrol')}
							</p>
							<div
								style={{
									display: 'flex',
									gap: '8px',
									justifyContent: 'flex-end',
									marginTop: '16px',
								}}
							>
								<Button
									variant="secondary"
									onClick={closeModal}
								>
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
						</>
					);
				},
			},
		],
		[deleteLog]
	);

	const defaultLayouts = useMemo(
		() => ({
			[LAYOUT_ACTIVITY]: {
				sort: { field: 'date', direction: 'desc' },
				groupBy: ACTIVITY_LAYOUT_CONFIG.groupBy,
				layout: { density: 'balanced' },
			},
		}),
		[]
	);

	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate(logs, view, fields),
		[logs, view, fields]
	);

	if (error) {
		return (
			<div className="updatescontrol-logs-error notice notice-error">
				<p>{error}</p>
			</div>
		);
	}

	const wrapperStyle = ACTIVITY_LAYOUT_CONFIG.fullWidth
		? undefined
		: { maxWidth: '720px' };

	return (
		<div
			className="updatescontrol-logs updatescontrol-activity-dataview"
			style={wrapperStyle}
		>
			{logForViewModal && (
				<Modal
					title={__('Log details', 'updatescontrol')}
					onRequestClose={() => setLogForViewModal(null)}
				>
					<LogDetailsContent log={logForViewModal} />
				</Modal>
			)}
			<div className="updatescontrol-logs-actions">
				<Button
					variant="secondary"
					onClick={() =>
						fetchLogs({ per_page: view.perPage, page: 1 })
					}
					disabled={loading}
				>
					{__('Refresh', 'updatescontrol')}
				</Button>
			</div>
			<DataViews
				getItemId={(item) => String(item.id)}
				view={view}
				onChangeView={setView}
				fields={fields}
				data={shownData}
				actions={actions}
				isLoading={loading}
				paginationInfo={paginationInfo}
				defaultLayouts={defaultLayouts}
				search
				searchLabel={__('Search logs', 'updatescontrol')}
			/>
		</div>
	);
}
