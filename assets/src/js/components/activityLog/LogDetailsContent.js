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
		<div className="updatescontrol-notes-content updatescontrol-notes-modal">
			{log.message && (
				<div className="updatescontrol-notes-section">
					<h4>{__('Message', 'updatescontrol')}</h4>
					<pre
						className="updatescontrol-notes-text"
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
				<div className="updatescontrol-notes-section">
					<h4>{__('Trace', 'updatescontrol')}</h4>
					<pre
						className="updatescontrol-notes-trace"
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
						'updatescontrol'
					)}
				</p>
			)}
		</div>
	);
}
