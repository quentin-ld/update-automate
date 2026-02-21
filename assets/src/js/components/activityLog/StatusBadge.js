/**
 * Status badge UI matching Gutenberg Badge (icon + label). Uses design-system
 * classes; Badge is not in the public @wordpress/components export.
 */
import { Icon } from '@wordpress/components';
import { published, caution, error as errorIcon } from '@wordpress/icons';

/**
 * Icon for the given intent (matches Gutenberg Badge context-based icons).
 *
 * @param {string} intent success, warning, error, default.
 * @return {Object|null} Icon definition or null for default.
 */
function intentIcon(intent) {
	switch (intent) {
		case 'success':
			return published;
		case 'warning':
			return caution;
		case 'error':
			return errorIcon;
		default:
			return null;
	}
}

/**
 * @param {Object} props          Props.
 * @param {string} props.intent   Intent: success, warning, error, default.
 * @param {*}      props.children Content.
 * @return {JSX.Element} Span with badge styling and optional icon.
 */
export function StatusBadge({ intent = 'default', children }) {
	const icon = intentIcon(intent);
	const hasIcon = !!icon;

	return (
		<span
			className={`components-badge is-${intent}${hasIcon ? ' has-icon' : ''}`}
			role="status"
			aria-label={typeof children === 'string' ? children : undefined}
		>
			<span className="components-badge__flex-wrapper">
				{hasIcon && (
					<Icon
						icon={icon}
						size={16}
						fill="currentColor"
						className="components-badge__icon"
					/>
				)}
				<span className="components-badge__content">{children}</span>
			</span>
		</span>
	);
}
