/**
 * ShapeLMS checkout: Stripe Payment Element and PayPal buttons.
 *
 * @package ShapeLMS
 */
( function () {
	'use strict';

	var root = document.getElementById( 'shapelms-checkout' );
	if ( ! root || ! window.shapelms ) {
		return;
	}

	var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( s ) {
		return s;
	};

	var cfg = window.shapelms.config( root );
	var api = window.shapelms.api;
	var message = window.shapelms.message;
	var msg = root.querySelector( '[data-shapelms-checkout-message]' );
	var payButton = root.querySelector( '[data-shapelms-pay]' );
	var courseIds = ( cfg.courseIds || '' ).split( ',' ).filter( Boolean ).map( Number );
	var gateways = ( cfg.gateways || '' ).split( ',' ).filter( Boolean );

	if ( ! payButton || ! courseIds.length ) {
		return;
	}

	var state = { gateway: null, order: null, stripe: null, elements: null, paypalRendered: false };

	function selected() {
		var input = root.querySelector( 'input[name="shapelms_gateway"]:checked' );
		return input ? input.value : gateways[ 0 ];
	}

	function showPanel( id ) {
		root.querySelectorAll( '[data-gateway-panel]' ).forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-gateway-panel' ) !== id;
		} );
		payButton.hidden = id === 'paypal';
	}

	function createOrder( gateway ) {
		if ( state.order && state.order.gateway === gateway ) {
			return Promise.resolve( state.order );
		}
		return api( root, 'checkout/order', { method: 'POST', body: { course_ids: courseIds, gateway: gateway } } ).then( function ( order ) {
			if ( order.completed && order.redirect ) {
				window.location.href = order.redirect;
				return order;
			}
			order.gateway = gateway;
			state.order = order;
			return order;
		} );
	}

	function confirm( orderKey, data ) {
		return api( root, 'checkout/confirm', { method: 'POST', body: { order_key: orderKey, data: data } } ).then( function ( res ) {
			if ( res.redirect ) {
				window.location.href = res.redirect;
			}
			return res;
		} );
	}

	/* Stripe ------------------------------------------------------------ */

	function setupStripe() {
		if ( ! window.Stripe || ! cfg.stripeKey ) {
			message( msg, __( 'Stripe could not be loaded.', 'shapelms' ), 'error' );
			return;
		}
		if ( state.elements ) {
			return;
		}
		payButton.disabled = true;
		createOrder( 'stripe' ).then( function ( order ) {
			if ( ! order.client_secret ) {
				return;
			}
			state.stripe = state.stripe || window.Stripe( cfg.stripeKey );
			state.elements = state.stripe.elements( { clientSecret: order.client_secret, appearance: { theme: 'stripe' } } );
			var element = state.elements.create( 'payment' );
			element.mount( '#shapelms-stripe-element' );
			element.on( 'ready', function () {
				payButton.disabled = false;
			} );
		} ).catch( function ( err ) {
			message( msg, err.message, 'error' );
		} );
	}

	function payStripe() {
		if ( ! state.stripe || ! state.elements || ! state.order ) {
			return;
		}
		payButton.disabled = true;
		message( msg, '' );
		state.stripe.confirmPayment( {
			elements: state.elements,
			confirmParams: { return_url: window.location.href },
			redirect: 'if_required'
		} ).then( function ( result ) {
			if ( result.error ) {
				payButton.disabled = false;
				message( msg, result.error.message, 'error' );
				return;
			}
			message( msg, __( 'Payment received. Finishing your order…', 'shapelms' ), 'success' );
			confirm( state.order.order_key, { intent_id: result.paymentIntent.id } ).catch( function ( err ) {
				payButton.disabled = false;
				message( msg, err.message, 'error' );
			} );
		} );
	}

	/* PayPal ------------------------------------------------------------ */

	function setupPayPal() {
		if ( ! window.paypal || state.paypalRendered ) {
			if ( ! window.paypal ) {
				message( msg, __( 'PayPal could not be loaded.', 'shapelms' ), 'error' );
			}
			return;
		}
		state.paypalRendered = true;
		window.paypal.Buttons( {
			style: { layout: 'vertical', shape: 'rect' },
			createOrder: function () {
				message( msg, '' );
				return createOrder( 'paypal' ).then( function ( order ) {
					return order.paypal_order_id;
				} ).catch( function ( err ) {
					message( msg, err.message, 'error' );
					throw err;
				} );
			},
			onApprove: function ( data ) {
				message( msg, __( 'Payment approved. Finishing your order…', 'shapelms' ), 'success' );
				return confirm( state.order.order_key, { paypal_order_id: data.orderID } ).catch( function ( err ) {
					message( msg, err.message, 'error' );
				} );
			},
			onError: function () {
				message( msg, __( 'PayPal could not process the payment. Please try again.', 'shapelms' ), 'error' );
			}
		} ).render( '#shapelms-paypal-buttons' );
	}

	/* Wiring ------------------------------------------------------------ */

	function switchGateway() {
		var id = selected();
		state.gateway = id;
		showPanel( id );
		if ( id === 'stripe' ) {
			setupStripe();
		} else if ( id === 'paypal' ) {
			setupPayPal();
		}
	}

	root.querySelectorAll( 'input[name="shapelms_gateway"]' ).forEach( function ( input ) {
		input.addEventListener( 'change', switchGateway );
	} );

	payButton.addEventListener( 'click', function () {
		if ( state.gateway === 'stripe' ) {
			payStripe();
		}
	} );

	// Stripe return_url flow (3DS redirect) lands back here with the intent in the query.
	var params = new URL( window.location.href ).searchParams;
	var returnedIntent = params.get( 'payment_intent' );
	var returnedKey = window.sessionStorage ? window.sessionStorage.getItem( 'shapelms-order-key' ) : null;
	if ( returnedIntent && returnedKey ) {
		message( msg, __( 'Finishing your order…', 'shapelms' ), 'success' );
		confirm( returnedKey, { intent_id: returnedIntent } ).catch( function ( err ) {
			message( msg, err.message, 'error' );
		} );
		return;
	}

	var originalCreate = createOrder;
	createOrder = function ( gateway ) {
		return originalCreate( gateway ).then( function ( order ) {
			try {
				if ( order && order.order_key ) {
					window.sessionStorage.setItem( 'shapelms-order-key', order.order_key );
				}
			} catch ( e ) {
				// Ignore.
			}
			return order;
		} );
	};

	switchGateway();
}() );
