/**
 * Activity Logs Data View — Gutenberg DataViews component for update logs.
 *
 * Uses the official activity layout. Orchestrates view state, fields, actions,
 * and modal. Helpers and UI pieces live in sibling modules.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 * @see docs/ACTIVITY-LOGS-DATAVIEW-SPEC.md
 */

import { useMemo, useState, useEffect, useCallback } from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useLogs } from '../../hooks/useLogs';
import { LAYOUT_ACTIVITY } from './constants';
import {
	statusToBadgeIntent,
	formatDate,
	getContextLabel,
	getActivityTitle,
	getActivityDescription,
	EMPTY_FALLBACK,
} from './utils';
import { StatusBadge } from './StatusBadge';
import { getIconForLogType } from './logTypeIcon';
import { LogDetailsContent } from './LogDetailsContent';

const FIXED_SORT = { field: 'date', direction: 'desc' };

export function ActivityLogsDataView() {
	const { logs, loading, error, fetchLogs, deleteLog } = useLogs();

	const [view, setView] = useState({
		type: LAYOUT_ACTIVITY,
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		sort: FIXED_SORT,
		fields: ['date', 'context', 'user', 'status'],
		titleField: 'title',
		descriptionField: 'description',
		mediaField: 'icon',
	});

	const handleChangeView = useCallback((nextView) => {
		setView(() => ({
			...nextView,
			sort: FIXED_SORT,
		}));
	}, []);

	useEffect(() => {
		fetchLogs({ per_page: view.perPage, page: view.page });
	}, [fetchLogs, view.perPage, view.page]);

	const fields = useMemo(
		() => [
			{
				id: 'title',
				label: __('Title', 'update-automate'),
				getValue: ({ item }) => getActivityTitle(item),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'description',
				label: __('Version', 'update-automate'),
				getValue: ({ item }) => getActivityDescription(item),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'icon',
				label: __('Type', 'update-automate'),
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
				id: 'date',
				label: __('Date', 'update-automate'),
				getValue: ({ item }) => item.created_at,
				render: ({ item }) => formatDate(item.created_at),
				enableSorting: false,
				sort: (a, b, direction) => {
					const tA = new Date(a.created_at).getTime();
					const tB = new Date(b.created_at).getTime();
					return direction === 'asc' ? tA - tB : tB - tA;
				},
				enableGlobalSearch: false,
			},
			{
				id: 'context',
				label: __('Context', 'update-automate'),
				getValue: ({ item }) => getContextLabel(item.update_context),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'user',
				label: __('User', 'update-automate'),
				getValue: ({ item }) => item.performed_by_display || '',
				render: ({ item }) =>
					item.user_edit_link ? (
						<a
							href={item.user_edit_link}
							rel="noopener noreferrer"
							target="_blank"
						>
							{item.performed_by_display || EMPTY_FALLBACK}
						</a>
					) : (
						<span>
							{item.performed_by_display || EMPTY_FALLBACK}
						</span>
					),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'status',
				label: __('Status', 'update-automate'),
				getValue: ({ item }) => item.status || '',
				render: ({ item }) => (
					<StatusBadge intent={statusToBadgeIntent(item.status)}>
						{item.status || EMPTY_FALLBACK}
					</StatusBadge>
				),
				enableSorting: false,
				enableGlobalSearch: true,
			},
		],
		[]
	);

	const actions = useMemo(
		() => [
			{
				id: 'view-logs',
				label: __('View log', 'update-automate'),
				modalHeader: __('Log details', 'update-automate'),
				modalSize: 'large',
				modalFocusOnMount: 'firstContentElement',
				isEligible: (item) => !!(item.message || item.trace),
				RenderModal: ({ items }) => (
					<LogDetailsContent log={items[0]} />
				),
			},
			{
				id: 'delete',
				label: __('Delete', 'update-automate'),
				modalHeader: __('Delete log', 'update-automate'),
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
								{__(
									'Delete this log entry?',
									'update-automate'
								)}
							</p>
							<div
								style={{
									display: 'flex',
									gap: '8px',
									justifyContent: 'flex-end',
									marginTop: '16px',
								}}
							>
								<Button variant="tertiary" onClick={closeModal}>
									{__('Cancel', 'update-automate')}
								</Button>
								<Button
									variant="primary"
									isDestructive
									onClick={handleConfirm}
								>
									{__('Confirm', 'update-automate')}
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
				sort: FIXED_SORT,
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
			<div className="updateautomate-logs-error notice notice-error">
				<p>{error}</p>
			</div>
		);
	}

	return (
		<div className="updateautomate-logs updateautomate-activity-dataview">
			<DataViews
				getItemId={(item) => String(item.id)}
				view={view}
				onChangeView={handleChangeView}
				fields={fields}
				data={shownData}
				actions={actions}
				isLoading={loading}
				paginationInfo={paginationInfo}
				defaultLayouts={defaultLayouts}
				config={{ perPageSizes: [10, 25, 50, 100] }}
				empty={__('No update logs yet.', 'update-automate')}
				search
				searchLabel={__('Search logs', 'update-automate')}
			/>
		</div>
	);
}
