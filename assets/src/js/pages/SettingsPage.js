import { useState, useEffect } from '@wordpress/element';
import { Notices } from '../components/Notices';
import { Tabs } from '../components/Tabs';
import { LogsTable } from '../components/LogsTable';
import { SettingsForm } from '../components/SettingsForm';
import { __ } from '@wordpress/i18n';

const TAB_LOGS = 'logs';
const TAB_SETTINGS = 'settings';

/**
 * Main settings page for Updates Control (Zenpress-style interface).
 * Tabs: Logs and Settings.
 *
 * @return {JSX.Element} The settings page UI.
 */
export const SettingsPage = () => {
	const [selectedTabId, setSelectedTabId] = useState(TAB_LOGS);

	useEffect(() => {
		if (!selectedTabId) {
			setSelectedTabId(TAB_LOGS);
		}
	}, [selectedTabId]);

	return (
		<article className="updatescontrol-row">
			<section className="updatescontrol-main">
				<div className="updatescontrol-notices">
					<Notices />
				</div>
				<div className="updatescontrol-panel">
					<Tabs
						orientation="vertical"
						selectedTabId={selectedTabId}
						onSelect={setSelectedTabId}
					>
						<Tabs.TabList>
							<Tabs.Tab
								tabId={TAB_LOGS}
								title={__('Update logs', 'updatescontrol')}
							>
								{__('Update logs', 'updatescontrol')}
							</Tabs.Tab>
							<Tabs.Tab
								tabId={TAB_SETTINGS}
								title={__('Settings', 'updatescontrol')}
							>
								{__('Settings', 'updatescontrol')}
							</Tabs.Tab>
						</Tabs.TabList>
						<Tabs.TabPanel tabId={TAB_LOGS}>
							<LogsTable />
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
