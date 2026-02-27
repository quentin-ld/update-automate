import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	timeToRead as iconLogs,
	settings as iconSettings,
	update as iconUpdate,
} from '@wordpress/icons';
import { Notices } from '../components/Notices';
import { Tabs } from '../components/Tabs';
import { ActivityLogPanel } from '../components/activityLog';
import { SettingsPanel } from '../components/SettingsPanel';
import { AutoUpdatesPanel } from '../components/autoUpdates';
import { usePluginSettings } from '../hooks/usePluginSettings';
import { __ } from '@wordpress/i18n';

const TAB_LOGS = 'logs';
const TAB_AUTO_UPDATES = 'auto-updates';
const TAB_SETTINGS = 'settings';
const VALID_TABS = [TAB_LOGS, TAB_AUTO_UPDATES, TAB_SETTINGS];

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
 * Main settings page for Update Automate.
 * Tabs: Logs and Settings. Active tab is synced with ?tab= URL param for direct links.
 *
 * @return {JSX.Element} The settings page UI.
 */
export const SettingsPage = () => {
	const { settings, setSettings, saveSettings, saving } = usePluginSettings();
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
								icon={iconLogs}
							>
								{__('Update logs', 'update-automate')}
							</Tabs.Tab>
							<Tabs.Tab
								tabId={TAB_AUTO_UPDATES}
								title={__('Auto-updates', 'update-automate')}
								icon={iconUpdate}
							>
								{__('Auto-updates', 'update-automate')}
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
							<ActivityLogPanel
								loggingEnabled={settings.logging_enabled}
							/>
						</Tabs.TabPanel>
						<Tabs.TabPanel tabId={TAB_AUTO_UPDATES}>
							<AutoUpdatesPanel />
						</Tabs.TabPanel>
						<Tabs.TabPanel tabId={TAB_SETTINGS}>
							<SettingsPanel
								settings={settings}
								setSettings={setSettings}
								saveSettings={saveSettings}
								saving={saving}
							/>
						</Tabs.TabPanel>
					</Tabs>
				</div>
			</section>
		</article>
	);
};
