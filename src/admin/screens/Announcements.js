/**
 * Announcements: course announcements with filters, sorting, bulk delete and an
 * add/edit form. New ones can email the enrolled students.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Modal, SelectControl, TextControl, TextareaControl, CheckboxControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { get, post, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce, takePreloadedData } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, BulkActions, DataTable, Pagination, Avatar, ErrorNotice, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';

// Course list; must match the preload in Modules/Announcements/Admin.php.
const COURSE_QUERY = { status: 'any', per_page: 100 };

function EditModal( { item, courses, defaultCourse, onClose, onSaved } ) {
	const isNew = ! item.id;
	const [ courseId, setCourseId ] = useState( String( item.course_id || defaultCourse || courses[ 0 ]?.id || '' ) );
	const [ title, setTitle ] = useState( item.title || '' );
	const [ content, setContent ] = useState( item.content || '' );
	const [ notify, setNotify ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const save = () => {
		setBusy( true );
		setError( '' );
		const data = { course_id: parseInt( courseId, 10 ), title: title.trim(), content: content.trim() };
		const request = isNew ? post( 'announcements', { ...data, notify } ) : post( `announcements/admin/${ item.id }`, data );
		request
			.then( () => {
				success( isNew ? __( 'Announcement published.', 'shapelms' ) : __( 'Announcement updated.', 'shapelms' ) );
				onSaved();
			} )
			.catch( ( e ) => {
				setError( errorMessage( e ) );
				setBusy( false );
			} );
	};

	const ready = courseId && title.trim() && content.trim();

	return (
		<Modal title={ isNew ? __( 'New announcement', 'shapelms' ) : __( 'Edit announcement', 'shapelms' ) } onRequestClose={ onClose } size="medium" className="shapelms-announce-modal">
			<ErrorNotice message={ error } />
			<div className="shapelms-form-stack">
				<SelectControl
					label={ __( 'Course', 'shapelms' ) }
					value={ courseId }
					onChange={ setCourseId }
					options={ [ ...( courseId ? [] : [ { value: '', label: __( 'Select a course', 'shapelms' ) } ] ), ...courses.map( ( c ) => ( { value: String( c.id ), label: c.title } ) ) ] }
					__nextHasNoMarginBottom
				/>
				<TextControl label={ __( 'Title', 'shapelms' ) } value={ title } onChange={ setTitle } maxLength={ 200 } placeholder={ __( 'e.g. Live session moved to Friday', 'shapelms' ) } __nextHasNoMarginBottom />
				<TextareaControl label={ __( 'Message', 'shapelms' ) } value={ content } onChange={ setContent } rows={ 7 } help={ __( 'Enrolled students see this on the course page.', 'shapelms' ) } __nextHasNoMarginBottom />
				{ isNew && <CheckboxControl label={ __( 'Email enrolled students', 'shapelms' ) } checked={ notify } onChange={ setNotify } __nextHasNoMarginBottom /> }
			</div>
			<div className="shapelms-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'shapelms' ) }</Button>
				<Button variant="primary" isBusy={ busy } disabled={ busy || ! ready } onClick={ save }>{ isNew ? __( 'Publish', 'shapelms' ) : __( 'Save changes', 'shapelms' ) }</Button>
			</div>
		</Modal>
	);
}

export default function Announcements() {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search );
	// Rendered into the page by the server (Admin::preload); fetched only when missing.
	const [ courses, setCourses ] = useState( () => takePreloadedData( 'courses', COURSE_QUERY )?.items || null );
	const [ editing, setEditing ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const listState = usePagedList( 'announcements/admin', { course_id: 0, search: debounced } );

	if ( listState.query.search !== debounced ) {
		listState.setQuery( { search: debounced } );
	}

	useEffect( () => {
		if ( courses ) {
			return;
		}
		get( 'courses', COURSE_QUERY ).then( ( res ) => setCourses( res.items || [] ) );
		// Only once, on mount.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Only rows on the current page count, so paging or filtering drops stale picks.
	const selectedIds = selected.filter( ( id ) => listState.items.some( ( row ) => row.id === id ) );

	const remove = ( ids ) => {
		if ( ! ids.length || ! window.confirm( sprintf( /* translators: %d: number of announcements */ _n(
			'Delete %d announcement permanently? Students will no longer see it. This cannot be undone.',
			'Delete %d announcements permanently? Students will no longer see them. This cannot be undone.',
			ids.length,
			'shapelms'
		), ids.length ) ) ) {
			return;
		}
		setBusy( true );
		post( 'announcements/admin/bulk', { action: 'delete', ids } )
			.then( ( res ) => {
				if ( res.done ) {
					/* translators: %d: number of announcements */
					success( sprintf( _n( '%d announcement deleted.', '%d announcements deleted.', res.done, 'shapelms' ), res.done ) );
				}
				if ( res.skipped ) {
					/* translators: %d: number of announcements */
					notifyError( sprintf( _n( '%d announcement was skipped: it no longer exists or you cannot edit its course.', '%d announcements were skipped: they no longer exist or you cannot edit their courses.', res.skipped, 'shapelms' ), res.skipped ) );
				}
				setSelected( [] );
				listState.reload();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const columns = [
		{
			key: 'title',
			label: __( 'Announcement', 'shapelms' ),
			sortable: true,
			render: ( row ) => (
				<div className="shapelms-announce-cell">
					<button type="button" className="shapelms-link-button" onClick={ () => setEditing( row ) }><strong>{ row.title }</strong></button>
					<div className="shapelms-muted">{ row.excerpt }</div>
				</div>
			),
		},
		{ key: 'course', label: __( 'Course', 'shapelms' ), render: ( row ) => ( row.course_url ? <a href={ row.course_url } target="_blank" rel="noreferrer">{ row.course_title }</a> : row.course_title ) },
		{ key: 'author', label: __( 'Author', 'shapelms' ), render: ( row ) => <div className="shapelms-cell-title"><Avatar src={ row.avatar } size={ 24 } /><span>{ row.author }</span></div> },
		{ key: 'date', label: __( 'Date', 'shapelms' ), sortable: true, render: ( row ) => formatDate( row.date ) },
		{
			key: 'actions',
			label: '',
			className: 'is-actions',
			render: ( row ) => (
				<>
					<Button size="compact" variant="secondary" onClick={ () => setEditing( row ) }>{ __( 'Edit', 'shapelms' ) }</Button>
					<Button size="compact" variant="tertiary" isDestructive disabled={ busy } onClick={ () => remove( [ row.id ] ) }>{ __( 'Delete', 'shapelms' ) }</Button>
				</>
			),
		},
	];

	const filtered = listState.query.course_id || listState.query.search;

	return (
		<>
			<PageHeader
				title={ __( 'Announcements', 'shapelms' ) }
				actions={ <Button variant="primary" onClick={ () => setEditing( {} ) } disabled={ ! courses?.length }>{ __( 'Add new', 'shapelms' ) }</Button> }
			/>
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar
					actions={ <BulkActions options={ [ { value: 'delete', label: __( 'Delete permanently', 'shapelms' ) } ] } onApply={ () => remove( selectedIds ) } count={ selectedIds.length } busy={ busy } /> }
					search={ search }
					onSearch={ setSearch }
					filters={ [
						{
							key: 'course',
							value: String( listState.query.course_id ),
							onChange: ( v ) => {
								setSelected( [] );
								listState.setQuery( { course_id: parseInt( v, 10 ) } );
							},
							options: [ { value: '0', label: __( 'All courses', 'shapelms' ) }, ...( courses || [] ).map( ( c ) => ( { value: String( c.id ), label: c.title } ) ) ],
						},
					] }
				/>
				<DataTable
					columns={ columns }
					rows={ listState.items }
					loading={ listState.loading }
					emptyText={ filtered ? __( 'No announcements match these filters.', 'shapelms' ) : __( 'No announcements yet. Use "Add new" to post one to a course.', 'shapelms' ) }
					selection={ { selected: selectedIds, onChange: setSelected } }
					sort={ {
						orderby: listState.query.orderby || 'date',
						order: listState.query.order || 'DESC',
						onChange: ( orderby, order ) => listState.setQuery( { orderby, order } ),
					} }
				/>
				<Pagination
					page={ listState.query.page }
					pages={ listState.pages }
					total={ listState.total }
					onChange={ listState.setPage }
					perPage={ listState.query.per_page }
					onPerPageChange={ ( perPage ) => listState.setQuery( { per_page: perPage } ) }
				/>
			</Card>
			{ editing && (
				<EditModal
					item={ editing }
					courses={ courses || [] }
					defaultCourse={ listState.query.course_id }
					onClose={ () => setEditing( null ) }
					onSaved={ () => {
						setEditing( null );
						listState.reload();
					} }
				/>
			) }
		</>
	);
}
