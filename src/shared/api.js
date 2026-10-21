/**
 * Thin REST helpers on top of @wordpress/api-fetch. All paths are relative to shapelms/v1.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const NAMESPACE = '/shapelms/v1/';

function route( path ) {
	return NAMESPACE + String( path ).replace( /^\/+/, '' );
}

export function get( path, query = {} ) {
	return apiFetch( { path: addQueryArgs( route( path ), query ) } );
}

export function post( path, data = {} ) {
	return apiFetch( { path: route( path ), method: 'POST', data } );
}

export function patch( path, data = {} ) {
	return apiFetch( { path: route( path ), method: 'PUT', data } );
}

export function del( path ) {
	return apiFetch( { path: route( path ), method: 'DELETE' } );
}

/**
 * Paginated GET that returns { items, total, pages }.
 */
export function list( path, query = {} ) {
	return apiFetch( { path: addQueryArgs( route( path ), query ), parse: false } ).then( async ( response ) => {
		const json = await response.json();
		if ( ! response.ok ) {
			throw json;
		}
		if ( Array.isArray( json ) ) {
			return { items: json, total: json.length, pages: 1 };
		}
		return {
			items: json.items || [],
			total: parseInt( response.headers.get( 'X-WP-Total' ) || json.total || 0, 10 ),
			pages: parseInt( response.headers.get( 'X-WP-TotalPages' ) || json.pages || 1, 10 ),
		};
	} );
}

export function errorMessage( error, fallback = 'Something went wrong.' ) {
	if ( ! error ) {
		return fallback;
	}
	if ( typeof error === 'string' ) {
		return error;
	}
	return error.message || fallback;
}
