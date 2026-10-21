/**
 * Orders.
 */
import { useState } from '@wordpress/element';
import { Button, Modal, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { post, del, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, DataTable, Pagination, Badge, ErrorNotice, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';

const tone = { pending: 'warning', completed: 'success', refunded: 'neutral', cancelled: 'neutral', failed: 'danger' };
const statuses = [ 'pending', 'completed', 'refunded', 'cancelled', 'failed' ];

function OrderModal( { order, onClose, onChange } ) {
	const [ status, setStatus ] = useState( order.status );
	const [ busy, setBusy ] = useState( false );

	const save = () => {
		setBusy( true );
		post( `orders/${ order.id }/status`, { status } )
			.then( () => {
				success( __( 'Order updated.', 'shapelms' ) );
				onChange();
				onClose();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<Modal title={ sprintf( /* translators: %d: order id */ __( 'Order #%d', 'shapelms' ), order.id ) } onRequestClose={ onClose } size="medium">
			<p><strong>{ order.billing_name }</strong> · { order.billing_email }</p>
			<p className="shapelms-muted">{ order.gateway } · { order.transaction_id || '—' } · { formatDate( order.created_at ) }</p>
			<table className="shapelms-admin-table">
				<tbody>
					{ order.items.map( ( item ) => (
						<tr key={ item.id }><td>{ item.title }</td><td className="is-right">{ item.price_html }</td></tr>
					) ) }
					<tr><td><strong>{ __( 'Total', 'shapelms' ) }</strong></td><td className="is-right"><strong>{ order.total_html }</strong></td></tr>
				</tbody>
			</table>
			<SelectControl label={ __( 'Status', 'shapelms' ) } value={ status } onChange={ setStatus } options={ statuses.map( ( s ) => ( { value: s, label: s } ) ) } help={ __( 'Completing enrolls the buyer; refunding or cancelling removes access.', 'shapelms' ) } __nextHasNoMarginBottom />
			<div className="shapelms-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Close', 'shapelms' ) }</Button>
				<Button variant="primary" isBusy={ busy } disabled={ busy || status === order.status } onClick={ save }>{ __( 'Update status', 'shapelms' ) }</Button>
			</div>
		</Modal>
	);
}

export default function Orders() {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search );
	const [ viewing, setViewing ] = useState( null );
	const listState = usePagedList( 'orders', { status: '', search: debounced } );

	if ( listState.query.search !== debounced ) {
		listState.setQuery( { search: debounced } );
	}

	const remove = ( row ) => {
		if ( ! window.confirm( __( 'Delete this order permanently? Enrollments are not changed.', 'shapelms' ) ) ) {
			return;
		}
		del( `orders/${ row.id }` ).then( () => {
			success( __( 'Order deleted.', 'shapelms' ) );
			listState.reload();
		} ).catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const columns = [
		{ key: 'id', label: __( 'Order', 'shapelms' ), render: ( row ) => <button type="button" className="shapelms-link-button" onClick={ () => setViewing( row ) }><strong>#{ row.id }</strong></button> },
		{ key: 'billing_name', label: __( 'Customer', 'shapelms' ), render: ( row ) => <><strong>{ row.billing_name }</strong><div className="shapelms-muted">{ row.billing_email }</div></> },
		{ key: 'items', label: __( 'Courses', 'shapelms' ), render: ( row ) => row.items.map( ( i ) => i.title ).join( ', ' ) },
		{ key: 'total_html', label: __( 'Total', 'shapelms' ) },
		{ key: 'gateway', label: __( 'Gateway', 'shapelms' ) },
		{ key: 'status', label: __( 'Status', 'shapelms' ), render: ( row ) => <Badge tone={ tone[ row.status ] }>{ row.status }</Badge> },
		{ key: 'created_at', label: __( 'Date', 'shapelms' ), render: ( row ) => formatDate( row.created_at ) },
		{ key: 'actions', label: '', className: 'is-actions', render: ( row ) => <><Button size="compact" variant="secondary" onClick={ () => setViewing( row ) }>{ __( 'View', 'shapelms' ) }</Button><Button size="compact" variant="tertiary" isDestructive onClick={ () => remove( row ) }>{ __( 'Delete', 'shapelms' ) }</Button></> },
	];

	return (
		<>
			<PageHeader title={ __( 'Orders', 'shapelms' ) } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar search={ search } onSearch={ setSearch } filters={ [ { key: 'status', value: listState.query.status, onChange: ( status ) => listState.setQuery( { status } ), options: [ { value: '', label: __( 'All statuses', 'shapelms' ) }, ...statuses.map( ( s ) => ( { value: s, label: s } ) ) ] } ] } />
				<DataTable columns={ columns } rows={ listState.items } loading={ listState.loading } emptyText={ __( 'No orders yet.', 'shapelms' ) } />
				<Pagination page={ listState.query.page } pages={ listState.pages } total={ listState.total } onChange={ listState.setPage } />
			</Card>
			{ viewing && <OrderModal order={ viewing } onClose={ () => setViewing( null ) } onChange={ listState.reload } /> }
		</>
	);
}
