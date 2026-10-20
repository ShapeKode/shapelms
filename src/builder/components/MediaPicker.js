/**
 * Uses the WordPress media library (wp.media) to pick an attachment.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function openMedia( { title, type = 'image', multiple = false, onSelect } ) {
	if ( ! window.wp || ! window.wp.media ) {
		return;
	}
	const frame = window.wp.media( {
		title,
		multiple,
		library: type ? { type } : undefined,
		button: { text: __( 'Use this file', 'shapelms' ) },
	} );
	frame.on( 'select', () => {
		const selection = frame.state().get( 'selection' ).toJSON();
		onSelect( multiple ? selection : selection[ 0 ] );
	} );
	frame.open();
}

export default function MediaPicker( { label, value, previewUrl, onChange, type = 'image' } ) {
	return (
		<div className="shapelms-media-picker">
			<span className="shapelms-list-editor__label">{ label }</span>
			{ previewUrl && <img className="shapelms-media-picker__preview" src={ previewUrl } alt="" /> }
			<div className="shapelms-media-picker__actions">
				<Button variant="secondary" onClick={ () => openMedia( { title: label, type, onSelect: ( att ) => onChange( att.id, att.url ) } ) }>
					{ value ? __( 'Replace', 'shapelms' ) : __( 'Choose', 'shapelms' ) }
				</Button>
				{ value ? <Button variant="tertiary" isDestructive onClick={ () => onChange( 0, '' ) }>{ __( 'Remove', 'shapelms' ) }</Button> : null }
			</div>
		</div>
	);
}
