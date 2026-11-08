/**
 * Instructor applications and list.
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, BulkActions, DataTable, Pagination, Badge, ErrorNotice, Avatar, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';

const tone = { pending: 'warning', approved: 'success', rejected: 'neutral', blocked: 'danger' };

export default function Instructors() {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search );
	const listState = usePagedList( 'instructors', { status: 'all', search: debounced } );
	const [ selected, setSelected ] = useState( [] );
	const [ bulkBusy, setBulkBusy ] = useState( false );

	if ( listState.query.search !== debounced ) {
		listState.setQuery( { search: debounced } );
	}

	// Only rows on the current page count, so paging or filtering drops stale picks.
	const selectedIds = selected.filter( ( id ) => listState.items.some( ( row ) => row.id === id ) );

	const bulkOptions = [
		{ value: 'approved', label: __( 'Approve', 'shapelms' ) },
		{ value: 'rejected', label: __( 'Reject', 'shapelms' ) },
		{ value: 'blocked', label: __( 'Block', 'shapelms' ) },
		{ value: 'delete', label: __( 'Delete permanently', 'shapelms' ) },
	];

	const applyBulk = ( action ) => {
		const ids = selectedIds;
		if ( ! ids.length ) {
			return;
		}
		const confirms = {
			/* translators: %d: number of instructors */
			blocked: _n( 'Block %d instructor? They can no longer manage courses. Their courses stay published.', 'Block %d instructors? They can no longer manage courses. Their courses stay published.', ids.length, 'shapelms' ),
			/* translators: %d: number of instructors */
			delete: _n( 'Delete %d instructor permanently? Their instructor role and profile (headline, bio, social links) are removed. Their user account and courses are kept. This cannot be undone.', 'Delete %d instructors permanently? Their instructor role and profile (headline, bio, social links) are removed. Their user accounts and courses are kept. This cannot be undone.', ids.length, 'shapelms' ),
		};
		if ( confirms[ action ] && ! window.confirm( sprintf( confirms[ action ], ids.length ) ) ) {
			return;
		}
		setBulkBusy( true );
		post( 'instructors/bulk', { action, ids } )
			.then( ( res ) => {
				if ( res.done ) {
					success( action === 'delete'
						/* translators: %d: number of instructors */
						? sprintf( _n( '%d instructor deleted.', '%d instructors deleted.', res.done, 'shapelms' ), res.done )
						/* translators: %d: number of instructors */
						: sprintf( _n( '%d instructor updated.', '%d instructors updated.', res.done, 'shapelms' ), res.done ) );
				}
				if ( res.skipped ) {
					/* translators: %d: number of users */
					notifyError( sprintf( _n( '%d site administrator was skipped; administrators always keep instructor access.', '%d site administrators were skipped; administrators always keep instructor access.', res.skipped, 'shapelms' ), res.skipped ) );
				}
				setSelected( [] );
				listState.reload();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBulkBusy( false ) );
	};

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
				<Toolbar actions={ <BulkActions options={ bulkOptions } onApply={ applyBulk } count={ selectedIds.length } busy={ bulkBusy } /> } search={ search } onSearch={ setSearch } filters={ [ { key: 'status', value: listState.query.status, onChange: ( status ) => listState.setQuery( { status } ), options: [ { value: 'all', label: __( 'All', 'shapelms' ) }, { value: 'pending', label: __( 'Pending', 'shapelms' ) }, { value: 'approved', label: __( 'Approved', 'shapelms' ) }, { value: 'rejected', label: __( 'Rejected', 'shapelms' ) }, { value: 'blocked', label: __( 'Blocked', 'shapelms' ) } ] } ] } />
				<DataTable columns={ columns } rows={ listState.items } loading={ listState.loading } emptyText={ __( 'No instructors yet.', 'shapelms' ) } selection={ { selected: selectedIds, onChange: setSelected } } />
				<Pagination page={ listState.query.page } pages={ listState.pages } total={ listState.total } onChange={ listState.setPage } />
			</Card>
		</>
	);
}
