/**
 * ShapeLMS checkout: Stripe Payment Element, PayPal buttons and offline payments.
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

	var payLabel = payButton.textContent;
	var state = { gateway: null, order: null, stripe: null, elements: null, paypalRendered: false };

	function selected() {
		var input = root.querySelector( 'input[name="shapelms_gateway"]:checked' );
		return input ? input.value : gateways[ 0 ];
	}

	function isOffline( id ) {
		return !! root.querySelector( '[data-gateway-panel="' + id + '"][data-gateway-offline]' );
	}

	function showPanel( id ) {
		root.querySelectorAll( '[data-gateway-panel]' ).forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-gateway-panel' ) !== id;
		} );
		payButton.hidden = id === 'paypal';
		payButton.textContent = isOffline( id ) ? payButton.getAttribute( 'data-offline-label' ) : payLabel;
	}

	var billingForm = root.querySelector( '[data-shapelms-billing]' );

	function billingValid() {
		return ! billingForm || billingForm.reportValidity();
	}

	function billing() {
		var values = {};
		if ( billingForm ) {
			billingForm.querySelectorAll( 'input[name]' ).forEach( function ( input ) {
				if ( input.name !== 'terms' ) {
					values[ input.name ] = input.value;
				}
			} );
		}
		return values;
	}

	function termsAccepted() {
		var box = billingForm ? billingForm.querySelector( 'input[name="terms"]' ) : null;
		return !! box && box.checked;
	}

	// The order is only created when the buyer pays; the server reuses an unpaid one.
	function createOrder( gateway ) {
		return api( root, 'checkout/order', { method: 'POST', body: { course_ids: courseIds, gateway: gateway, billing: billing(), terms: termsAccepted() } } ).then( function ( order ) {
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
		state.stripe = state.stripe || window.Stripe( cfg.stripeKey );
		state.elements = state.stripe.elements( { mode: 'payment', amount: Number( cfg.amountMinor ), currency: ( cfg.currency || '' ).toLowerCase(), appearance: { theme: 'stripe' } } );
		var element = state.elements.create( 'payment' );
		element.mount( '#shapelms-stripe-element' );
		element.on( 'ready', function () {
			payButton.disabled = false;
		} );
	}

	function payStripe() {
		if ( ! state.stripe || ! state.elements ) {
			return;
		}
		var fail = function ( text ) {
			payButton.disabled = false;
			message( msg, text, 'error' );
		};
		payButton.disabled = true;
		message( msg, '' );
		state.elements.submit().then( function ( submitted ) {
			if ( submitted.error ) {
				fail( submitted.error.message );
				return null;
			}
			return createOrder( 'stripe' ).then( function ( order ) {
				state.order = order;
				return state.stripe.confirmPayment( {
					elements: state.elements,
					clientSecret: order.client_secret,
					confirmParams: { return_url: window.location.href },
					redirect: 'if_required'
				} );
			} ).then( function ( result ) {
				if ( result.error ) {
					fail( result.error.message );
					return null;
				}
				message( msg, __( 'Payment received. Finishing your order…', 'shapelms' ), 'success' );
				return confirm( state.order.order_key, { intent_id: result.paymentIntent.id } );
			} );
		} ).catch( function ( err ) {
			fail( err.message );
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
			onClick: function ( data, actions ) {
				return billingValid() ? actions.resolve() : actions.reject();
			},
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
		payButton.disabled = false;
		message( msg, '' );
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
		if ( ! billingValid() ) {
			return;
		}
		if ( state.gateway === 'stripe' ) {
			payStripe();
		} else if ( isOffline( state.gateway ) ) {
			payButton.disabled = true;
			message( msg, '' );
			createOrder( state.gateway ).catch( function ( err ) {
				payButton.disabled = false;
				message( msg, err.message, 'error' );
			} );
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
