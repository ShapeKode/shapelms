/**
 * Category, date and status filters for the courses list table.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { get } from '../../shared/api';
import { takePreloadedData } from '../../shared/hooks';

/** Y-m-d in the browser's local time. */
export const ymd = ( date ) => {
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad( date.getDate() ) }`;
};

const addDays = ( date, days ) => new Date( date.getFullYear(), date.getMonth(), date.getDate() + days );
const monthRange = ( year, month ) => [ ymd( new Date( year, month, 1 ) ), ymd( new Date( year, month + 1, 0 ) ) ];

/**
 * Turns a date choice into an inclusive [ from, to ] range, or null for "all dates".
 *
 * @param {string} preset Preset key, or "m:YYYY-MM" for a month.
 * @return {string[]|null} Range.
 */
export function presetRange( preset ) {
	const today = new Date();
	const y = today.getFullYear();
	const m = today.getMonth();
	switch ( preset ) {
		case 'today':
			return [ ymd( today ), ymd( today ) ];
		case 'yesterday':
			return [ ymd( addDays( today, -1 ) ), ymd( addDays( today, -1 ) ) ];
		case 'last7':
			return [ ymd( addDays( today, -6 ) ), ymd( today ) ];
		case 'last30':
			return [ ymd( addDays( today, -29 ) ), ymd( today ) ];
		case 'this_month':
			return monthRange( y, m );
		case 'last_month':
			return monthRange( y, m - 1 );
		case 'this_year':
			return [ `${ y }-01-01`, `${ y }-12-31` ];
		default:
			if ( preset.startsWith( 'm:' ) ) {
				const [ year, month ] = preset.slice( 2 ).split( '-' ).map( Number );
				return monthRange( year, month - 1 );
			}
			return null;
	}
}

const presets = [
	[ 'today', __( 'Today', 'shapelms' ) ],
	[ 'yesterday', __( 'Yesterday', 'shapelms' ) ],
	[ 'last7', __( 'Last 7 days', 'shapelms' ) ],
	[ 'last30', __( 'Last 30 days', 'shapelms' ) ],
	[ 'this_month', __( 'This month', 'shapelms' ) ],
	[ 'last_month', __( 'Last month', 'shapelms' ) ],
	[ 'this_year', __( 'This year', 'shapelms' ) ],
];

/** Categories in tree order, children indented under their parent. */
function categoryOptions( categories ) {
	const out = [];
	const walk = ( parent, depth ) => {
		categories
			.filter( ( term ) => term.parent === parent )
			.sort( ( a, b ) => a.name.localeCompare( b.name ) )
			.forEach( ( term ) => {
				out.push( { value: String( term.id ), label: `${ '   '.repeat( depth ) }${ term.name } (${ term.count })` } );
				walk( term.id, depth + 1 );
			} );
	};
	walk( 0, 0 );
	// Terms whose parent is missing would otherwise vanish.
	categories.filter( ( term ) => ! out.some( ( o ) => o.value === String( term.id ) ) ).forEach( ( term ) => out.push( { value: String( term.id ), label: `${ term.name } (${ term.count })` } ) );
	return out;
}

export default function CourseFilters( { query, setQuery, datePreset, setDatePreset, statusOptions, onClear, active } ) {
	// Category and month choices come with the page; fetch only if they did not.
	const [ options, setOptions ] = useState( () => takePreloadedData( 'courses/filters' ) || { categories: [], months: [], pending: true } );

	useEffect( () => {
		if ( options.pending ) {
			get( 'courses/filters' ).then( setOptions ).catch( () => {} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const chooseDate = ( preset ) => {
		setDatePreset( preset );
		if ( preset === 'custom' ) {
			return; // Waits for the From/To inputs.
		}
		const range = presetRange( preset );
		setQuery( { date_from: range ? range[ 0 ] : '', date_to: range ? range[ 1 ] : '' } );
	};

	return (
		<div className="shapelms-filters">
			<SelectControl
				className="shapelms-toolbar__filter"
				aria-label={ __( 'Filter by category', 'shapelms' ) }
				value={ String( query.category || '' ) }
				onChange={ ( category ) => setQuery( { category } ) }
				options={ [ { value: '', label: __( 'All categories', 'shapelms' ) }, ...categoryOptions( options.categories ) ] }
				__nextHasNoMarginBottom
			/>

			<SelectControl
				className="shapelms-toolbar__filter"
				aria-label={ __( 'Filter by date', 'shapelms' ) }
				value={ datePreset }
				onChange={ chooseDate }
				__nextHasNoMarginBottom
			>
				<option value="">{ __( 'All dates', 'shapelms' ) }</option>
				<optgroup label={ __( 'Quick ranges', 'shapelms' ) }>
					{ presets.map( ( [ value, label ] ) => <option key={ value } value={ value }>{ label }</option> ) }
				</optgroup>
				{ options.months.length > 0 && (
					<optgroup label={ __( 'By month', 'shapelms' ) }>
						{ options.months.map( ( month ) => <option key={ month.value } value={ `m:${ month.value }` }>{ month.label }</option> ) }
					</optgroup>
				) }
				<option value="custom">{ __( 'Custom range…', 'shapelms' ) }</option>
			</SelectControl>

			{ datePreset === 'custom' && (
				<div className="shapelms-date-range">
					<input
						type="date"
						aria-label={ __( 'From date', 'shapelms' ) }
						value={ query.date_from || '' }
						max={ query.date_to || undefined }
						onChange={ ( e ) => setQuery( { date_from: e.target.value } ) }
					/>
					<span aria-hidden="true">–</span>
					<input
						type="date"
						aria-label={ __( 'To date', 'shapelms' ) }
						value={ query.date_to || '' }
						min={ query.date_from || undefined }
						onChange={ ( e ) => setQuery( { date_to: e.target.value } ) }
					/>
				</div>
			) }

			<SelectControl
				className="shapelms-toolbar__filter"
				aria-label={ __( 'Filter by status', 'shapelms' ) }
				value={ query.status }
				onChange={ ( status ) => setQuery( { status } ) }
				options={ statusOptions }
				__nextHasNoMarginBottom
			/>

			{ active > 0 && (
				<Button variant="tertiary" size="compact" onClick={ onClear } className="shapelms-filters__clear">
					{ /* translators: %d: number of active filters */ sprintf( __( 'Clear filters (%d)', 'shapelms' ), active ) }
				</Button>
			) }
		</div>
	);
}
