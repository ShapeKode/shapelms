/**
 * Shared UI components for the admin app and builder.
 */
import { Button, Spinner, Notice, SearchControl, SelectControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Icon, arrowUp, arrowDown, chevronUpDown } from '@wordpress/icons';
import { config } from './config';

export function PageHeader( { title, description, actions } ) {
	return (
		<div className="shapelms-page-header">
			<div>
				<h1 className="shapelms-page-header__title">{ title }</h1>
				{ description && <p className="shapelms-page-header__description">{ description }</p> }
			</div>
			{ actions && <div className="shapelms-page-header__actions">{ actions }</div> }
		</div>
	);
}

export function Card( { title, children, className = '', actions, padding = true } ) {
	return (
		<div className={ `shapelms-card-box ${ className }` }>
			{ ( title || actions ) && (
				<div className="shapelms-card-box__head">
					{ title && <h2 className="shapelms-card-box__title">{ title }</h2> }
					{ actions && <div className="shapelms-card-box__actions">{ actions }</div> }
				</div>
			) }
			<div className={ padding ? 'shapelms-card-box__body' : '' }>{ children }</div>
		</div>
	);
}

export function Loading( { inline } ) {
	return (
		<div className={ inline ? 'shapelms-loading shapelms-loading--inline' : 'shapelms-loading' }>
			<Spinner />
		</div>
	);
}

export function ErrorNotice( { message, onDismiss } ) {
	if ( ! message ) {
		return null;
	}
	return (
		<Notice status="error" isDismissible={ !! onDismiss } onRemove={ onDismiss }>
			{ message }
		</Notice>
	);
}

export function StatCard( { label, value, hint, tone } ) {
	return (
		<div className={ `shapelms-stat-card ${ tone ? 'is-' + tone : '' }` }>
			<span className="shapelms-stat-card__label">{ label }</span>
			<strong className="shapelms-stat-card__value">{ value }</strong>
			{ hint && <span className="shapelms-stat-card__hint">{ hint }</span> }
		</div>
	);
}

export function Badge( { children, tone = 'neutral' } ) {
	return <span className={ `shapelms-badge is-${ tone }` }>{ children }</span>;
}

export function Empty( { title, description, action } ) {
	return (
		<div className="shapelms-empty-state">
			<h3>{ title }</h3>
			{ description && <p>{ description }</p> }
			{ action }
		</div>
	);
}

/**
 * Page numbers to show: first, last, and a window around the current page, with gaps as null.
 */
function pageWindow( page, pages ) {
	const wanted = new Set( [ 1, pages, page - 1, page, page + 1 ] );
	// Near either end, show a few more so the bar keeps a steady width.
	if ( page <= 3 ) {
		[ 2, 3, 4 ].forEach( ( p ) => wanted.add( p ) );
	}
	if ( page >= pages - 2 ) {
		[ pages - 1, pages - 2, pages - 3 ].forEach( ( p ) => wanted.add( p ) );
	}
	const list = [ ...wanted ].filter( ( p ) => p >= 1 && p <= pages ).sort( ( a, b ) => a - b );
	const out = [];
	list.forEach( ( p, i ) => {
		if ( i && p - list[ i - 1 ] > 1 ) {
			out.push( null );
		}
		out.push( p );
	} );
	return out;
}

/**
 * List footer: "Showing x–y of n", optional rows-per-page picker and page buttons.
 * Pass `perPage` + `onPerPageChange` to show the picker.
 */
export function Pagination( { page, pages, total, onChange, perPage, onPerPageChange, perPageOptions = [ 10, 20, 50, 100 ] } ) {
	if ( ! total ) {
		return null;
	}
	const size = perPage || Math.ceil( total / Math.max( 1, pages ) );
	const from = ( page - 1 ) * size + 1;
	const to = Math.min( total, page * size );
	const go = ( p ) => p !== page && p >= 1 && p <= pages && onChange( p );
	// Always offer the size from Settings, even when it is not a preset.
	const sizes = [ ...new Set( [ ...perPageOptions, size ] ) ].sort( ( a, b ) => a - b );
	const paged = pages > 1;
	// The picker only matters when some size would split the list into pages.
	const canResize = onPerPageChange && total > sizes[ 0 ];

	// Everything fits on one page and no size would change that: nothing to show.
	if ( ! paged && ! canResize ) {
		return null;
	}

	return (
		<nav className="shapelms-pagination-bar" aria-label={ __( 'Pagination', 'shapelms' ) }>
			<div className="shapelms-pagination-meta">
				<span className="shapelms-pagination-info">
					{ /* translators: 1: first item number, 2: last item number, 3: total items */ sprintf( __( 'Showing %1$d–%2$d of %3$d', 'shapelms' ), from, to, total ) }
				</span>
				{ canResize && (
					<label className="shapelms-per-page">
						{ __( 'Rows per page', 'shapelms' ) }
						<select value={ size } onChange={ ( e ) => onPerPageChange( parseInt( e.target.value, 10 ) ) }>
							{ sizes.map( ( n ) => <option key={ n } value={ n }>{ n }</option> ) }
						</select>
					</label>
				) }
			</div>
			{ paged && <div className="shapelms-pages">
				<button type="button" className="shapelms-page shapelms-page--nav" disabled={ page <= 1 } onClick={ () => go( 1 ) } aria-label={ __( 'First page', 'shapelms' ) }>«</button>
				<button type="button" className="shapelms-page shapelms-page--nav" disabled={ page <= 1 } onClick={ () => go( page - 1 ) } aria-label={ __( 'Previous page', 'shapelms' ) }>‹</button>
				{ pageWindow( page, Math.max( 1, pages ) ).map( ( p, i ) => ( p === null ? (
					<span key={ `gap-${ i }` } className="shapelms-page-gap" aria-hidden="true">…</span>
				) : (
					<button
						key={ p }
						type="button"
						className={ `shapelms-page${ p === page ? ' is-current' : '' }` }
						aria-current={ p === page ? 'page' : undefined }
						aria-label={ /* translators: %d: page number */ sprintf( __( 'Page %d', 'shapelms' ), p ) }
						onClick={ () => go( p ) }
					>
						{ p }
					</button>
				) ) ) }
				<button type="button" className="shapelms-page shapelms-page--nav" disabled={ page >= pages } onClick={ () => go( page + 1 ) } aria-label={ __( 'Next page', 'shapelms' ) }>›</button>
				<button type="button" className="shapelms-page shapelms-page--nav" disabled={ page >= pages } onClick={ () => go( pages ) } aria-label={ __( 'Last page', 'shapelms' ) }>»</button>
			</div> }
		</nav>
	);
}

/**
 * List toolbar: optional actions on the left, filters and search on the right.
 */
export function Toolbar( { search, onSearch, filters = [], actions, children } ) {
	return (
		<div className="shapelms-toolbar">
			{ actions && <div className="shapelms-toolbar__start">{ actions }</div> }
			<div className="shapelms-toolbar__end">
				{ filters.map( ( filter ) => (
					<SelectControl key={ filter.key } className="shapelms-toolbar__filter" value={ filter.value } options={ filter.options } onChange={ filter.onChange } __nextHasNoMarginBottom />
				) ) }
				{ children }
				{ onSearch && (
					<SearchControl className="shapelms-toolbar__search" value={ search } onChange={ onSearch } placeholder={ __( 'Search…', 'shapelms' ) } __nextHasNoMarginBottom />
				) }
			</div>
		</div>
	);
}

/**
 * Bulk action picker with an Apply button.
 */
export function BulkActions( { options, onApply, count, busy } ) {
	const [ action, setAction ] = useState( '' );
	return (
		<div className="shapelms-bulk">
			<SelectControl
				className="shapelms-bulk__select"
				aria-label={ __( 'Bulk action', 'shapelms' ) }
				value={ action }
				options={ [ { value: '', label: __( 'Bulk action', 'shapelms' ) }, ...options ] }
				onChange={ setAction }
				__nextHasNoMarginBottom
			/>
			<Button
				variant="secondary"
				isBusy={ busy }
				disabled={ busy || ! action || ! count }
				onClick={ () => onApply( action ) }
			>
				{ __( 'Apply', 'shapelms' ) }
			</Button>
			{ count > 0 && <span className="shapelms-muted">{ /* translators: %d: number of selected items */ sprintf( _n( '%d selected', '%d selected', count, 'shapelms' ), count ) }</span> }
		</div>
	);
}

function sortIcon( col, sort ) {
	if ( sort.orderby !== col.key ) {
		return chevronUpDown;
	}
	return sort.order === 'ASC' ? arrowUp : arrowDown;
}

function sortHint( col, sort ) {
	// The click flips the current direction, or starts ascending on a new column.
	const next = sort.orderby === col.key && sort.order === 'ASC' ? 'DESC' : 'ASC';
	return next === 'ASC'
		? /* translators: %s: column name */ sprintf( __( 'Sort by %s, ascending', 'shapelms' ), col.label )
		: /* translators: %s: column name */ sprintf( __( 'Sort by %s, descending', 'shapelms' ), col.label );
}

function sortHeader( col, sort ) {
	if ( ! col.sortable || ! sort || sort.orderby !== col.key ) {
		return undefined;
	}
	return sort.order === 'ASC' ? 'ascending' : 'descending';
}

/**
 * Pass `selection` ({ selected: ids[], onChange }) to add a checkbox column,
 * and `sort` ({ orderby, order, onChange( orderby, order ) }) to make `sortable` columns clickable.
 */
export function DataTable( { columns, rows, keyField = 'id', loading, emptyText, selection, sort } ) {
	// A spinner only when there is nothing to show; otherwise keep the rows and refresh in place.
	if ( loading && ! rows?.length ) {
		return <Loading />;
	}
	if ( ! rows.length ) {
		return <Empty title={ emptyText || __( 'Nothing here yet.', 'shapelms' ) } />;
	}
	const selected = selection ? selection.selected : [];
	const allSelected = rows.every( ( row ) => selected.includes( row[ keyField ] ) );
	const toggleAll = () => selection.onChange( allSelected ? [] : rows.map( ( row ) => row[ keyField ] ) );
	const toggle = ( id ) => selection.onChange( selected.includes( id ) ? selected.filter( ( s ) => s !== id ) : [ ...selected, id ] );
	return (
		<div className={ `shapelms-table-wrap${ loading ? ' is-refreshing' : '' }` } aria-busy={ loading ? 'true' : undefined }>
			{ loading && <div className="shapelms-progress" /> }
			<table className="shapelms-admin-table">
				<thead>
					<tr>
						{ selection && (
							<th className="is-check">
								<input type="checkbox" checked={ allSelected } onChange={ toggleAll } aria-label={ __( 'Select all', 'shapelms' ) } />
							</th>
						) }
						{ columns.map( ( col ) => (
							<th key={ col.key } className={ col.className || '' } aria-sort={ sortHeader( col, sort ) }>
								{ col.sortable && sort ? (
									<button type="button" className={ `shapelms-sort${ sort.orderby === col.key ? ' is-' + sort.order.toLowerCase() : '' }` } onClick={ () => sort.onChange( col.key, sort.orderby === col.key && sort.order === 'ASC' ? 'DESC' : 'ASC' ) } title={ sortHint( col, sort ) }>
										{ col.label }
										<Icon className="shapelms-sort__icon" size={ 20 } icon={ sortIcon( col, sort ) } />
									</button>
								) : col.label }
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => (
						<tr key={ row[ keyField ] } className={ selected.includes( row[ keyField ] ) ? 'is-selected' : '' }>
							{ selection && (
								<td className="is-check">
									<input type="checkbox" checked={ selected.includes( row[ keyField ] ) } onChange={ () => toggle( row[ keyField ] ) } aria-label={ __( 'Select row', 'shapelms' ) } />
								</td>
							) }
							{ columns.map( ( col ) => (
								<td key={ col.key } className={ col.className || '' }>{ col.render ? col.render( row ) : row[ col.key ] }</td>
							) ) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

export function Avatar( { src, name, size = 32 } ) {
	return <img className="shapelms-avatar" src={ src } alt="" width={ size } height={ size } title={ name } />;
}

export function formatDate( value ) {
	if ( ! value ) {
		return '—';
	}
	const date = new Date( value.includes( 'T' ) ? value : value.replace( ' ', 'T' ) + 'Z' );
	return isNaN( date.getTime() ) ? value : date.toLocaleDateString( undefined, { year: 'numeric', month: 'short', day: 'numeric' } );
}

export function money( amount ) {
	const symbol = config().currency || '';
	return `${ symbol }${ Number( amount || 0 ).toFixed( 2 ) }`;
}
