/**
 * Pure helpers for activity log display and formatting.
 */
import { __ } from '@wordpress/i18n';
import { ACTION_LABELS } from './constants';

/**
 * Map log status to badge intent (success, warning, error, default).
 *
 * @param {string} status Log status value.
 * @return {string} Badge intent.
 */
export function statusToBadgeIntent(status) {
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
 * Human-readable date/time from log.
 *
 * @param {string} dateStr ISO date string.
 * @return {string} Formatted date or fallback.
 */
export function formatDate(dateStr) {
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
export function getContextLabel(updateContext) {
	if (updateContext === 'bulk') {
		return __('Bulk action', 'updatescontrol');
	}
	if (updateContext === 'single') {
		return __('Single action', 'updatescontrol');
	}
	return updateContext || '—';
}

/**
 * Build activity title: [item name] + " — " + [action].
 *
 * @param {Object} item Log item.
 * @return {string} Title.
 */
export function getActivityTitle(item) {
	const name = item.item_name || __('Item', 'updatescontrol');
	const actionLabel =
		ACTION_LABELS[item.action_type] ||
		item.action_display ||
		item.action_type ||
		'';
	return actionLabel ? `${name} — ${actionLabel}` : name;
}

/**
 * Build description: "from → to" or single version.
 *
 * @param {Object} item Log item.
 * @return {string} Description.
 */
export function getActivityDescription(item) {
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
