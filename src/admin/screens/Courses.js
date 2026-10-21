/**
 * Courses list.
 */
import { useState } from '@wordpress/element';
import { Button, Modal, TextControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, del, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, DataTable, Pagination, Badge, ErrorNotice, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';

const statusTone = { publish: 'success', draft: 'neutral', pending: 'warning', private: 'neutral' };
const statusLabel = {
	publish: __( 'Published', 'shapelms' ),
	draft: __( 'Draft', 'shapelms' ),
	pending: __( 'Pending', 'shapelms' ),
	private: __( 'Private', 'shapelms' ),
};

export default function Courses( { config } ) {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search );
	const [ creating, setCreating ] = useState( false );
	const [ title, setTitle ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const listState = usePagedList( 'courses', { status: 'any', search: debounced, mine: ! config.canManage } );

	if ( listState.query.search !== debounced ) {
		listState.setQuery( { search: debounced } );
	}

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

	const columns = [
		{
			key: 'title',
			label: __( 'Course', 'shapelms' ),
			render: ( row ) => (
				<div className="shapelms-cell-title">
					{ row.image ? <img src={ row.image } alt="" className="shapelms-thumb" /> : <span className="shapelms-thumb shapelms-thumb--empty" /> }
					<div>
						<a href={ row.edit_url }><strong>{ row.title }</strong></a>
						<div className="shapelms-muted">{ row.instructors?.[ 0 ]?.name }</div>
					</div>
				</div>
			),
		},
		{ key: 'status', label: __( 'Status', 'shapelms' ), render: ( row ) => <Badge tone={ statusTone[ row.status ] || 'neutral' }>{ statusLabel[ row.status ] || row.status }</Badge> },
		{ key: 'price', label: __( 'Price', 'shapelms' ), render: ( row ) => row.price_html },
		{ key: 'lessons', label: __( 'Lessons', 'shapelms' ), render: ( row ) => sprintf( /* translators: %d: lessons */ _n( '%d lesson', '%d lessons', row.lessons, 'shapelms' ), row.lessons ) + ' / ' + sprintf( /* translators: %d: quizzes */ _n( '%d quiz', '%d quizzes', row.quizzes, 'shapelms' ), row.quizzes ) },
		{ key: 'students', label: __( 'Students', 'shapelms' ) },
		{ key: 'rating', label: __( 'Rating', 'shapelms' ), render: ( row ) => ( row.rating.count ? `${ row.rating.average.toFixed( 1 ) } ★ (${ row.rating.count })` : '—' ) },
		{ key: 'modified', label: __( 'Updated', 'shapelms' ), render: ( row ) => formatDate( row.modified ) },
		{
			key: 'actions',
			label: '',
			className: 'is-actions',
			render: ( row ) => (
				<>
					<Button size="compact" variant="secondary" href={ row.edit_url }>{ __( 'Edit', 'shapelms' ) }</Button>
					<Button size="compact" variant="tertiary" href={ row.url } target="_blank">{ __( 'View', 'shapelms' ) }</Button>
					<Button size="compact" variant="tertiary" isDestructive onClick={ () => trash( row ) }>{ __( 'Trash', 'shapelms' ) }</Button>
				</>
			),
		},
	];

	return (
		<>
			<PageHeader title={ __( 'Courses', 'shapelms' ) } actions={ <Button variant="primary" onClick={ () => setCreating( true ) }>{ __( 'New course', 'shapelms' ) }</Button> } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar
					search={ search }
					onSearch={ setSearch }
					filters={ [
						{
							key: 'status',
							value: listState.query.status,
							onChange: ( status ) => listState.setQuery( { status } ),
							options: [
								{ value: 'any', label: __( 'All statuses', 'shapelms' ) },
								{ value: 'publish', label: __( 'Published', 'shapelms' ) },
								{ value: 'draft', label: __( 'Draft', 'shapelms' ) },
								{ value: 'pending', label: __( 'Pending review', 'shapelms' ) },
							],
						},
					] }
				/>
				<DataTable columns={ columns } rows={ listState.items } loading={ listState.loading } emptyText={ __( 'No courses yet. Create your first course.', 'shapelms' ) } />
				<Pagination page={ listState.query.page } pages={ listState.pages } total={ listState.total } onChange={ listState.setPage } />
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
