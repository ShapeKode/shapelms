/**
 * Curriculum item types from PHP (lesson and quiz, plus any an add-on registers).
 */
import { __ } from '@wordpress/i18n';
import { config } from '../shared/config';

const fallback = {
	lesson: { label: __( 'Lesson', 'shapelms' ), icon: 'L', add_label: __( '+ Lesson', 'shapelms' ), edit_label: __( 'Edit lesson', 'shapelms' ) },
	quiz: { label: __( 'Quiz', 'shapelms' ), icon: 'Q', add_label: __( '+ Quiz', 'shapelms' ), edit_label: __( 'Edit quiz', 'shapelms' ) },
};

let cached = null;

export function itemTypes() {
	if ( cached ) {
		return cached;
	}
	let parsed = {};
	try {
		parsed = JSON.parse( config().itemTypes || '{}' ) || {};
	} catch ( e ) {
		parsed = {};
	}
	cached = { ...fallback, ...parsed };
	return cached;
}

export function itemType( type ) {
	const types = itemTypes();
	return types[ type ] || { label: type, icon: ( type || '?' ).charAt( 0 ).toUpperCase(), add_label: '+ ' + type, edit_label: type };
}
