/**
 * Q&A: student questions across courses, with filters, counts, sorting, bulk delete
 * and a thread view to reply.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Modal, TextareaControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { get, post, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce, takePreloadedData } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, BulkActions, DataTable, Pagination, Badge, ErrorNotice, Avatar, Loading, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';

// Course filter list; must match the preload in Modules/QA/Admin.php.
const COURSE_QUERY = { status: 'any', per_page: 100 };

const STATUSES = [
	[ '', __( 'All', 'shapelms' ), 'all' ],
	[ 'unanswered', __( 'Unanswered', 'shapelms' ), 'unanswered' ],
	[ 'answered', __( 'Answered', 'shapelms' ), 'answered' ],
];

function dateTime( value ) {
	if ( ! value ) {
		return '—';
	}
	const date = new Date( value.replace( ' ', 'T' ) + 'Z' );
	return isNaN( date.getTime() ) ? value : date.toLocaleString( undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' } );
}

function excerpt( text, length = 110 ) {
	const clean = ( text || '' ).replace( /\s+/g, ' ' ).trim();
	return clean.length > length ? clean.slice( 0, length ).trimEnd() + '…' : clean;
}

function ThreadModal( { id, onClose, onChange } ) {
	const [ thread, setThread ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ reply, setReply ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		get( `qa/admin/${ id }` ).then( setThread ).catch( ( e ) => setError( errorMessage( e ) ) );
	}, [ id ] );

	const send = () => {
		setBusy( true );
		post( `qa/admin/${ id }/reply`, { content: reply.trim() } )
			.then( ( updated ) => {
				setThread( updated );
				setReply( '' );
				success( __( 'Reply posted.', 'shapelms' ) );
				onChange();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const removeReply = ( replyId ) => {
		if ( ! window.confirm( __( 'Delete this reply permanently?', 'shapelms' ) ) ) {
			return;
		}
		post( 'qa/admin/bulk', { action: 'delete', ids: [ replyId ] } )
			.then( () => {
				setThread( ( t ) => ( { ...t, replies: t.replies.filter( ( r ) => r.id !== replyId ) } ) );
				success( __( 'Reply deleted.', 'shapelms' ) );
				onChange();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const message = ( m, isQuestion ) => (
		<div className={ `shapelms-qa-msg${ isQuestion ? ' is-question' : '' }${ m.is_instructor ? ' is-instructor' : '' }` } key={ m.id }>
			<Avatar src={ m.avatar } size={ 36 } />
			<div className="shapelms-qa-msg__body">
				<div className="shapelms-qa-msg__head">
					<strong>{ m.name }</strong>
					{ m.is_instructor && <Badge tone="info">{ __( 'Instructor', 'shapelms' ) }</Badge> }
					<span className="shapelms-muted">{ dateTime( m.date ) }</span>
					{ ! isQuestion && <Button size="small" variant="tertiary" isDestructive className="shapelms-qa-msg__delete" onClick={ () => removeReply( m.id ) }>{ __( 'Delete', 'shapelms' ) }</Button> }
				</div>
				<p>{ m.content }</p>
			</div>
		</div>
	);

	return (
		<Modal title={ __( 'Question', 'shapelms' ) } onRequestClose={ onClose } size="medium" className="shapelms-qa-modal">
			<ErrorNotice message={ error } />
			{ ! thread && ! error && <Loading /> }
			{ thread && (
				<>
					<p className="shapelms-muted shapelms-qa-modal__where">
						{ thread.course_title }
						{ thread.item_title && <> · { thread.item_url ? <a href={ thread.item_url } target="_blank" rel="noopener noreferrer">{ thread.item_title }</a> : thread.item_title }</> }
					</p>
					{ message( thread, true ) }
					<h3 className="shapelms-qa-modal__replies">
						{ thread.replies.length
							? sprintf( /* translators: %d: number of replies */ _n( '%d reply', '%d replies', thread.replies.length, 'shapelms' ), thread.replies.length )
							: __( 'No replies yet', 'shapelms' ) }
					</h3>
					<div className="shapelms-qa-modal__list">{ thread.replies.map( ( r ) => message( r, false ) ) }</div>
					<TextareaControl label={ __( 'Your reply', 'shapelms' ) } value={ reply } onChange={ setReply } rows={ 4 } placeholder={ __( 'Write an answer the student will see under the lesson…', 'shapelms' ) } __nextHasNoMarginBottom />
					<div className="shapelms-modal-actions">
						<Button variant="tertiary" onClick={ onClose }>{ __( 'Close', 'shapelms' ) }</Button>
						<Button variant="primary" isBusy={ busy } disabled={ busy || ! reply.trim() } onClick={ send }>{ __( 'Post reply', 'shapelms' ) }</Button>
					</div>
				</>
			) }
		</Modal>
	);
}

export default function QA() {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search );
	// Rendered into the page by the server (Admin::preload); fetched only when missing.
	const [ courses, setCourses ] = useState( () => takePreloadedData( 'courses', COURSE_QUERY )?.items || null );
	const [ viewing, setViewing ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const listState = usePagedList( 'qa/admin', { course_id: 0, status: '', search: debounced } );

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
		if ( ! ids.length || ! window.confirm( sprintf( /* translators: %d: number of questions */ _n(
			'Delete %d question permanently? Its replies are deleted too. This cannot be undone.',
			'Delete %d questions permanently? Their replies are deleted too. This cannot be undone.',
			ids.length,
			'shapelms'
		), ids.length ) ) ) {
			return;
		}
		setBusy( true );
		post( 'qa/admin/bulk', { action: 'delete', ids } )
			.then( ( res ) => {
				if ( res.done ) {
					/* translators: %d: number of questions */
					success( sprintf( _n( '%d question deleted.', '%d questions deleted.', res.done, 'shapelms' ), res.done ) );
				}
				if ( res.skipped ) {
					/* translators: %d: number of questions */
					notifyError( sprintf( _n( '%d question was skipped: it no longer exists or you cannot edit its course.', '%d questions were skipped: they no longer exist or you cannot edit their courses.', res.skipped, 'shapelms' ), res.skipped ) );
				}
				setSelected( [] );
				listState.reload();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const columns = [
		{
			key: 'question',
			label: __( 'Question', 'shapelms' ),
			render: ( row ) => (
				<div className="shapelms-cell-title shapelms-qa-cell">
					<Avatar src={ row.avatar } />
					<div>
						<button type="button" className="shapelms-link-button" onClick={ () => setViewing( row.id ) }><strong>{ excerpt( row.content ) }</strong></button>
						<div className="shapelms-muted">{ row.name } · { row.email }</div>
					</div>
				</div>
			),
		},
		{
			key: 'course',
			label: __( 'Course', 'shapelms' ),
			render: ( row ) => <>{ row.course_title }{ row.item_title && <div className="shapelms-muted">{ row.item_title }</div> }</>,
		},
		{ key: 'replies', label: __( 'Replies', 'shapelms' ), sortable: true, render: ( row ) => row.replies },
		{ key: 'status', label: __( 'Status', 'shapelms' ), render: ( row ) => <Badge tone={ row.answered ? 'success' : 'warning' }>{ row.answered ? __( 'Answered', 'shapelms' ) : __( 'Unanswered', 'shapelms' ) }</Badge> },
		{ key: 'date', label: __( 'Date', 'shapelms' ), sortable: true, render: ( row ) => formatDate( row.date ) },
		{
			key: 'actions',
			label: '',
			className: 'is-actions',
			render: ( row ) => (
				<>
					<Button size="compact" variant="secondary" onClick={ () => setViewing( row.id ) }>{ row.answered ? __( 'View', 'shapelms' ) : __( 'Reply', 'shapelms' ) }</Button>
					<Button size="compact" variant="tertiary" isDestructive disabled={ busy } onClick={ () => remove( [ row.id ] ) }>{ __( 'Delete', 'shapelms' ) }</Button>
				</>
			),
		},
	];

	const filtered = listState.query.status || listState.query.course_id || listState.query.search;

	return (
		<>
			<PageHeader title={ __( 'Q&A', 'shapelms' ) } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar
					actions={ <BulkActions options={ [ { value: 'delete', label: __( 'Delete permanently', 'shapelms' ) } ] } onApply={ () => remove( selectedIds ) } count={ selectedIds.length } busy={ busy } /> }
					search={ search }
					onSearch={ setSearch }
					filters={ [
						{ key: 'course', value: String( listState.query.course_id ), onChange: ( v ) => listState.setQuery( { course_id: parseInt( v, 10 ) } ), options: [ { value: '0', label: __( 'All courses', 'shapelms' ) }, ...( courses || [] ).map( ( c ) => ( { value: String( c.id ), label: c.title } ) ) ] },
						{
							key: 'status',
							value: listState.query.status,
							onChange: ( status ) => {
								setSelected( [] );
								listState.setQuery( { status } );
							},
							options: STATUSES.map( ( [ value, label, countKey ] ) => ( {
								value,
								label: sprintf( /* translators: 1: status, 2: number of questions */ __( '%1$s (%2$d)', 'shapelms' ), label, listState.counts?.[ countKey ] ?? 0 ),
							} ) ),
						},
					] }
				/>
				<DataTable
					columns={ columns }
					rows={ listState.items }
					loading={ listState.loading }
					emptyText={ filtered ? __( 'No questions match these filters.', 'shapelms' ) : __( 'No questions yet. Students ask them under the lessons.', 'shapelms' ) }
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
			{ viewing && <ThreadModal id={ viewing } onClose={ () => setViewing( null ) } onChange={ listState.reload } /> }
		</>
	);
}
