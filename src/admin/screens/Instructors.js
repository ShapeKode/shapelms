/**
 * Instructor applications and list.
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { post, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, DataTable, Pagination, Badge, ErrorNotice, Avatar, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';

const tone = { pending: 'warning', approved: 'success', rejected: 'neutral', blocked: 'danger' };

export default function Instructors() {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search );
	const listState = usePagedList( 'instructors', { status: 'all', search: debounced } );

	if ( listState.query.search !== debounced ) {
		listState.setQuery( { search: debounced } );
	}

	const setStatus = ( row, status ) => {
		post( `instructors/${ row.id }/status`, { status } )
			.then( () => {
				success( __( 'Instructor updated.', 'shapelms' ) );
				listState.reload();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const columns = [
		{ key: 'name', label: __( 'Instructor', 'shapelms' ), render: ( row ) => <div className="shapelms-cell-title"><Avatar src={ row.avatar } /><div><strong>{ row.name }</strong><div className="shapelms-muted">{ row.email }</div></div></div> },
		{ key: 'headline', label: __( 'Headline', 'shapelms' ), render: ( row ) => row.headline || '—' },
		{ key: 'status', label: __( 'Status', 'shapelms' ), render: ( row ) => <Badge tone={ tone[ row.status ] || 'neutral' }>{ row.status || '—' }</Badge> },
		{ key: 'courses', label: __( 'Courses', 'shapelms' ) },
		{ key: 'registered', label: __( 'Joined', 'shapelms' ), render: ( row ) => formatDate( row.registered ) },
		{
			key: 'actions',
			label: '',
			className: 'is-actions',
			render: ( row ) => (
				<>
					{ row.status !== 'approved' && <Button size="compact" variant="primary" onClick={ () => setStatus( row, 'approved' ) }>{ __( 'Approve', 'shapelms' ) }</Button> }
					{ row.status === 'pending' && <Button size="compact" variant="tertiary" onClick={ () => setStatus( row, 'rejected' ) }>{ __( 'Reject', 'shapelms' ) }</Button> }
					{ row.status !== 'blocked' && <Button size="compact" variant="tertiary" isDestructive onClick={ () => window.confirm( __( 'Block this instructor? Their courses stay published.', 'shapelms' ) ) && setStatus( row, 'blocked' ) }>{ __( 'Block', 'shapelms' ) }</Button> }
				</>
			),
		},
	];

	return (
		<>
			<PageHeader title={ __( 'Instructors', 'shapelms' ) } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar search={ search } onSearch={ setSearch } filters={ [ { key: 'status', value: listState.query.status, onChange: ( status ) => listState.setQuery( { status } ), options: [ { value: 'all', label: __( 'All', 'shapelms' ) }, { value: 'pending', label: __( 'Pending', 'shapelms' ) }, { value: 'approved', label: __( 'Approved', 'shapelms' ) }, { value: 'rejected', label: __( 'Rejected', 'shapelms' ) }, { value: 'blocked', label: __( 'Blocked', 'shapelms' ) } ] } ] } />
				<DataTable columns={ columns } rows={ listState.items } loading={ listState.loading } emptyText={ __( 'No instructors yet.', 'shapelms' ) } />
				<Pagination page={ listState.query.page } pages={ listState.pages } total={ listState.total } onChange={ listState.setPage } />
			</Card>
		</>
	);
}
