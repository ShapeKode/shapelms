/**
 * Reads configuration from data attributes on the React root.
 * Nothing is inlined in the page; PHP writes data-* attributes only.
 */
import apiFetch from '@wordpress/api-fetch';

let cache = null;

export function readConfig( root ) {
	if ( cache ) {
		return cache;
	}
	const data = {};
	Array.prototype.forEach.call( root.attributes, ( attr ) => {
		if ( attr.name.startsWith( 'data-' ) ) {
			const key = attr.name.slice( 5 ).replace( /-([a-z])/g, ( m, c ) => c.toUpperCase() );
			data[ key ] = attr.value;
		}
	} );
	data.canManage = data.canManage === '1';
	data.setupDone = data.setupDone === '1';
	data.userId = parseInt( data.userId, 10 ) || 0;
	data.courseId = parseInt( data.courseId, 10 ) || 0;
	data.pendingInstructors = parseInt( data.pendingInstructors, 10 ) || 0;

	// WordPress registers the REST root and nonce middlewares on wp.apiFetch in the admin.
	// Outside the admin (or if the nonce is missing) register the nonce ourselves.
	if ( data.restNonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( data.restNonce ) );
	}
	if ( ! window.wpApiSettings && data.siteUrl ) {
		apiFetch.use( apiFetch.createRootURLMiddleware( data.siteUrl.replace( /\/$/, '' ) + '/wp-json/' ) );
	}

	cache = data;
	return data;
}

export function config() {
	return cache || {};
}
