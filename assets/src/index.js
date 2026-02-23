import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { SettingsPage } from './js/pages/SettingsPage';

import './index.scss';

/**
 * Render the updateautomate settings page once the DOM is ready.
 * Notices use the default context (same as zenpress); wp-data and wp-notices are script dependencies.
 */
domReady(() => {
	const rootEl = document.getElementById('updateautomate-settings');
	if (!rootEl || !(rootEl instanceof HTMLElement)) {
		return;
	}
	const root = createRoot(rootEl);
	root.render(<SettingsPage />);
});
