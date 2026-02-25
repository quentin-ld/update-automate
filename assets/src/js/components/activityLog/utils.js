/**
 * Pure helpers for activity log display and formatting.
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import { ACTION_LABELS } from './constants';

/** Translated fallback for empty values (em dash). */
export const EMPTY_FALLBACK = _x(
	'—',
	'empty value fallback',
	'update-automate'
);

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
		return EMPTY_FALLBACK;
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
		return __('Bulk action', 'update-automate');
	}
	if (updateContext === 'single') {
		return __('Single action', 'update-automate');
	}
	return updateContext || EMPTY_FALLBACK;
}

/**
 * Build activity title: [item name] + " — " + [action].
 *
 * @param {Object} item Log item.
 * @return {string} Title.
 */
export function getActivityTitle(item) {
	const name = item.item_name || __('Item', 'update-automate');
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
		return sprintf(
			/* translators: 1: previous version number, 2: new version number */
			__('v%1$s → v%2$s', 'update-automate'),
			from,
			to
		);
	}
	if (to) {
		return sprintf(
			/* translators: %s: version number */
			__('v%s', 'update-automate'),
			to
		);
	}
	if (from) {
		return sprintf(
			/* translators: %s: version number */
			__('v%s', 'update-automate'),
			from
		);
	}
	return EMPTY_FALLBACK;
}
