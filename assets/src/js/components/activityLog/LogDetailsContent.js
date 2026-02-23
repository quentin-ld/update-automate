/**
 * Message and trace content for log detail modal / action.
 */
import { __ } from '@wordpress/i18n';

/**
 * @param {Object} props     Props.
 * @param {Object} props.log Log item with optional message, trace.
 * @return {JSX.Element} Message and trace sections or empty state.
 */
export function LogDetailsContent({ log }) {
	if (!log) {
		return null;
	}
	return (
		<div className="updateautomate-notes-content updateautomate-notes-modal">
			{log.message && (
				<div className="updateautomate-notes-section">
					<h4>{__('Message', 'update-automate')}</h4>
					<pre
						className="updateautomate-notes-text"
						style={{
							whiteSpace: 'pre-wrap',
							wordBreak: 'break-word',
						}}
					>
						{log.message}
					</pre>
				</div>
			)}
			{log.trace && (
				<div className="updateautomate-notes-section">
					<h4>{__('Trace', 'update-automate')}</h4>
					<pre
						className="updateautomate-notes-trace"
						style={{
							whiteSpace: 'pre-wrap',
							wordBreak: 'break-all',
							fontSize: '12px',
						}}
					>
						{log.trace}
					</pre>
				</div>
			)}
			{!log.message && !log.trace && (
				<p>
					{__(
						'No message or trace for this entry.',
						'update-automate'
					)}
				</p>
			)}
		</div>
	);
}
