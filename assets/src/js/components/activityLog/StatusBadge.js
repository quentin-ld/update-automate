/**
 * Status badge UI using design-system classes (Badge is not in public components export).
 */

/**
 * @param {Object} props          Props.
 * @param {string} props.intent   Intent: success, warning, error, default.
 * @param {*}      props.children Content.
 * @return {JSX.Element} Span with badge styling.
 */
export function StatusBadge({ intent = 'default', children }) {
	return (
		<span
			className={`components-badge is-${intent}`}
			role="status"
			aria-label={typeof children === 'string' ? children : undefined}
		>
			<span className="components-badge__content">{children}</span>
		</span>
	);
}
