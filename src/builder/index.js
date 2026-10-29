/**
 * Course builder entry (mounted on the course edit screen).
 */
import { createRoot } from '@wordpress/element';
import { readConfig } from '../shared/config';
import App from './App';
import QuestionsEditor, { QuestionForm } from './components/QuestionsEditor';
import '../admin/style.scss';

// Builder pieces add-on screens can reuse (for example a question bank).
window.shapelmsBuilder = { QuestionForm, QuestionsEditor };

const root = document.getElementById( 'shapelms-builder' );

if ( root ) {
	const cfg = readConfig( root );
	createRoot( root ).render( <App courseId={ cfg.courseId } config={ cfg } /> );
}
