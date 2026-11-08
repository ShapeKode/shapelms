/**
 * Builder: schedule publishing.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Notice, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const DEFAULT_TIME = '09:00';

/**
 * Readable text for a site-time value (Y-m-d\TH:i). Built from its parts so the browser's
 * own time zone cannot shift it.
 *
 * @param {string} value     Date and time.
 * @param {string} dateStyle Intl date style.
 * @return {string} Text.
 */
export function formatSchedule( value, dateStyle = 'full' ) {
	const [ date, time ] = value.split( 'T' );
	const [ year, month, day ] = date.split( '-' ).map( Number );
	const [ hour, minute ] = time.split( ':' ).map( Number );

	return new Date( year, month - 1, day, hour, minute ).toLocaleString( undefined, { dateStyle, timeStyle: 'short' } );
}

export default function SchedulePanel( { course, draft, setDraft, apply } ) {
	const start = draft || course.schedule || '';
	const [ date, setDate ] = useState( start.split( 'T' )[ 0 ] || '' );
	const [ time, setTime ] = useState( start.split( 'T' )[ 1 ] || '' );
	const [ editing, setEditing ] = useState( false );
	const saved = useRef( course.schedule );

	const isScheduled = course.status === 'future';
	const chosen = date && time ? `${ date }T${ time }` : '';
	const isPast = !! chosen && chosen <= course.site_now;

	// The server answered with a new schedule: show that, not what was typed.
	useEffect( () => {
		if ( saved.current === course.schedule ) {
			return;
		}
		saved.current = course.schedule;
		setEditing( false );
		setDate( ( course.schedule || '' ).split( 'T' )[ 0 ] || '' );
		setTime( ( course.schedule || '' ).split( 'T' )[ 1 ] || '' );
	}, [ course.schedule ] );

	const choose = ( nextDate, nextTime ) => {
		const value = nextDate && nextTime ? `${ nextDate }T${ nextTime }` : '';
		setDate( nextDate );
		setTime( nextTime );
		setDraft( value && value > course.site_now && value !== course.schedule ? value : '' );
	};

	const reset = () => {
		setEditing( false );
		choose( ( course.schedule || '' ).split( 'T' )[ 0 ] || '', ( course.schedule || '' ).split( 'T' )[ 1 ] || '' );
	};

	return (
		<div className="shapelms-schedule">
			<span className="shapelms-list-editor__label">{ __( 'Schedule publishing', 'shapelms' ) }</span>

			{ isScheduled && ! editing && ! draft ? (
				<div className="shapelms-schedule__summary">
					<strong>{ __( 'This course is scheduled', 'shapelms' ) }</strong>
					<p>{ sprintf( /* translators: 1: date and time, 2: time zone */ __( 'It goes live on %1$s (%2$s).', 'shapelms' ), formatSchedule( course.schedule ), course.timezone ) }</p>
					<div className="shapelms-schedule__actions">
						<Button variant="secondary" onClick={ () => setEditing( true ) }>{ __( 'Change date', 'shapelms' ) }</Button>
						<Button variant="tertiary" isDestructive onClick={ () => apply( '' ) }>{ __( 'Cancel schedule', 'shapelms' ) }</Button>
					</div>
				</div>
			) : (
				<>
					<p className="shapelms-muted">{ __( 'Pick when this course should go live. Until then visitors cannot see it; it publishes by itself at that time.', 'shapelms' ) }</p>
					<div className="shapelms-builder__row">
						<TextControl type="date" label={ __( 'Date', 'shapelms' ) } value={ date } min={ course.site_now.split( 'T' )[ 0 ] } onChange={ ( next ) => choose( next, next ? time || DEFAULT_TIME : time ) } __nextHasNoMarginBottom />
						<TextControl type="time" label={ __( 'Time', 'shapelms' ) } value={ time } onChange={ ( next ) => choose( date, next ) } __nextHasNoMarginBottom />
					</div>
					<p className="shapelms-muted">{ sprintf( /* translators: 1: time zone, 2: current date and time */ __( 'Times use the site time zone (%1$s). It is now %2$s there.', 'shapelms' ), course.timezone, formatSchedule( course.site_now, 'medium' ) ) }</p>

					{ isPast && <Notice status="error" isDismissible={ false }>{ __( 'That time has already passed. Pick a later date or time.', 'shapelms' ) }</Notice> }

					{ !! draft && (
						<Notice status={ course.status === 'publish' ? 'warning' : 'info' } isDismissible={ false }>
							{ course.status === 'publish'
								? sprintf( /* translators: %s: date and time */ __( 'This course is live now. Scheduling takes it offline until %s.', 'shapelms' ), formatSchedule( draft ) )
								: sprintf( /* translators: %s: date and time */ __( 'It will go live on %s. Press the button to confirm.', 'shapelms' ), formatSchedule( draft ) ) }
						</Notice>
					) }

					<div className="shapelms-schedule__actions">
						<Button variant="primary" disabled={ ! draft } onClick={ () => apply( draft ) }>{ isScheduled ? __( 'Update schedule', 'shapelms' ) : __( 'Schedule course', 'shapelms' ) }</Button>
						{ isScheduled ? (
							<Button variant="tertiary" onClick={ reset }>{ __( 'Keep current date', 'shapelms' ) }</Button>
						) : !! date && (
							<Button variant="tertiary" onClick={ reset }>{ __( 'Clear', 'shapelms' ) }</Button>
						) }
					</div>
				</>
			) }
		</div>
	);
}
