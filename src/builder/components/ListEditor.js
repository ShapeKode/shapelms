/**
 * Editable list of strings (requirements, outcomes, audience).
 *
 * Rows live in local state so an empty row stays open while the user types; only filled
 * rows are passed to onChange (the server drops empty strings anyway).
 */
import { Button, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const filled = ( list ) => list.filter( ( item ) => item.trim() !== '' );
// Compare trimmed: the server trims each item, which must not reset a row mid-typing.
const same = ( a, b ) => a.length === b.length && a.every( ( item, i ) => item.trim() === String( b[ i ] ).trim() );

export default function ListEditor( { label, value = [], onChange, placeholder } ) {
	const [ rows, setRows ] = useState( () => ( value.length ? value : [ '' ] ) );

	// Take outside changes (load, server response) unless they only differ by empty rows.
	useEffect( () => {
		setRows( ( current ) => ( same( filled( current ), value ) ? current : ( value.length ? value : [ '' ] ) ) );
	}, [ value ] );

	const commit = ( next ) => {
		setRows( next.length ? next : [ '' ] );
		if ( ! same( filled( next ), value ) ) {
			onChange( filled( next ) );
		}
	};

	const set = ( index, text ) => commit( rows.map( ( item, i ) => ( i === index ? text : item ) ) );
	const remove = ( index ) => commit( rows.filter( ( _, i ) => i !== index ) );
	const add = () => setRows( [ ...rows, '' ] );

	return (
		<div className="shapelms-list-editor">
			<span className="shapelms-list-editor__label">{ label }</span>
			{ rows.map( ( item, i ) => (
				<div className="shapelms-list-editor__row" key={ i }>
					<TextControl value={ item } onChange={ ( v ) => set( i, v ) } placeholder={ placeholder } __nextHasNoMarginBottom />
					<Button icon="no-alt" label={ __( 'Remove', 'shapelms' ) } onClick={ () => remove( i ) } />
				</div>
			) ) }
			<Button variant="secondary" size="compact" onClick={ add }>{ __( 'Add item', 'shapelms' ) }</Button>
		</div>
	);
}
