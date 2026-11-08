/**
 * Orders: status tabs with counts, payment status filter, bulk trash/restore/delete and sorting.
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, BulkActions, DataTable, Pagination, Badge, ErrorNotice, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';
import OrderModal from '../components/OrderModal';

// Tabs in display order. "all" and "trash" are views, the rest group order statuses (see OrderRepository::VIEWS).
const VIEWS = [
	[ 'all', __( 'All', 'shapelms' ) ],
	[ 'incomplete', __( 'Incomplete', 'shapelms' ) ],
	[ 'completed', __( 'Completed', 'shapelms' ) ],
	[ 'cancelled', __( 'Cancelled', 'shapelms' ) ],
	[ 'trash', __( 'Trash', 'shapelms' ) ],
	[ 'pending', __( 'Pending', 'shapelms' ) ],
];
const viewLabel = Object.fromEntries( VIEWS );
const viewTone = { incomplete: 'warning', pending: 'info', completed: 'success', cancelled: 'neutral', trash: 'neutral' };

const PAYMENTS = [
	[ 'paid', __( 'Paid', 'shapelms' ) ],
	[ 'unpaid', __( 'Unpaid', 'shapelms' ) ],
	[ 'failed', __( 'Failed', 'shapelms' ) ],
	[ 'refunded', __( 'Refunded', 'shapelms' ) ],
	[ 'partially-refunded', __( 'Partially Refunded', 'shapelms' ) ],
	[ 'pending', __( 'Pending', 'shapelms' ) ],
];
const paymentLabel = Object.fromEntries( PAYMENTS );
const paymentTone = { paid: 'success', unpaid: 'warning', failed: 'danger', refunded: 'neutral', 'partially-refunded': 'info', pending: 'info' };

export default function Orders() {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search );
	const [ viewing, setViewing ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const listState = usePagedList( 'orders', { view: '', payment: '', search: debounced } );

	if ( listState.query.search !== debounced ) {
		listState.setQuery( { search: debounced } );
	}

	const view = listState.query.view || 'all';
	const inTrash = view === 'trash';
	// Only rows on the current page count, so paging or filtering drops stale picks.
	const selectedIds = selected.filter( ( id ) => listState.items.some( ( row ) => row.id === id ) );

	const run = ( action, ids ) => {
		if ( action === 'delete' && ! window.confirm( sprintf( /* translators: %d: number of orders */ _n( 'Delete %d order permanently? This cannot be undone. Enrollments are not changed.', 'Delete %d orders permanently? This cannot be undone. Enrollments are not changed.', ids.length, 'shapelms' ), ids.length ) ) ) {
			return;
		}
		if ( action === 'complete' && ! window.confirm( sprintf( /* translators: %d: number of orders */ _n( 'Mark %d order as paid? The student gets course access. Only do this after you have received the money.', 'Mark %d orders as paid? The students get course access. Only do this after you have received the money.', ids.length, 'shapelms' ), ids.length ) ) ) {
			return;
		}
		if ( action === 'cancel' && ! window.confirm( sprintf( /* translators: %d: number of orders */ _n( 'Cancel %d order? Paid orders lose their course access. No money is sent back.', 'Cancel %d orders? Paid orders lose their course access. No money is sent back.', ids.length, 'shapelms' ), ids.length ) ) ) {
			return;
		}
		const message = ( n ) => {
			if ( action === 'complete' ) {
				/* translators: %d: number of orders */
				return sprintf( _n( '%d order marked as paid.', '%d orders marked as paid.', n, 'shapelms' ), n );
			}
			if ( action === 'cancel' ) {
				/* translators: %d: number of orders */
				return sprintf( _n( '%d order cancelled.', '%d orders cancelled.', n, 'shapelms' ), n );
			}
			if ( action === 'trash' ) {
				/* translators: %d: number of orders */
				return sprintf( _n( '%d order moved to the trash.', '%d orders moved to the trash.', n, 'shapelms' ), n );
			}
			if ( action === 'restore' ) {
				/* translators: %d: number of orders */
				return sprintf( _n( '%d order restored.', '%d orders restored.', n, 'shapelms' ), n );
			}
			/* translators: %d: number of orders */
			return sprintf( _n( '%d order deleted permanently.', '%d orders deleted permanently.', n, 'shapelms' ), n );
		};
		setBusy( true );
		post( 'orders/bulk', { action, ids } )
			.then( ( res ) => {
				if ( res.done ) {
					success( message( res.done ) );
				}
				if ( res.skipped ) {
					/* translators: %d: number of orders */
					notifyError( sprintf( _n( '%d order was skipped because its status cannot change that way.', '%d orders were skipped because their status cannot change that way.', res.skipped, 'shapelms' ), res.skipped ) );
				}
				setSelected( [] );
				listState.reload();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const bulkOptions = inTrash
		? [ { value: 'restore', label: __( 'Restore', 'shapelms' ) }, { value: 'delete', label: __( 'Delete permanently', 'shapelms' ) } ]
		: [
			{ value: 'complete', label: __( 'Mark as paid', 'shapelms' ) },
			{ value: 'cancel', label: __( 'Cancel', 'shapelms' ) },
			{ value: 'trash', label: __( 'Trash', 'shapelms' ) },
		];

	const columns = [
		{ key: 'id', label: __( 'Order ID', 'shapelms' ), sortable: true, render: ( row ) => <button type="button" className="shapelms-link-button" onClick={ () => setViewing( row ) }><strong>#{ row.id }</strong></button> },
		{ key: 'billing_name', label: __( 'Customer', 'shapelms' ), render: ( row ) => <><strong>{ row.billing_name }</strong><div className="shapelms-muted">{ row.billing_email }</div></> },
		{ key: 'items', label: __( 'Courses', 'shapelms' ), render: ( row ) => row.items.map( ( i ) => i.title ).join( ', ' ) },
		{ key: 'created_at', label: __( 'Date', 'shapelms' ), sortable: true, render: ( row ) => formatDate( row.created_at ) },
		{ key: 'payment_status', label: __( 'Payment', 'shapelms' ), render: ( row ) => <Badge tone={ paymentTone[ row.payment_status ] }>{ paymentLabel[ row.payment_status ] }</Badge> },
		{ key: 'view', label: __( 'Status', 'shapelms' ), render: ( row ) => <Badge tone={ viewTone[ row.view ] }>{ viewLabel[ row.view ] }</Badge> },
		{
			key: 'total',
			label: __( 'Total', 'shapelms' ),
			sortable: true,
			render: ( row ) => (
				<>
					<strong>{ row.total_html }</strong>
					{ row.payment_status === 'partially-refunded' && <div className="shapelms-muted">{ sprintf( /* translators: %s: refunded amount */ __( '%s refunded', 'shapelms' ), row.refunded_html ) }</div> }
				</>
			),
		},
		{
			key: 'actions',
			label: '',
			className: 'is-actions',
			render: ( row ) => ( inTrash ? (
				<>
					<Button size="compact" variant="secondary" disabled={ busy } onClick={ () => run( 'restore', [ row.id ] ) }>{ __( 'Restore', 'shapelms' ) }</Button>
					<Button size="compact" variant="tertiary" isDestructive disabled={ busy } onClick={ () => run( 'delete', [ row.id ] ) }>{ __( 'Delete permanently', 'shapelms' ) }</Button>
				</>
			) : (
				<>
					<Button size="compact" variant="secondary" onClick={ () => setViewing( row ) }>{ __( 'View', 'shapelms' ) }</Button>
					<Button size="compact" variant="tertiary" isDestructive disabled={ busy } onClick={ () => run( 'trash', [ row.id ] ) }>{ __( 'Trash', 'shapelms' ) }</Button>
				</>
			) ),
		},
	];

	const filtered = listState.query.payment || listState.query.search;
	const emptyText = filtered
		? __( 'No orders match these filters.', 'shapelms' )
		: ( inTrash ? __( 'The trash is empty.', 'shapelms' ) : __( 'No orders yet.', 'shapelms' ) );

	return (
		<>
			<PageHeader title={ __( 'Orders', 'shapelms' ) } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar
					actions={ <BulkActions key={ inTrash ? 'trash' : 'all' } options={ bulkOptions } onApply={ ( action ) => run( action, selectedIds ) } count={ selectedIds.length } busy={ busy } /> }
					search={ search }
					onSearch={ setSearch }
					filters={ [ {
						// Order status, with how many orders each one holds.
						key: 'view',
						value: view,
						onChange: ( next ) => {
							setSelected( [] );
							listState.setQuery( { view: next === 'all' ? '' : next } );
						},
						options: VIEWS.map( ( [ value, label ] ) => ( {
							value,
							label: sprintf( /* translators: 1: order status, 2: number of orders */ __( '%1$s (%2$d)', 'shapelms' ), value === 'all' ? __( 'All statuses', 'shapelms' ) : label, listState.counts?.[ value ] ?? 0 ),
						} ) ),
					}, {
						key: 'payment',
						value: listState.query.payment,
						onChange: ( payment ) => listState.setQuery( { payment } ),
						options: [ { value: '', label: __( 'All payment statuses', 'shapelms' ) }, ...PAYMENTS.map( ( [ value, label ] ) => ( { value, label } ) ) ],
					} ] }
				/>
				<DataTable
					columns={ columns }
					rows={ listState.items }
					loading={ listState.loading }
					emptyText={ emptyText }
					selection={ { selected: selectedIds, onChange: setSelected } }
					sort={ {
						// The API sorts by newest order id when nothing is set.
						orderby: listState.query.orderby || 'id',
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
			{ viewing && <OrderModal order={ viewing } labels={ { viewLabel, viewTone, paymentLabel, paymentTone } } onClose={ () => setViewing( null ) } onChange={ listState.reload } /> }
		</>
	);
}
