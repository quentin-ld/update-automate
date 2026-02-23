import {
	useState,
	createContext,
	useContext,
	useRef,
	useEffect,
} from '@wordpress/element';
import { Icon } from '@wordpress/icons';

const TabsContext = createContext();

/**
 * Custom Tabs component with vertical orientation support
 *
 * @param {Object}   props               - Component props.
 * @param {string}   props.selectedTabId - Currently selected tab ID.
 * @param {Function} props.onSelect      - Callback when tab is selected.
 * @param {string}   props.orientation   - Tab orientation ('vertical' or 'horizontal').
 * @param {Object}   props.children      - Child components (TabList and TabPanels).
 * @return {JSX.Element} The tabs container.
 */
export const Tabs = ({
	selectedTabId: controlledId,
	onSelect,
	orientation = 'horizontal',
	children,
}) => {
	const [internalId, setInternalId] = useState();
	const tabListRef = useRef(null);
	const selectedId = controlledId !== undefined ? controlledId : internalId;
	const handleSelect = (id) => {
		if (controlledId === undefined) {
			setInternalId(id);
		}
		onSelect?.(id);
	};

	const getOrderedTabIds = () => {
		if (!tabListRef.current) {
			return [];
		}

		const tabs = Array.from(
			tabListRef.current.querySelectorAll('[role="tab"]')
		);
		return tabs
			.map((tab) => {
				const id = tab.getAttribute('id');
				return id ? id.replace('updatescontrol-tab-', '') : null;
			})
			.filter(Boolean);
	};

	return (
		<TabsContext.Provider
			value={{
				selectedTabId: selectedId,
				onSelect: handleSelect,
				orientation,
				getOrderedTabIds,
				tabListRef,
			}}
		>
			<div
				className={`updatescontrol-tabs updatescontrol-tabs--${orientation}`}
			>
				{children}
			</div>
		</TabsContext.Provider>
	);
};

/**
 * TabList component - container for Tab components
 *
 * @param {Object} props          - Component props.
 * @param {Object} props.children - Tab components.
 * @return {JSX.Element} The tab list container.
 */
export const TabList = ({ children }) => {
	const { orientation, tabListRef } = useContext(TabsContext);

	return (
		<div
			ref={tabListRef}
			className={`updatescontrol-tabs__list updatescontrol-tabs__list--${orientation}`}
			role="tablist"
			aria-orientation={orientation}
		>
			{children}
		</div>
	);
};

/**
 * Tab component - individual tab button
 *
 * @param {Object}   props           - Component props.
 * @param {string}   props.tabId     - Unique identifier for the tab.
 * @param {string}   props.title    - Tab title (optional, uses children if not provided).
 * @param {Object}   props.icon     - Optional WordPress icon (e.g. from @wordpress/icons).
 * @param {number}   props.iconSize - Icon size in pixels (default 24).
 * @param {string}   props.className - Additional CSS class name.
 * @param {Object}   props.children  - Tab content.
 * @return {JSX.Element} The tab button.
 */
export const Tab = ({
	tabId,
	title,
	icon,
	iconSize = 24,
	className = '',
	children,
}) => {
	const { selectedTabId, onSelect, orientation, getOrderedTabIds } =
		useContext(TabsContext);
	const isSelected = selectedTabId === tabId;
	const tabRef = useRef(null);

	// Handle keyboard navigation according to W3C ARIA pattern
	const handleKeyDown = (e) => {
		const tabIds = getOrderedTabIds();
		if (!tabIds || tabIds.length === 0) {
			return;
		}

		const currentIndex = tabIds.indexOf(tabId);
		if (currentIndex === -1) {
			return;
		}

		let targetIndex = currentIndex;

		if (orientation === 'vertical') {
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				targetIndex =
					currentIndex < tabIds.length - 1 ? currentIndex + 1 : 0;
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				targetIndex =
					currentIndex > 0 ? currentIndex - 1 : tabIds.length - 1;
			}
		} else if (e.key === 'ArrowRight') {
			e.preventDefault();
			targetIndex =
				currentIndex < tabIds.length - 1 ? currentIndex + 1 : 0;
		} else if (e.key === 'ArrowLeft') {
			e.preventDefault();
			targetIndex =
				currentIndex > 0 ? currentIndex - 1 : tabIds.length - 1;
		}

		if (e.key === 'Home') {
			e.preventDefault();
			targetIndex = 0;
		} else if (e.key === 'End') {
			e.preventDefault();
			targetIndex = tabIds.length - 1;
		}

		if (e.key === ' ' || e.key === 'Enter') {
			e.preventDefault();
			onSelect(tabId);
			return;
		}

		if (
			targetIndex !== currentIndex &&
			targetIndex >= 0 &&
			targetIndex < tabIds.length
		) {
			const targetTabId = tabIds[targetIndex];
			const targetTabElement = document.getElementById(
				`updatescontrol-tab-${targetTabId}`
			);
			if (targetTabElement) {
				targetTabElement.focus();
				onSelect(targetTabId);
			}
		}
	};

	const handleFocus = () => {
		if (!isSelected) {
			onSelect(tabId);
		}
	};

	return (
		<button
			ref={tabRef}
			className={`updatescontrol-tabs__tab ${isSelected ? 'updatescontrol-tabs__tab--is-active' : ''} ${icon ? 'updatescontrol-tabs__tab--has-icon' : ''} ${className}`.trim()}
			role="tab"
			aria-selected={isSelected}
			aria-controls={`updatescontrol-tab-panel-${tabId}`}
			id={`updatescontrol-tab-${tabId}`}
			tabIndex={isSelected ? 0 : -1}
			onClick={() => onSelect(tabId)}
			onKeyDown={handleKeyDown}
			onFocus={handleFocus}
		>
			{icon && (
				<span className="updatescontrol-tabs__tab-icon" aria-hidden>
					<Icon icon={icon} size={iconSize} />
				</span>
			)}
			<span className="updatescontrol-tabs__tab-label">
				{title || children}
			</span>
		</button>
	);
};

/**
 * TabPanel component - container for tab content
 *
 * @param {Object} props          - Component props.
 * @param {string} props.tabId    - Unique identifier matching a Tab's tabId.
 * @param {Object} props.children - Panel content.
 * @return {JSX.Element|null} The tab panel or null if not selected.
 */
export const TabPanel = ({ tabId, children }) => {
	const { selectedTabId } = useContext(TabsContext);
	const panelRef = useRef(null);
	const isSelected = selectedTabId === tabId;

	useEffect(() => {
		if (isSelected && panelRef.current) {
			const focusableElements = panelRef.current.querySelectorAll(
				'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])'
			);
			if (focusableElements.length === 0) {
				panelRef.current.setAttribute('tabindex', '0');
			} else {
				panelRef.current.removeAttribute('tabindex');
			}
		}
	}, [isSelected]);

	if (!isSelected) {
		return (
			<div
				className="updatescontrol-tabs__panel"
				role="tabpanel"
				id={`updatescontrol-tab-panel-${tabId}`}
				aria-labelledby={`updatescontrol-tab-${tabId}`}
				hidden
			>
				{children}
			</div>
		);
	}

	return (
		<div
			ref={panelRef}
			className="updatescontrol-tabs__panel"
			role="tabpanel"
			id={`updatescontrol-tab-panel-${tabId}`}
			aria-labelledby={`updatescontrol-tab-${tabId}`}
		>
			{children}
		</div>
	);
};

Tabs.TabList = TabList;
Tabs.Tab = Tab;
Tabs.TabPanel = TabPanel;
