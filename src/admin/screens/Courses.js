/**
 * Courses list.
 */
import { useState } from '@wordpress/element';
import { Button, Modal, TextControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Icon, listView, page, help, pencil, file } from '@wordpress/icons';
import { post, patch, del, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, BulkActions, DataTable, Pagination, Badge, ErrorNotice, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';
import CourseFilters, { ymd } from '../components/CourseFilters';

const itemIcons = { lesson: page, quiz: help, assignment: pencil };

const statusTone ={ publish: 'success', draft: 'neutral', pending: 'warning', private: 'neutral', future: 'info', trash: 'danger' };
const statusLabel = {
	publish: __( 'Published', 'shapelms' ),
	future: __( 'Scheduled', 'shapelms' ),
	draft: __( 'Draft', 'shapelms' ),
	pending: __( 'Pending', 'shapelms' ),
	private: __( 'Private', 'shapelms' ),
	trash: __( 'Trash', 'shapelms' ),
};

/**
 * Applies the list filters to rows already loaded, so a filter change shows
 * results at once; the server's answer replaces them a moment later.
 */
function previewCourses( items, query ) {
	const term = String( query.search || '' ).trim().toLowerCase();
	const category = parseInt( query.category, 10 ) || 0;
	const orderby = query.orderby || 'date';
	const dir = query.order === 'ASC' ? 1 : -1;
	return items
		.filter( ( row ) => ( query.status === 'any' ? row.status !== 'trash' : row.status === query.status ) )
		.filter( ( row ) => ! term || row.title.toLowerCase().includes( term ) )
		.filter( ( row ) => ! category || ( row.categories || [] ).some( ( t ) => t.id === category ) )
		.filter( ( row ) => {
			const day = ymd( new Date( row.date ) );
			return ( ! query.date_from || day >= query.date_from ) && ( ! query.date_to || day <= query.date_to );
		} )
		.sort( ( a, b ) => dir * ( orderby === 'title' ? a.title.localeCompare( b.title ) : new Date( a.date ) - new Date( b.date ) ) );
}

export default function Courses( { config } ) {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search, 250 );
	const [ creating, setCreating ] = useState( false );
	const [ title, setTitle ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ changing, setChanging ] = useState( 0 );
	const [ selected, setSelected ] = useState( [] );
	const [ bulkBusy, setBulkBusy ] = useState( false );
	const listState = usePagedList( 'courses', { status: 'any', search: debounced, mine: ! config.canManage }, { preview: previewCourses } );

	const [ datePreset, setDatePreset ] = useState( '' );

	if ( listState.query.search !== debounced ) {
		listState.setQuery( { search: debounced } );
	}

	const activeFilters = [ listState.query.category, listState.query.date_from || listState.query.date_to, listState.query.status !== 'any', search ].filter( Boolean ).length;
	const clearFilters = () => {
		setSearch( '' );
		setDatePreset( '' );
		listState.setQuery( { category: '', date_from: '', date_to: '', status: 'any', search: '' } );
	};

	const create = () => {
		if ( ! title.trim() ) {
			return;
		}
		setBusy( true );
		post( 'courses', { title } )
			.then( ( course ) => {
				window.location.href = course.edit_url;
			} )
			.catch( ( e ) => {
				notifyError( errorMessage( e ) );
				setBusy( false );
			} );
	};

	const trash = ( course ) => {
		if ( ! window.confirm( /* translators: %s: course title */ sprintf( __( 'Move "%s" to the trash?', 'shapelms' ), course.title ) ) ) {
			return;
		}
		del( `courses/${ course.id }` )
			.then( () => {
				success( __( 'Course moved to trash.', 'shapelms' ) );
				listState.reload();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const changeStatus = ( course, status ) => {
		if ( status === 'trash' ) {
			trash( course );
			return;
		}
		setChanging( course.id );
		patch( `courses/${ course.id }`, { status } )
			.then( ( saved ) => {
				if ( saved.status === status ) {
					success( /* translators: %s: course status */ sprintf( __( 'Status changed to %s.', 'shapelms' ), statusLabel[ status ] ) );
				} else if ( saved.status === 'pending' ) {
					success( __( 'Course submitted for review.', 'shapelms' ) );
				} else {
					notifyError( __( 'You cannot change the status of this course.', 'shapelms' ) );
				}
				// The row may no longer belong in a filtered list.
				if ( listState.query.status === 'any' && course.status !== 'trash' ) {
					listState.patchItem( course.id, { status: saved.status, visibility: saved.visibility, date: saved.date } );
				} else {
					listState.reload();
				}
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setChanging( 0 ) );
	};

	// Only ids still on the current page count, so paging or filtering drops stale picks.
	const selectedIds = selected.filter( ( id ) => listState.items.some( ( row ) => row.id === id ) );
	const inTrash = listState.query.status === 'trash';

	const bulkOptions = inTrash
		? [ { value: 'restore', label: __( 'Restore', 'shapelms' ) } ]
		: [
			...( config.canPublish ? [ { value: 'publish', label: __( 'Publish', 'shapelms' ) } ] : [] ),
			{ value: 'pending', label: __( 'Pending', 'shapelms' ) },
			{ value: 'draft', label: __( 'Draft', 'shapelms' ) },
			{ value: 'trash', label: __( 'Move to trash', 'shapelms' ) },
		];

	const applyBulk = ( action ) => {
		const ids = selectedIds;
		if ( ! ids.length ) {
			return;
		}
		if ( action === 'trash' && ! window.confirm( /* translators: %d: number of courses */ sprintf( _n( 'Move %d course to the trash?', 'Move %d courses to the trash?', ids.length, 'shapelms' ), ids.length ) ) ) {
			return;
		}
		const request = ( id ) => {
			if ( action === 'trash' ) {
				return del( `courses/${ id }` );
			}
			return patch( `courses/${ id }`, { status: action === 'restore' ? 'draft' : action } );
		};
		setBulkBusy( true );
		Promise.allSettled( ids.map( request ) )
			.then( ( results ) => {
				const failed = results.filter( ( r ) => r.status === 'rejected' );
				const done = ids.length - failed.length;
				if ( done ) {
					success( /* translators: %d: number of courses */ sprintf( _n( '%d course updated.', '%d courses updated.', done, 'shapelms' ), done ) );
				}
				if ( failed.length ) {
					notifyError( errorMessage( failed[ 0 ].reason ) );
				}
				setSelected( [] );
				listState.reload();
			} )
			.finally( () => setBulkBusy( false ) );
	};

	const statusOptions = ( course ) => Object.keys( statusLabel )
		// Scheduling needs a date, so it happens in the builder.
		.filter( ( status ) => status === course.status || ( status !== 'future' && ( config.canPublish || ! [ 'publish', 'private' ].includes( status ) ) ) )
		.map( ( status ) => <option key={ status } value={ status }>{ statusLabel[ status ] }</option> );

	const columns = [
		{
			key: 'title',
			label: __( 'Title', 'shapelms' ),
			sortable: true,
			render: ( row ) => (
				<div className="shapelms-cell-title">
					{ row.image ? <img src={ row.image } alt="" className="shapelms-thumb" /> : <span className="shapelms-thumb shapelms-thumb--empty" /> }
					<div>
						{ row.status === 'trash' ? <strong>{ row.title }</strong> : <a href={ row.edit_url }><strong>{ row.title }</strong></a> }
						<div className="shapelms-course-meta">
							<span><Icon icon={ listView } size={ 16 } />{ __( 'Topic', 'shapelms' ) }: <strong>{ row.topics }</strong></span>
							{ ( row.item_counts || [] ).map( ( item ) => (
								<span key={ item.type }><Icon icon={ itemIcons[ item.type ] || file } size={ 16 } />{ item.label }: <strong>{ item.count }</strong></span>
							) ) }
						</div>
					</div>
				</div>
			),
		},
		{ key: 'status', label: __( 'Status', 'shapelms' ), render: ( row ) => (
			<>
				<select
					className={ `shapelms-status-select is-${ statusTone[ row.status ] || 'neutral' }` }
					aria-label={ /* translators: %s: course title */ sprintf( __( 'Status of %s', 'shapelms' ), row.title ) }
					value={ row.status }
					disabled={ changing === row.id }
					onChange={ ( e ) => changeStatus( row, e.target.value ) }
				>
					{ statusOptions( row ) }
				</select>
				{ row.visibility === 'password' && <> <Badge tone="neutral">{ __( 'Password', 'shapelms' ) }</Badge></> }
				{ row.status === 'future' && <div className="shapelms-muted">{ formatDate( row.date ) }</div> }
			</>
		) },
		{ key: 'price', label: __( 'Price', 'shapelms' ), render: ( row ) => row.price_html },
		{
			key: 'author',
			label: __( 'Author', 'shapelms' ),
			render: ( row ) => ( row.instructors?.length ? (
				<div className="shapelms-cell-author">
					<img src={ row.instructors[ 0 ].avatar } alt="" className="shapelms-cell-author__avatar" />
					<span>{ row.instructors.map( ( person ) => person.name ).join( ', ' ) }</span>
				</div>
			) : '—' ),
		},
		{
			key: 'categories',
			label: __( 'Categories', 'shapelms' ),
			// Clicking a category filters the list by it, like core's list tables.
			render: ( row ) => ( row.categories?.length ? (
				<span className="shapelms-term-links">
					{ row.categories.map( ( term ) => (
						<button
							key={ term.id }
							type="button"
							className="shapelms-term-link"
							title={ /* translators: %s: category name */ sprintf( __( 'Show courses in %s', 'shapelms' ), term.name ) }
							onClick={ () => listState.setQuery( { category: String( term.id ) } ) }
						>
							{ term.name }
						</button>
					) ) }
				</span>
			) : <span className="shapelms-muted">{ __( 'Uncategorized', 'shapelms' ) }</span> ),
		},
		{ key: 'rating', label: __( 'Rating', 'shapelms' ), render: ( row ) => ( row.rating.count ? `${ row.rating.average.toFixed( 1 ) } ★ (${ row.rating.count })` : '—' ) },
		{ key: 'date', label: __( 'Date', 'shapelms' ), sortable: true, render: ( row ) => formatDate( row.date ) },
		{
			key: 'actions',
			label: '',
			className: 'is-actions',
			render: ( row ) => ( row.status === 'trash' ? null : (
				<>
					<Button size="compact" variant="secondary" href={ row.edit_url }>{ __( 'Edit', 'shapelms' ) }</Button>
					<Button size="compact" variant="tertiary" href={ row.url } target="_blank">{ __( 'View', 'shapelms' ) }</Button>
					<Button size="compact" variant="tertiary" isDestructive onClick={ () => trash( row ) }>{ __( 'Trash', 'shapelms' ) }</Button>
				</>
			) ),
		},
	];

	return (
		<>
			<PageHeader title={ __( 'Courses', 'shapelms' ) } actions={ <Button variant="primary" onClick={ () => setCreating( true ) }>{ __( 'New course', 'shapelms' ) }</Button> } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar
					actions={ <BulkActions key={ inTrash ? 'trash' : 'all' } options={ bulkOptions } onApply={ applyBulk } count={ selectedIds.length } busy={ bulkBusy } /> }
					search={ search }
					onSearch={ setSearch }
				>
					<CourseFilters
						query={ listState.query }
						setQuery={ listState.setQuery }
						datePreset={ datePreset }
						setDatePreset={ setDatePreset }
						active={ activeFilters }
						onClear={ clearFilters }
						statusOptions={ [
							{ value: 'any', label: __( 'All statuses', 'shapelms' ) },
							{ value: 'publish', label: __( 'Published', 'shapelms' ) },
							{ value: 'future', label: __( 'Scheduled', 'shapelms' ) },
							{ value: 'draft', label: __( 'Draft', 'shapelms' ) },
							{ value: 'pending', label: __( 'Pending review', 'shapelms' ) },
							{ value: 'private', label: __( 'Private', 'shapelms' ) },
							{ value: 'trash', label: __( 'Trash', 'shapelms' ) },
						] }
					/>
				</Toolbar>
				<DataTable
					columns={ columns }
					sort={ {
						// The API sorts newest first when nothing is set.
						orderby: listState.query.orderby || 'date',
						order: listState.query.order || 'DESC',
						onChange: ( orderby, order ) => listState.setQuery( { orderby, order } ),
					} }
					selection={ { selected: selectedIds, onChange: setSelected } } rows={ listState.items } loading={ listState.loading } emptyText={ activeFilters ? __( 'No courses match these filters.', 'shapelms' ) : __( 'No courses yet. Create your first course.', 'shapelms' ) } />
				<Pagination
					page={ listState.query.page }
					pages={ listState.pages }
					total={ listState.total }
					onChange={ listState.setPage }
					perPage={ listState.query.per_page }
					onPerPageChange={ ( perPage ) => listState.setQuery( { per_page: perPage } ) }
				/>
			</Card>

			{ creating && (
				<Modal title={ __( 'New course', 'shapelms' ) } onRequestClose={ () => setCreating( false ) } size="small">
					<TextControl label={ __( 'Course title', 'shapelms' ) } value={ title } onChange={ setTitle } onKeyDown={ ( e ) => e.key === 'Enter' && create() } autoFocus __nextHasNoMarginBottom />
					<div className="shapelms-modal-actions">
						<Button variant="tertiary" onClick={ () => setCreating( false ) }>{ __( 'Cancel', 'shapelms' ) }</Button>
						<Button variant="primary" isBusy={ busy } disabled={ busy || ! title.trim() } onClick={ create }>{ __( 'Create and open builder', 'shapelms' ) }</Button>
					</div>
				</Modal>
			) }
		</>
	);
}
