import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import './index.scss';
import { SettingsPage } from './js/pages/SettingsPage';

/**
 * Render the updatescontrol settings page once the DOM is ready.
 */
domReady(() => {
	const rootEl = document.getElementById('updatescontrol-settings');
	if (!rootEl || !(rootEl instanceof HTMLElement)) {
		return;
	}
	const root = createRoot(rootEl);
	root.render(<SettingsPage />);
});
