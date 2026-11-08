/**
 * Order details with the actions that fit the order's state:
 * mark as paid, refund (full or partial), cancel with a reason, other status moves, history and notes.
 *
 * The server decides what is allowed (order.allowed, order.refundable); this only offers those.
 */
import { useState } from '@wordpress/element';
import { Button, Modal, Notice, SelectControl, TextControl, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../../shared/api';
import { Badge, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';

// Moves offered under "Change status" (the main ones have their own buttons).
const OTHER_STATUSES = {
	pending: __( 'Pending payment', 'shapelms' ),
	'on-hold': __( 'On hold (waiting for payment)', 'shapelms' ),
	failed: __( 'Failed', 'shapelms' ),
};

const CANCEL_REASONS = [
	__( 'Customer changed or canceled order', 'shapelms' ),
	__( 'Payment declined', 'shapelms' ),
	__( 'Fraudulent order', 'shapelms' ),
	__( 'Courses unavailable', 'shapelms' ),
];
const OTHER = '__other';

const ONLINE = [ 'stripe', 'paypal' ];

function dateTime( value ) {
	if ( ! value ) {
		return '—';
	}
	const date = new Date( value.includes( 'T' ) ? value : value.replace( ' ', 'T' ) + 'Z' );
	return isNaN( date.getTime() ) ? value : date.toLocaleString( undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' } );
}

function money( amount, order ) {
	// Same symbol and position as the order's total.
	const sample = order.total_html.replace( /[\d.,\s]+/, '{n}' );
	return sample.includes( '{n}' ) ? sample.replace( '{n}', Number( amount ).toFixed( 2 ) ) : Number( amount ).toFixed( 2 );
}

export default function OrderModal( { order: initial, labels, onClose, onChange } ) {
	const [ order, setOrder ] = useState( initial );
	// null | paid | cancel | refund | status
	const [ mode, setMode ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ reason, setReason ] = useState( '' );
	const [ reasonText, setReasonText ] = useState( '' );
	const [ amount, setAmount ] = useState( '' );
	const [ nextStatus, setNextStatus ] = useState( '' );
	const [ note, setNote ] = useState( '' );

	const allowed = order.allowed || [];
	const trashed = order.view === 'trash';
	const courses = order.items.map( ( i ) => i.title ).join( ', ' );
	const others = allowed.filter( ( s ) => OTHER_STATUSES[ s ] );

	const open = ( next ) => {
		setMode( next );
		setReason( '' );
		setReasonText( '' );
		setAmount( next === 'refund' ? String( order.refundable ) : '' );
	};

	const send = ( path, data, message ) => {
		setBusy( true );
		return post( path, data )
			.then( ( updated ) => {
				setOrder( updated );
				setMode( null );
				setNextStatus( '' );
				success( message );
				onChange();
				return updated;
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const cancelReason = reason === OTHER ? reasonText.trim() : reason;
	const refundAmount = parseFloat( amount );
	const refundValid = refundAmount > 0 && refundAmount <= order.refundable + 0.001;
	const fullRefund = refundValid && refundAmount >= order.refundable - 0.001 && order.refunded === 0;

	const panels = {
		paid: (
			<>
				<h3>{ __( 'Mark as paid', 'shapelms' ) }</h3>
				<p>{ sprintf( /* translators: 1: customer name, 2: course titles */ __( '%1$s gets access to: %2$s.', 'shapelms' ), order.billing_name || __( 'The student', 'shapelms' ), courses ) }</p>
				<p className="shapelms-muted">{ ONLINE.includes( order.gateway )
					? __( 'Only do this if you can see the payment in your gateway dashboard.', 'shapelms' )
					: __( 'Only do this after you have received the money.', 'shapelms' ) }</p>
				<div className="shapelms-modal-actions">
					<Button variant="tertiary" onClick={ () => setMode( null ) }>{ __( 'Back', 'shapelms' ) }</Button>
					<Button variant="primary" isBusy={ busy } disabled={ busy } onClick={ () => send( `orders/${ order.id }/status`, { status: 'completed' }, __( 'Order marked as paid. The student now has access.', 'shapelms' ) ) }>{ __( 'Mark as paid', 'shapelms' ) }</Button>
				</div>
			</>
		),
		cancel: (
			<>
				<h3>{ sprintf( /* translators: %d: order id */ __( 'Cancel order #%d', 'shapelms' ), order.id ) }</h3>
				<SelectControl
					label={ __( 'Reason for cancellation', 'shapelms' ) }
					value={ reason }
					onChange={ setReason }
					options={ [ { value: '', label: __( 'Select a reason', 'shapelms' ) }, ...CANCEL_REASONS.map( ( r ) => ( { value: r, label: r } ) ), { value: OTHER, label: __( 'Other', 'shapelms' ) } ] }
					__nextHasNoMarginBottom
				/>
				{ reason === OTHER && <TextareaControl label={ __( 'Describe the reason', 'shapelms' ) } value={ reasonText } onChange={ setReasonText } rows={ 3 } __nextHasNoMarginBottom /> }
				{ order.status === 'completed' && (
					<Notice status="warning" isDismissible={ false }>
						{ sprintf( /* translators: %s: course titles */ __( 'The student loses access to %s. Cancelling does not send money back; use Refund for that.', 'shapelms' ), courses ) }
					</Notice>
				) }
				<div className="shapelms-modal-actions">
					<Button variant="tertiary" onClick={ () => setMode( null ) }>{ __( 'Back', 'shapelms' ) }</Button>
					<Button variant="primary" isDestructive isBusy={ busy } disabled={ busy || ! cancelReason } onClick={ () => send( `orders/${ order.id }/status`, { status: 'cancelled', reason: cancelReason }, __( 'Order cancelled.', 'shapelms' ) ) }>{ __( 'Cancel order', 'shapelms' ) }</Button>
				</div>
			</>
		),
		refund: (
			<>
				<h3>{ __( 'Refund', 'shapelms' ) }</h3>
				<TextControl
					type="number"
					min="0.01"
					step="0.01"
					max={ order.refundable }
					label={ __( 'Amount to refund', 'shapelms' ) }
					value={ amount }
					onChange={ setAmount }
					help={ sprintf( /* translators: %s: amount */ __( 'Up to %s. A full refund removes course access; a partial refund keeps it.', 'shapelms' ), money( order.refundable, order ) ) }
					__nextHasNoMarginBottom
				/>
				<TextareaControl label={ __( 'Reason (optional)', 'shapelms' ) } value={ reasonText } onChange={ setReasonText } rows={ 2 } __nextHasNoMarginBottom />
				<Notice status="info" isDismissible={ false }>
					{ ONLINE.includes( order.gateway )
						? sprintf( /* translators: %s: gateway name */ __( 'This records the refund in ShapeLMS. Send the money back from your %s dashboard; the refund it reports later is not counted twice.', 'shapelms' ), order.gateway === 'paypal' ? 'PayPal' : 'Stripe' )
						: __( 'This records the refund in ShapeLMS. Return the money to the student yourself.', 'shapelms' ) }
				</Notice>
				<div className="shapelms-modal-actions">
					<Button variant="tertiary" onClick={ () => setMode( null ) }>{ __( 'Back', 'shapelms' ) }</Button>
					<Button
						variant="primary"
						isDestructive
						isBusy={ busy }
						disabled={ busy || ! refundValid }
						onClick={ () => send( `orders/${ order.id }/refund`, { amount: refundAmount, reason: reasonText.trim() }, fullRefund ? __( 'Order refunded. Course access removed.', 'shapelms' ) : __( 'Partial refund recorded. Course access kept.', 'shapelms' ) ) }
					>
						{ refundValid
							? sprintf( /* translators: %s: amount */ __( 'Refund %s', 'shapelms' ), money( refundAmount, order ) )
							: __( 'Refund', 'shapelms' ) }
					</Button>
				</div>
			</>
		),
		status: (
			<>
				<h3>{ sprintf( /* translators: %s: status */ __( 'Change status to %s', 'shapelms' ), OTHER_STATUSES[ nextStatus ] || '' ) }</h3>
				<p className="shapelms-muted">
					{ nextStatus === 'failed'
						? __( 'The payment is marked as failed and the student has no access.', 'shapelms' )
						: __( 'The order waits for payment again. The student has no access until it is marked as paid.', 'shapelms' ) }
				</p>
				<TextareaControl label={ __( 'Reason (optional)', 'shapelms' ) } value={ reasonText } onChange={ setReasonText } rows={ 2 } __nextHasNoMarginBottom />
				<div className="shapelms-modal-actions">
					<Button variant="tertiary" onClick={ () => {
						setMode( null );
						setNextStatus( '' );
					} }>{ __( 'Back', 'shapelms' ) }</Button>
					<Button variant="primary" isBusy={ busy } disabled={ busy } onClick={ () => send( `orders/${ order.id }/status`, { status: nextStatus, reason: reasonText.trim() }, __( 'Order status changed.', 'shapelms' ) ) }>{ __( 'Change status', 'shapelms' ) }</Button>
				</div>
			</>
		),
	};

	const addNote = () => {
		send( `orders/${ order.id }/notes`, { note: note.trim() }, __( 'Note added.', 'shapelms' ) ).then( ( updated ) => updated && setNote( '' ) );
	};

	const hasActions = ! trashed && ( allowed.length > 0 || order.refundable > 0 );

	return (
		<Modal title={ sprintf( /* translators: %d: order id */ __( 'Order #%d', 'shapelms' ), order.id ) } onRequestClose={ onClose } size="medium" className="shapelms-order-modal">
			<p className="shapelms-order-badges">
				<Badge tone={ labels.viewTone[ order.view ] }>{ labels.viewLabel[ order.view ] }</Badge>
				<Badge tone={ labels.paymentTone[ order.payment_status ] }>{ labels.paymentLabel[ order.payment_status ] }</Badge>
			</p>
			<p><strong>{ order.billing_name }</strong> · { order.billing_email }</p>
			{ order.billing && order.billing.address && (
				<p className="shapelms-muted">{ [ order.billing.phone, order.billing.address, order.billing.city, order.billing.state, order.billing.zip_code, order.billing.country ].filter( Boolean ).join( ', ' ) }</p>
			) }
			<p className="shapelms-muted">{ order.gateway } · { order.transaction_id || '—' } · { formatDate( order.created_at ) }</p>
			<table className="shapelms-admin-table">
				<tbody>
					{ order.items.map( ( item ) => (
						<tr key={ item.id }><td>{ item.title }</td><td className="is-right">{ item.price_html }</td></tr>
					) ) }
					<tr><td><strong>{ __( 'Total', 'shapelms' ) }</strong></td><td className="is-right"><strong>{ order.total_html }</strong></td></tr>
					{ order.refunded > 0 && (
						<tr><td>{ __( 'Refunded', 'shapelms' ) }</td><td className="is-right">−{ order.refunded_html }</td></tr>
					) }
				</tbody>
			</table>

			{ trashed && <p className="shapelms-muted">{ __( 'Restore this order from the trash to change it.', 'shapelms' ) }</p> }

			{ mode ? (
				<div className="shapelms-order-panel">{ panels[ mode ] }</div>
			) : hasActions && (
				<div className="shapelms-order-actions">
					{ allowed.includes( 'completed' ) && <Button variant="primary" onClick={ () => open( 'paid' ) }>{ __( 'Mark as paid', 'shapelms' ) }</Button> }
					{ order.refundable > 0 && <Button variant="secondary" onClick={ () => open( 'refund' ) }>{ __( 'Refund', 'shapelms' ) }</Button> }
					{ allowed.includes( 'cancelled' ) && <Button variant="tertiary" isDestructive onClick={ () => open( 'cancel' ) }>{ __( 'Cancel order', 'shapelms' ) }</Button> }
					{ others.length > 0 && (
						<SelectControl
							className="shapelms-order-actions__more"
							aria-label={ __( 'Change status', 'shapelms' ) }
							value={ nextStatus }
							onChange={ ( value ) => {
								setNextStatus( value );
								if ( value ) {
									open( 'status' );
								}
							} }
							options={ [ { value: '', label: __( 'Change status…', 'shapelms' ) }, ...others.map( ( s ) => ( { value: s, label: OTHER_STATUSES[ s ] } ) ) ] }
							__nextHasNoMarginBottom
						/>
					) }
				</div>
			) }
			{ ! trashed && ! hasActions && ! mode && order.status === 'refunded' && <p className="shapelms-muted">{ __( 'This order was refunded in full. Nothing else can be changed.', 'shapelms' ) }</p> }

			<div className="shapelms-order-history">
				<h3>{ __( 'Order history', 'shapelms' ) }</h3>
				{ ! trashed && (
					<div className="shapelms-order-history__add">
						<TextareaControl aria-label={ __( 'Add a note', 'shapelms' ) } placeholder={ __( 'Add a private note…', 'shapelms' ) } value={ note } onChange={ setNote } rows={ 2 } __nextHasNoMarginBottom />
						<Button variant="secondary" size="compact" disabled={ busy || ! note.trim() } onClick={ addNote }>{ __( 'Add note', 'shapelms' ) }</Button>
					</div>
				) }
				{ order.history && order.history.length ? (
					<ol>
						{ order.history.map( ( entry, i ) => (
							<li key={ i } className={ `is-${ entry.type }` }>
								<p>{ entry.text }</p>
								<span className="shapelms-muted">{ dateTime( entry.time ) } · { entry.author }</span>
							</li>
						) ) }
					</ol>
				) : (
					<p className="shapelms-muted">{ __( 'No changes recorded yet.', 'shapelms' ) }</p>
				) }
			</div>

			<div className="shapelms-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Close', 'shapelms' ) }</Button>
			</div>
		</Modal>
	);
}
