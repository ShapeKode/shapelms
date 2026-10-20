/**
 * Snackbar helpers.
 */
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

export function success( message ) {
	dispatch( noticesStore ).createNotice( 'success', message, { type: 'snackbar', isDismissible: true } );
}

export function error( message ) {
	dispatch( noticesStore ).createNotice( 'error', message, { type: 'snackbar', isDismissible: true } );
}
