/**
 * Safe read of window.updatescontrolIntegrationsActive (plain object only).
 *
 * @return {Object} Map of integration key to boolean; empty if missing or invalid.
 */
export function getActiveIntegrations() {
	const raw =
		typeof window !== 'undefined'
			? window.updatescontrolIntegrationsActive
			: null;
	if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
		return {};
	}
	return raw;
}

/**
 * Whether at least one integration is active.
 *
 * @return {boolean} True if at least one integration is active.
 */
export function hasActiveIntegration() {
	return Object.values(getActiveIntegrations()).some(Boolean);
}
