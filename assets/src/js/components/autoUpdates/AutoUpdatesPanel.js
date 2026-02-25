/**
 * Auto-updates panel — centralised control for native WP auto-update settings.
 * Sections: Core, Plugins, Themes, Translations.
 * Uses DataViews (table layout) for plugin/theme lists.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 */

import {
	Spinner,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { plugins as pluginsIcon, brush as brushIcon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useAutoUpdates } from '../../hooks/useAutoUpdates';
import { ConstantNotices } from './ConstantNotices';
import { CoreSection } from './CoreSection';
import { ItemsDataViewSection } from './ItemsDataViewSection';
import { TranslationsSection } from './TranslationsSection';

/**
 * Auto-updates panel — renders inside a TabPanel.
 *
 * @return {JSX.Element} The auto-updates panel UI.
 */
export function AutoUpdatesPanel() {
	const {
		data,
		loading,
		busy,
		setCoreMode,
		togglePlugin,
		toggleTheme,
		toggleTranslation,
		dismissConstant,
	} = useAutoUpdates();

	if (loading || !data) {
		return (
			<div
				className="updateautomate-autoupdates-loading"
				aria-live="polite"
				role="status"
			>
				<Spinner />
				<span>
					{__('Loading auto-update settings…', 'update-automate')}
				</span>
			</div>
		);
	}

	return (
		<div className="updateautomate-autoupdates-panel">
			<h2 className="updateautomate-panel-title">
				{__('Auto-updates', 'update-automate')}
			</h2>
			<Text variant="muted">
				{__(
					'Choose which parts of your site update automatically: core, plugins, themes, and translations.',
					'update-automate'
				)}
			</Text>
			<ConstantNotices
				constants={data.constants}
				sections={['core', 'plugins', 'themes', 'translations']}
				dismissed={data.dismissed_constants || []}
				onDismiss={dismissConstant}
			/>

			<CoreSection
				core={data.core}
				constants={data.constants}
				setCoreMode={setCoreMode}
				busy={busy}
			/>

			<ItemsDataViewSection
				items={data.themes}
				itemIdKey="stylesheet"
				icon={brushIcon}
				sectionTitle={__('Themes', 'update-automate')}
				itemLabel={__('Theme', 'update-automate')}
				searchLabel={__('Search themes', 'update-automate')}
				uriKey="theme_uri"
				constants={data.constants}
				sections={['themes']}
				onToggle={toggleTheme}
				busy={busy}
			/>

			<ItemsDataViewSection
				items={data.plugins}
				itemIdKey="file"
				icon={pluginsIcon}
				sectionTitle={__('Plugins', 'update-automate')}
				itemLabel={__('Plugin', 'update-automate')}
				searchLabel={__('Search plugins', 'update-automate')}
				uriKey="plugin_uri"
				constants={data.constants}
				sections={['plugins']}
				onToggle={togglePlugin}
				busy={busy}
			/>

			<TranslationsSection
				translations={data.translations}
				constants={data.constants}
				toggleTranslation={toggleTranslation}
				busy={busy}
			/>
		</div>
	);
}
