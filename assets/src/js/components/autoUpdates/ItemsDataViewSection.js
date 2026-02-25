/**
 * Shared DataViews section for Plugins and Themes.
 * Reduces duplication between PluginsSection and ThemesSection.
 */

import { useMemo, useState, useCallback } from '@wordpress/element';
import { ToggleControl, Icon } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { StatusBadge } from '../activityLog/StatusBadge';
import { ConstantNotices } from './ConstantNotices';

const FIXED_FIELDS = [
	'auto_update',
	'icon',
	'name',
	'status',
	'version',
	'description',
	'details',
];

/**
 * @param {Object} constants Map from PHP.
 * @param {string} section   'plugins' | 'themes'.
 * @return {boolean} True if the section is locked by a constant.
 */
function isSectionLocked(constants, section) {
	if (!constants) {
		return false;
	}
	return Object.values(constants).some(
		(info) => info.locks && info.value && info.affects.includes(section)
	);
}

/**
 * @param {Object}   props
 * @param {Array}    props.items        Plugin or theme items.
 * @param {string}   props.itemIdKey    'file' (plugins) | 'stylesheet' (themes).
 * @param {Object}   props.icon         Icon from @wordpress/icons.
 * @param {string}   props.sectionTitle e.g. 'Plugins' | 'Themes'.
 * @param {string}   props.itemLabel    e.g. 'Plugin' | 'Theme'.
 * @param {string}   props.searchLabel  e.g. 'Search plugins' | 'Search themes'.
 * @param {string}   props.uriKey       'plugin_uri' | 'theme_uri'.
 * @param {Object}   props.constants    Constant info from API.
 * @param {string[]} props.sections     e.g. ['plugins'] | ['themes'].
 * @param {Function} props.onToggle     (id, checked) => void.
 * @param {boolean}  props.busy
 * @return {JSX.Element} The items DataView section.
 */
export function ItemsDataViewSection({
	items,
	itemIdKey,
	icon,
	sectionTitle,
	itemLabel,
	searchLabel,
	uriKey,
	constants,
	sections,
	onToggle,
	busy,
}) {
	const getItemId = (item) => item[itemIdKey];
	const locked = isSectionLocked(constants, sections[0]);

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
					<span className="updateautomate-autoupdates__toggle">
						{item.auto_update_available === false ? (
							<span
								className="updateautomate-autoupdates__unavailable"
								title={__(
									'Automatic updates are not available for this item (e.g. not from WordPress.org).',
									'update-automate'
								)}
							>
								{__('Unavailable', 'update-automate')}
							</span>
						) : (
							<ToggleControl
								__nextHasNoMarginBottom
								checked={item.auto_update}
								onChange={(checked) =>
									onToggle(item[itemIdKey], checked)
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
						)}
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
					<span className="updateautomate-autoupdates__icon">
						{item.icon ? (
							<img
								className="updateautomate-autoupdates__icon-img"
								src={item.icon}
								alt=""
								width={32}
								height={32}
								loading="lazy"
							/>
						) : (
							<Icon
								icon={icon}
								size={32}
								className="updateautomate-autoupdates__icon-fallback"
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
				label: itemLabel,
				getValue: ({ item }) => item.name,
				render: ({ item }) => (
					<span className="updateautomate-autoupdates__name">
						{item.name}
					</span>
				),
				enableSorting: false,
				enableGlobalSearch: true,
			},
			{
				id: 'status',
				label: __('Status', 'update-automate'),
				render: ({ item }) => (
					<span className="updateautomate-autoupdates__status">
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
					<span className="updateautomate-autoupdates__version">
						{item.version}
					</span>
				),
				enableSorting: false,
				enableGlobalSearch: false,
			},
			{
				id: 'description',
				label: __('Description', 'update-automate'),
				getValue: ({ item }) => item.description,
				render: ({ item }) => (
					<span className="updateautomate-autoupdates__description">
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
					item[uriKey] ? (
						<a
							className="updateautomate-autoupdates__details-link"
							href={item[uriKey]}
							target="_blank"
							rel="noopener noreferrer"
						>
							{__('View', 'update-automate')}
						</a>
					) : (
						<span className="updateautomate-autoupdates__details-empty">
							—
						</span>
					),
				enableSorting: false,
				enableGlobalSearch: false,
			},
		],
		[onToggle, locked, busy, itemIdKey, icon, itemLabel, uriKey]
	);

	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate(items, view, fields),
		[items, view, fields]
	);

	return (
		<div className="updateautomate-autoupdates-section">
			<h3 className="updateautomate-autoupdates-section-title">
				<Icon icon={icon} size={24} />
				{sectionTitle}
			</h3>
			<ConstantNotices
				constants={constants}
				sections={sections}
				lockingOnly
			/>
			<DataViews
				getItemId={getItemId}
				view={view}
				onChangeView={handleChangeView}
				fields={fields}
				data={shownData}
				paginationInfo={paginationInfo}
				defaultLayouts={{ table: {} }}
				search
				searchLabel={searchLabel}
			/>
		</div>
	);
}
