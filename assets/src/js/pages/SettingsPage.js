import { useState, useEffect, useCallback } from '@wordpress/element';
import { pages as iconPages, settings as iconSettings } from '@wordpress/icons';
import { Notices } from '../components/Notices';
import { Tabs } from '../components/Tabs';
import { ActivityLogsDataView } from '../components/activityLog';
import { SettingsForm } from '../components/SettingsForm';
import { __ } from '@wordpress/i18n';

const TAB_LOGS = 'logs';
const TAB_SETTINGS = 'settings';
const VALID_TABS = [TAB_LOGS, TAB_SETTINGS];

function getTabFromUrl() {
	const params = new URLSearchParams(window.location.search);
	const tab = params.get('tab');
	return VALID_TABS.includes(tab) ? tab : TAB_LOGS;
}

function setTabInUrl(tabId) {
	const params = new URLSearchParams(window.location.search);
	params.set('tab', tabId);
	const url = `${window.location.pathname}?${params.toString()}`;
	window.history.replaceState(null, '', url);
}

/**
 * Main settings page for Update Automate (Zenpress-style interface).
 * Tabs: Logs and Settings. Active tab is synced with ?tab= URL param for direct links.
 *
 * @return {JSX.Element} The settings page UI.
 */
export const SettingsPage = () => {
	const [selectedTabId, setSelectedTabId] = useState(getTabFromUrl);

	const handleSelectTab = useCallback((tabId) => {
		setSelectedTabId(tabId);
		setTabInUrl(tabId);
	}, []);

	useEffect(() => {
		if (!selectedTabId || !VALID_TABS.includes(selectedTabId)) {
			setSelectedTabId(TAB_LOGS);
			setTabInUrl(TAB_LOGS);
		}
	}, [selectedTabId]);

	return (
		<article className="updateautomate-row">
			<section className="updateautomate-main">
				<div className="updateautomate-notices">
					<Notices />
				</div>
				<div className="updateautomate-panel">
					<Tabs
						orientation="vertical"
						selectedTabId={selectedTabId}
						onSelect={handleSelectTab}
					>
						<Tabs.TabList>
							<Tabs.Tab
								tabId={TAB_LOGS}
								title={__('Update logs', 'update-automate')}
								icon={iconPages}
							>
								{__('Update logs', 'update-automate')}
							</Tabs.Tab>
							<Tabs.Tab
								tabId={TAB_SETTINGS}
								title={__('Settings', 'update-automate')}
								icon={iconSettings}
							>
								{__('Settings', 'update-automate')}
							</Tabs.Tab>
						</Tabs.TabList>
						<Tabs.TabPanel tabId={TAB_LOGS}>
							<ActivityLogsDataView />
						</Tabs.TabPanel>
						<Tabs.TabPanel tabId={TAB_SETTINGS}>
							<SettingsForm />
						</Tabs.TabPanel>
					</Tabs>
				</div>
			</section>
		</article>
	);
};
