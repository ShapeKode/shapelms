/**
 * Admin app entry.
 */
import { createRoot } from '@wordpress/element';
import { readConfig } from '../shared/config';
import App from './App';
import './style.scss';

const root = document.getElementById( 'shapelms-admin' );

if ( root ) {
	const cfg = readConfig( root );
	createRoot( root ).render( <App screen={ root.getAttribute( 'data-screen' ) || 'dashboard' } config={ cfg } /> );
}
