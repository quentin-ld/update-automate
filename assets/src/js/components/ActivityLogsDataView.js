/**
 * Activity Logs Data View — Gutenberg DataViews component for update logs.
 *
 * Follows the plugin approach from:
 * https://developer.wordpress.org/news/2024/08/using-data-views-to-display-and-interact-with-data-in-plugins/
 *
 * - Imports DataViews and filterSortAndPaginate from '@wordpress/dataviews/wp' (bundled build).
 * - Uses useState(view) + onChangeView(setView), useMemo(filterSortAndPaginate), defaultLayouts, actions.
 * - Styles: DataViews CSS imported in main SCSS; enqueue with wp-components dependency.
 *
 * Uses the official activity layout by default (see Block Editor Handbook and Storybook
 * layout-activity). Other view modes (table, list, grid) remain available.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 * @see docs/ACTIVITY-LOGS-DATAVIEW-SPEC.md
 */

import { useMemo, useState, useEffect } from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useLogs } from '../hooks/useLogs';

const LAYOUT_ACTIVITY = 'activity';
const LAYOUT_LIST = 'list';
const LAYOUT_TABLE = 'table';

const ACTION_LABELS = {
	update: __('Update', 'updatescontrol'),
	downgrade: __('Downgrade', 'updatescontrol'),
	install: __('Install', 'updatescontrol'),
	same_version: __('Reset', 'updatescontrol'),
	failed: __('Failed', 'updatescontrol'),
	uninstall: __('Uninstall', 'updatescontrol'),
	delete: __('Delete', 'updatescontrol'),
};

/**
 * Map log status to badge intent (success, warning, error, default).
 *
 * @param {string} status Log status value.
 * @return {string} Badge intent.
 */
function statusToBadgeIntent(status) {
	if (!status) {
		return 'default';
	}
	const s = String(status).toLowerCase();
	if (s === 'success' || s === 'updated' || s === 'ok') {
		return 'success';
	}
	if (s === 'warning' || s === 'warn') {
		return 'warning';
	}
	if (s === 'error' || s === 'failed') {
		return 'error';
	}
	return 'default';
}

/**
 * Status badge UI using design-system classes (Badge is not in public components export).
 *
 * @param {Object} props          Props.
 * @param {string} props.intent   Intent: success, warning, error, default.
 * @param {*}      props.children Content.
 * @return {JSX.Element} Span with badge styling.
 */
function StatusBadge({ intent = 'default', children }) {
	return (
		<span
			className={`components-badge is-${intent}`}
			role="status"
			aria-label={typeof children === 'string' ? children : undefined}
		>
			<span className="components-badge__content">{children}</span>
		</span>
	);
}

/**
 * Human-readable date/time from log.
 *
 * @param {string} dateStr ISO date string.
 * @return {string} Formatted date or fallback.
 */
function formatDate(dateStr) {
	if (!dateStr) {
		return '—';
	}
	try {
		return new Date(dateStr).toLocaleString();
	} catch {
		return dateStr;
	}
}

/**
 * Context label from update_context.
 *
 * @param {string} updateContext Raw context.
 * @return {string} Label.
 */
function getContextLabel(updateContext) {
	if (updateContext === 'bulk') {
		return __('Bulk', 'updatescontrol');
	}
	if (updateContext === 'single') {
		return __('Single', 'updatescontrol');
	}
	return updateContext || '—';
}

/**
 * Build activity title: [item name] + " updated" + " — " + [action].
 *
 * @param {Object} item Log item.
 * @return {string} Title.
 */
function getActivityTitle(item) {
	const name = item.item_name || __('Item', 'updatescontrol');
	const actionLabel =
		ACTION_LABELS[item.action_type] ||
		item.action_display ||
		item.action_type ||
		'';
	return actionLabel
		? `${name} ${__('updated', 'updatescontrol')} — ${actionLabel}`
		: name;
}

/**
 * Build description: "from → to" or single version.
 *
 * @param {Object} item Log item.
 * @return {string} Description.
 */
function getActivityDescription(item) {
	const from = item.version_before;
	const to = item.version_after;
	if (from && to) {
		return `v${from} → v${to}`;
	}
	if (to) {
		return `v${to}`;
	}
	if (from) {
		return `v${from}`;
	}
	return '—';
}

/**
 * Activity Logs Data View component.
 *
 * Renders update logs using Gutenberg DataViews (list/table), with search,
 * filters, View logs modal, and Delete with confirmation. Uses only official
 * Gutenberg components.
 *
 * @return {JSX.Element} Data view UI or error state.
 */
export function ActivityLogsDataView() {
	const { logs, loading, error, fetchLogs, deleteLog } = useLogs();
	// Activity layout by default (official layout type per Block Editor Handbook).
	// view.fields lists only the extra row fields; title/description come from titleField/descriptionField.
	const [view, setView] = useState({
		type: LAYOUT_ACTIVITY,
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		sort: { field: 'date', direction: 'desc' },
		fields: ['date', 'context', 'user', 'status'],
		titleField: 'title',
		descriptionField: 'description',
	});

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
		],
		[]
	);

	// DataViews wraps RenderModal in its own Modal; return only content to avoid double modal.
	const actions = useMemo(
		() => [
			{
				id: 'view-logs',
				label: __('View logs', 'updatescontrol'),
				modalHeader: __('Log details', 'updatescontrol'),
				isEligible: (item) => !!(item.message || item.trace),
				RenderModal: ({ items }) => {
					const log = items[0];
					if (!log) {
						return null;
					}
					return (
						<div className="updatescontrol-notes-content updatescontrol-notes-modal">
							{log.message && (
								<div className="updatescontrol-notes-section">
									<h4>{__('Message', 'updatescontrol')}</h4>
									<pre
										className="updatescontrol-notes-text"
										style={{
											whiteSpace: 'pre-wrap',
											wordBreak: 'break-word',
										}}
									>
										{log.message}
									</pre>
								</div>
							)}
							{log.trace && (
								<div className="updatescontrol-notes-section">
									<h4>{__('Trace', 'updatescontrol')}</h4>
									<pre
										className="updatescontrol-notes-trace"
										style={{
											whiteSpace: 'pre-wrap',
											wordBreak: 'break-all',
											fontSize: '12px',
										}}
									>
										{log.trace}
									</pre>
								</div>
							)}
							{!log.message && !log.trace && (
								<p>
									{__(
										'No message or trace for this entry.',
										'updatescontrol'
									)}
								</p>
							)}
						</div>
					);
				},
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

	// defaultLayouts: activity as default; table, list kept. Per handbook: table, grid, list, activity.
	const primaryField = 'title';
	const defaultLayouts = useMemo(
		() => ({
			[LAYOUT_ACTIVITY]: {
				sort: { field: 'date', direction: 'desc' },
			},
			[LAYOUT_LIST]: {},
			[LAYOUT_TABLE]: {
				layout: { primaryField },
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

	return (
		<div className="updatescontrol-logs updatescontrol-activity-dataview">
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
