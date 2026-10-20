/**
 * Course builder entry (mounted on the course edit screen).
 */
import { createRoot } from '@wordpress/element';
import { readConfig } from '../shared/config';
import App from './App';
import '../admin/style.scss';

const root = document.getElementById( 'shapelms-builder' );

if ( root ) {
	const cfg = readConfig( root );
	createRoot( root ).render( <App courseId={ cfg.courseId } config={ cfg } /> );
}
