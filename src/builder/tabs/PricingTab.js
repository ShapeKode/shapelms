/**
 * Builder: pricing.
 */
import { useState } from '@wordpress/element';
import { TextControl, RadioControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { post, errorMessage } from '../../shared/api';
import { success, error as notifyError } from '../../admin/notices';

export default function PricingTab( { course, update, setCourse, config } ) {
	const [ busy, setBusy ] = useState( false );
	const paid = course.meta.price_type === 'paid';

	const createProduct = () => {
		setBusy( true );
		post( 'woocommerce/product', { course_id: course.id } )
			.then( ( res ) => {
				setCourse( ( c ) => ( { ...c, woo_product_id: res.product_id, woo_product_url: res.edit_url, meta: { ...c.meta, woo_product_id: res.product_id } } ) );
				success( __( 'WooCommerce product created.', 'shapelms' ) );
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<>
			<h2>{ __( 'Pricing', 'shapelms' ) }</h2>

			<RadioControl
				selected={ paid ? 'paid' : 'free' }
				options={ [
					{ value: 'free', label: __( 'Free — anyone with an account can enroll', 'shapelms' ) },
					{ value: 'paid', label: __( 'Paid — students buy the course', 'shapelms' ) },
				] }
				onChange={ ( price_type ) => update( { meta: { price_type } } ) }
			/>

			{ paid && (
				<div className="shapelms-builder__row">
					<TextControl type="number" label={ `${ __( 'Regular price', 'shapelms' ) } (${ config.currency })` } value={ course.meta.price || 0 } min={ 0 } step={ 0.01 } onChange={ ( v ) => update( { meta: { price: Number( v ) || 0 } } ) } __nextHasNoMarginBottom />
					<TextControl type="number" label={ `${ __( 'Sale price', 'shapelms' ) } (${ config.currency })` } help={ __( 'Leave 0 for no sale.', 'shapelms' ) } value={ course.meta.sale_price || 0 } min={ 0 } step={ 0.01 } onChange={ ( v ) => update( { meta: { sale_price: Number( v ) || 0 } } ) } __nextHasNoMarginBottom />
				</div>
			) }

			{ paid && course.woo_product_url !== undefined && (
				<div className="shapelms-list-editor">
					<span className="shapelms-list-editor__label">{ __( 'WooCommerce', 'shapelms' ) }</span>
					{ course.woo_product_id ? (
						<p>
							{ __( 'Linked to product', 'shapelms' ) } #{ course.woo_product_id }.{ ' ' }
							<a href={ course.woo_product_url } target="_blank" rel="noreferrer">{ __( 'Edit product', 'shapelms' ) }</a>
						</p>
					) : (
						<>
							<p className="shapelms-muted">{ __( 'Create a hidden WooCommerce product for this course so it can be bought through the WooCommerce checkout.', 'shapelms' ) }</p>
							<Button variant="secondary" isBusy={ busy } onClick={ createProduct }>{ __( 'Create product', 'shapelms' ) }</Button>
						</>
					) }
				</div>
			) }
		</>
	);
}
