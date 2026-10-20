/**
 * Shared UI components for the admin app and builder.
 */
import { Button, Spinner, Notice, SearchControl, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
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

export function Pagination( { page, pages, total, onChange } ) {
	if ( pages <= 1 ) {
		return total ? <p className="shapelms-pagination-info">{ sprintf( __( '%d items', 'shapelms' ), total ) }</p> : null;
	}
	return (
		<div className="shapelms-pagination-bar">
			<span className="shapelms-pagination-info">{ sprintf( __( 'Page %1$d of %2$d · %3$d items', 'shapelms' ), page, pages, total ) }</span>
			<div className="shapelms-pagination-buttons">
				<Button variant="secondary" size="compact" disabled={ page <= 1 } onClick={ () => onChange( page - 1 ) }>
					{ __( 'Previous', 'shapelms' ) }
				</Button>
				<Button variant="secondary" size="compact" disabled={ page >= pages } onClick={ () => onChange( page + 1 ) }>
					{ __( 'Next', 'shapelms' ) }
				</Button>
			</div>
		</div>
	);
}

export function Toolbar( { search, onSearch, filters = [], children } ) {
	return (
		<div className="shapelms-toolbar">
			{ onSearch && (
				<SearchControl className="shapelms-toolbar__search" value={ search } onChange={ onSearch } placeholder={ __( 'Search…', 'shapelms' ) } __nextHasNoMarginBottom />
			) }
			{ filters.map( ( filter ) => (
				<SelectControl key={ filter.key } className="shapelms-toolbar__filter" value={ filter.value } options={ filter.options } onChange={ filter.onChange } __nextHasNoMarginBottom />
			) ) }
			{ children }
		</div>
	);
}

export function DataTable( { columns, rows, keyField = 'id', loading, emptyText } ) {
	if ( loading ) {
		return <Loading />;
	}
	if ( ! rows.length ) {
		return <Empty title={ emptyText || __( 'Nothing here yet.', 'shapelms' ) } />;
	}
	return (
		<div className="shapelms-table-wrap">
			<table className="shapelms-admin-table">
				<thead>
					<tr>
						{ columns.map( ( col ) => (
							<th key={ col.key } className={ col.className || '' }>{ col.label }</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => (
						<tr key={ row[ keyField ] }>
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
