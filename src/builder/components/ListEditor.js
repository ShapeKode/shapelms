/**
 * Editable list of strings (requirements, outcomes, audience).
 */
import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ListEditor( { label, value = [], onChange, placeholder } ) {
	const items = value.length ? value : [ '' ];

	const set = ( index, text ) => {
		const next = [ ...items ];
		next[ index ] = text;
		onChange( next );
	};
	const remove = ( index ) => onChange( items.filter( ( _, i ) => i !== index ) );
	const add = () => onChange( [ ...items, '' ] );

	return (
		<div className="shapelms-list-editor">
			<span className="shapelms-list-editor__label">{ label }</span>
			{ items.map( ( item, i ) => (
				<div className="shapelms-list-editor__row" key={ i }>
					<TextControl value={ item } onChange={ ( v ) => set( i, v ) } placeholder={ placeholder } __nextHasNoMarginBottom />
					<Button icon="no-alt" label={ __( 'Remove', 'shapelms' ) } onClick={ () => remove( i ) } />
				</div>
			) ) }
			<Button variant="secondary" size="compact" onClick={ add }>{ __( 'Add item', 'shapelms' ) }</Button>
		</div>
	);
}
