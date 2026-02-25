/**
 * Auto Updates Panel — centralised control for native WP auto-update settings.
 *
 * Sections: Core, Plugins, Themes, Translations.
 * Uses DataViews (table layout) for plugin/theme lists.
 */

import { useMemo, useState, useCallback } from '@wordpress/element';
import {
	Spinner,
	Notice,
	RadioControl,
	ToggleControl,
	Icon,
} from '@wordpress/components';
import { plugins as pluginsIcon, brush as brushIcon } from '@wordpress/icons';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { useAutoUpdates } from '../hooks/useAutoUpdates';
import { StatusBadge } from './activityLog/StatusBadge';

const FIXED_FIELDS = [
	'auto_update',
	'icon',
	'name',
	'status',
	'version',
	'description',
	'details',
];

const CONSTANT_DESCRIPTIONS = {
	WP_AUTO_UPDATE_CORE: __(
		'The WP_AUTO_UPDATE_CORE constant is defined in wp-config.php and overrides the core auto-update setting below.',
		'update-automate'
	),
	AUTOMATIC_UPDATER_DISABLED: __(
		'The AUTOMATIC_UPDATER_DISABLED constant is set to true in wp-config.php. All automatic updates are disabled at the WordPress level.',
		'update-automate'
	),
	DISALLOW_FILE_MODS: __(
		'The DISALLOW_FILE_MODS constant is set to true in wp-config.php. WordPress cannot modify files, so all automatic updates are blocked.',
		'update-automate'
	),
	DISABLE_WP_CRON: __(
		'The DISABLE_WP_CRON constant is set to true in wp-config.php. Background auto-updates rely on WP-Cron and will not run unless an external cron is configured.',
		'update-automate'
	),
};

/**
 * Constant warning notices.
 *
 * @param {Object}   props
 * @param {Object}   props.constants     Map of constant name → { defined, value, affects, locks }.
 * @param {string[]} props.sections      Sections to filter for ('core', 'plugins', etc.).
 * @param {boolean}  [props.lockingOnly] When true, only show constants with locks=true.
 * @param {string[]} [props.dismissed]   List of dismissed constant names.
 * @param {Function} [props.onDismiss]   Called with constant name when dismissed.
 */
function ConstantNotices({
	constants,
	sections,
	lockingOnly = false,
	dismissed = [],
	onDismiss,
}) {
	if (!constants || Object.keys(constants).length === 0) {
		return null;
	}

	const relevant = Object.entries(constants).filter(
		([name, info]) =>
			info.affects.some((s) => sections.includes(s)) &&
			(!lockingOnly || info.locks) &&
			!dismissed.includes(name)
	);

	if (relevant.length === 0) {
		return null;
	}

	return relevant.map(([name, info]) => (
		<Notice
			key={name}
			status="warning"
			isDismissible={!info.locks}
			onDismiss={
				!info.locks && onDismiss ? () => onDismiss(name) : undefined
			}
		>
			<strong>{name}</strong>
			<br />
			{CONSTANT_DESCRIPTIONS[name] || name}
		</Notice>
	));
}

/**
 * Check whether a section is entirely locked by a constant.
 *
 * @param {Object} constants Map from PHP.
 * @param {string} section   'core' | 'plugins' | 'themes' | 'translations'.
 * @return {boolean} True if locked by a global constant.
 */
function isSectionLocked(constants, section) {
	if (!constants) {
		return false;
	}
	return Object.values(constants).some(
		(info) => info.locks && info.value && info.affects.includes(section)
	);
}

// ─── Core section ────────────────────────────────────────────────────────────

function CoreSection({ core, constants, setCoreMode, busy }) {
	const locked =
		core.overridden_by_constant || isSectionLocked(constants, 'core');

	const options = [
		{
			label: __(
				'Minor releases only (default — e.g. 6.4.1 → 6.4.2)',
				'update-automate'
			),
			value: 'minor',
		},
		{
			label: __(
				'All releases — major and minor (e.g. 6.4 → 6.5)',
				'update-automate'
			),
			value: 'all',
		},
		{
			label: __(
				'Disabled — no automatic core updates',
				'update-automate'
			),
			value: 'disabled',
		},
	];

	return (
		<div className="updateautomate-autoupdates-section">
			<h3 className="updateautomate-autoupdates-section-title">
				{__('WordPress Core', 'update-automate')}
			</h3>
			<ConstantNotices
				constants={constants}
				sections={['core']}
				lockingOnly
			/>
			<RadioControl
				label={__('Core auto-update behaviour', 'update-automate')}
				selected={core.mode}
				options={options}
				onChange={(value) => setCoreMode(value)}
				disabled={locked || busy}
				help={
					locked
						? __(
								'This setting is managed by a wp-config.php constant and cannot be changed here.',
								'update-automate'
							)
						: ''
				}
			/>
		</div>
	);
}

// ─── Plugins section ─────────────────────────────────────────────────────────

function PluginsSection({ plugins, constants, togglePlugin, busy }) {
	const locked = isSectionLocked(constants, 'plugins');

	const [view, setView] = useState({
		type: 'table',
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		fields: FIXED_FIELDS,
	});

	const handleChangeView = useCallback((nextView) => {
		setView(() => ({
			...nextView,
			fields: FIXED_FIELDS,
		}));
	}, []);

	const fields = useMemo(
		() => [
			{
				id: 'auto_update',
				label: __('Auto-update', 'update-automate'),
				render: ({ item }) => (
					<span className="autoUpdates__toggle">
						<ToggleControl
							__nextHasNoMarginBottom
							checked={item.auto_update}
							onChange={(checked) =>
								togglePlugin(item.file, checked)
							}
							disabled={locked || busy}
							aria-label={
								item.auto_update
									? __(
											'Disable auto-update',
											'update-automate'
										)
									: __(
											'Enable auto-update',
											'update-automate'
										)
							}
						/>
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'icon',
				label: '',
				render: ({ item }) => (
					<span className="autoUpdates__icon">
						{item.icon ? (
							<img
								className="autoUpdates__iconImg"
								src={item.icon}
								alt=""
								width={32}
								height={32}
								loading="lazy"
							/>
						) : (
							<Icon
								icon={pluginsIcon}
								size={32}
								className="autoUpdates__iconFallback"
							/>
						)}
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'name',
				label: __('Plugin', 'update-automate'),
				getValue: ({ item }) => item.name,
				render: ({ item }) => (
					<span className="autoUpdates__name">{item.name}</span>
				),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'status',
				label: __('Status', 'update-automate'),
				render: ({ item }) => (
					<span className="autoUpdates__status">
						{item.active ? (
							<StatusBadge intent="success">
								{__('Active', 'update-automate')}
							</StatusBadge>
						) : (
							<StatusBadge intent="warning">
								{__('Inactive', 'update-automate')}
							</StatusBadge>
						)}
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'version',
				label: __('Version', 'update-automate'),
				getValue: ({ item }) => item.version,
				render: ({ item }) => (
					<span className="autoUpdates__version">{item.version}</span>
				),
				enableSorting: false,
				enableGlobalSearch: false,
			},
			{
				id: 'description',
				label: __('Description', 'update-automate'),
				getValue: ({ item }) => item.description,
				render: ({ item }) => (
					<span className="autoUpdates__description">
						{item.description}
					</span>
				),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'details',
				label: __('Details', 'update-automate'),
				render: ({ item }) =>
					item.plugin_uri ? (
						<a
							className="autoUpdates__detailsLink"
							href={item.plugin_uri}
							target="_blank"
							rel="noopener noreferrer"
						>
							{__('View', 'update-automate')}
						</a>
					) : (
						<span className="autoUpdates__detailsEmpty">—</span>
					),
				enableSorting: false,
				enableGlobalSearch: false,
			},
		],
		[togglePlugin, locked, busy]
	);

	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate(plugins, view, fields),
		[plugins, view, fields]
	);

	return (
		<div className="updateautomate-autoupdates-section">
			<h3 className="updateautomate-autoupdates-section-title">
				{__('Plugins', 'update-automate')}
			</h3>
			<ConstantNotices
				constants={constants}
				sections={['plugins']}
				lockingOnly
			/>
			<DataViews
				getItemId={(item) => item.file}
				view={view}
				onChangeView={handleChangeView}
				fields={fields}
				data={shownData}
				paginationInfo={paginationInfo}
				defaultLayouts={{ table: {} }}
				search
				searchLabel={__('Search plugins', 'update-automate')}
			/>
		</div>
	);
}

// ─── Themes section ──────────────────────────────────────────────────────────

function ThemesSection({ themes, constants, toggleTheme, busy }) {
	const locked = isSectionLocked(constants, 'themes');

	const [view, setView] = useState({
		type: 'table',
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		fields: FIXED_FIELDS,
	});

	const handleChangeView = useCallback((nextView) => {
		setView(() => ({
			...nextView,
			fields: FIXED_FIELDS,
		}));
	}, []);

	const fields = useMemo(
		() => [
			{
				id: 'auto_update',
				label: __('Auto-update', 'update-automate'),
				render: ({ item }) => (
					<span className="autoUpdates__toggle">
						<ToggleControl
							__nextHasNoMarginBottom
							checked={item.auto_update}
							onChange={(checked) =>
								toggleTheme(item.stylesheet, checked)
							}
							disabled={locked || busy}
							aria-label={
								item.auto_update
									? __(
											'Disable auto-update',
											'update-automate'
										)
									: __(
											'Enable auto-update',
											'update-automate'
										)
							}
						/>
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'icon',
				label: '',
				render: ({ item }) => (
					<span className="autoUpdates__icon">
						{item.icon ? (
							<img
								className="autoUpdates__iconImg"
								src={item.icon}
								alt=""
								width={32}
								height={32}
								loading="lazy"
							/>
						) : (
							<Icon
								icon={brushIcon}
								size={32}
								className="autoUpdates__iconFallback"
							/>
						)}
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'name',
				label: __('Theme', 'update-automate'),
				getValue: ({ item }) => item.name,
				render: ({ item }) => (
					<span className="autoUpdates__name">{item.name}</span>
				),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'status',
				label: __('Status', 'update-automate'),
				render: ({ item }) => (
					<span className="autoUpdates__status">
						{item.active ? (
							<StatusBadge intent="success">
								{__('Active', 'update-automate')}
							</StatusBadge>
						) : (
							<StatusBadge intent="warning">
								{__('Inactive', 'update-automate')}
							</StatusBadge>
						)}
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'version',
				label: __('Version', 'update-automate'),
				getValue: ({ item }) => item.version,
				render: ({ item }) => (
					<span className="autoUpdates__version">{item.version}</span>
				),
				enableSorting: false,
				enableGlobalSearch: false,
			},
			{
				id: 'description',
				label: __('Description', 'update-automate'),
				getValue: ({ item }) => item.description,
				render: ({ item }) => (
					<span className="autoUpdates__description">
						{item.description}
					</span>
				),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'details',
				label: __('Details', 'update-automate'),
				render: ({ item }) =>
					item.theme_uri ? (
						<a
							className="autoUpdates__detailsLink"
							href={item.theme_uri}
							target="_blank"
							rel="noopener noreferrer"
						>
							{__('View', 'update-automate')}
						</a>
					) : (
						<span className="autoUpdates__detailsEmpty">—</span>
					),
				enableSorting: false,
				enableGlobalSearch: false,
			},
		],
		[toggleTheme, locked, busy]
	);

	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate(themes, view, fields),
		[themes, view, fields]
	);

	return (
		<div className="updateautomate-autoupdates-section">
			<h3 className="updateautomate-autoupdates-section-title">
				{__('Themes', 'update-automate')}
			</h3>
			<ConstantNotices
				constants={constants}
				sections={['themes']}
				lockingOnly
			/>
			<DataViews
				getItemId={(item) => item.stylesheet}
				view={view}
				onChangeView={handleChangeView}
				fields={fields}
				data={shownData}
				paginationInfo={paginationInfo}
				defaultLayouts={{ table: {} }}
				search
				searchLabel={__('Search themes', 'update-automate')}
			/>
		</div>
	);
}

// ─── Translations section ────────────────────────────────────────────────────

function TranslationsSection({
	translations,
	constants,
	toggleTranslation,
	busy,
}) {
	const locked = isSectionLocked(constants, 'translations');

	return (
		<div className="updateautomate-autoupdates-section">
			<h3 className="updateautomate-autoupdates-section-title">
				{__('Translations', 'update-automate')}
			</h3>
			<ConstantNotices
				constants={constants}
				sections={['translations']}
				lockingOnly
			/>
			<ToggleControl
				label={__(
					'Enable automatic translation updates',
					'update-automate'
				)}
				help={__(
					'WordPress updates translations automatically by default. Disable this to prevent automatic translation downloads.',
					'update-automate'
				)}
				checked={translations.auto_update}
				onChange={(checked) => toggleTranslation(checked)}
				disabled={locked || busy}
			/>
		</div>
	);
}

// ─── Main panel ──────────────────────────────────────────────────────────────

/**
 * Auto-updates panel — renders inside a TabPanel.
 *
 * @return {JSX.Element} The auto-updates panel.
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
			<div className="updateautomate-autoupdates-loading">
				<Spinner />
				<span>
					{__('Loading auto-update settings…', 'update-automate')}
				</span>
			</div>
		);
	}

	return (
		<div className="updateautomate-autoupdates-panel">
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

			<TranslationsSection
				translations={data.translations}
				constants={data.constants}
				toggleTranslation={toggleTranslation}
				busy={busy}
			/>

			<ThemesSection
				themes={data.themes}
				constants={data.constants}
				toggleTheme={toggleTheme}
				busy={busy}
			/>

			<PluginsSection
				plugins={data.plugins}
				constants={data.constants}
				togglePlugin={togglePlugin}
				busy={busy}
			/>
		</div>
	);
}
