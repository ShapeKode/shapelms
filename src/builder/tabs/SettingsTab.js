/**
 * Builder: settings.
 */
import { useEffect, useState } from '@wordpress/element';
import { TextControl, ToggleControl, FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { get } from '../../shared/api';
import { useDebounce } from '../../shared/hooks';

export default function SettingsTab( { course, update, config } ) {
	const [ userSearch, setUserSearch ] = useState( '' );
	const [ users, setUsers ] = useState( [] );
	const [ selected, setSelected ] = useState( [] );
	const debounced = useDebounce( userSearch );

	useEffect( () => {
		const ids = course.meta.instructors || [];
		setSelected( ( course.instructors || [] ).filter( ( i ) => i && ids.includes( i.id ) ).map( ( i ) => ( { id: i.id, name: i.name } ) ) );
	}, [ course.instructors, course.meta.instructors ] );

	useEffect( () => {
		if ( debounced.length < 2 ) {
			return;
		}
		get( 'students/search-users', { search: debounced } ).then( setUsers );
	}, [ debounced ] );

	const hours = Math.floor( ( course.meta.duration || 0 ) / 3600 );
	const minutes = Math.floor( ( ( course.meta.duration || 0 ) % 3600 ) / 60 );

	return (
		<>
			<h2>{ __( 'Settings', 'shapelms' ) }</h2>

			<TextControl label={ __( 'URL slug', 'shapelms' ) } value={ course.slug || '' } onChange={ ( slug ) => update( { slug } ) } __nextHasNoMarginBottom />

			<div className="shapelms-list-editor">
				<span className="shapelms-list-editor__label">{ __( 'Total duration', 'shapelms' ) }</span>
				<div className="shapelms-builder__row">
					<TextControl type="number" label={ __( 'Hours', 'shapelms' ) } value={ hours } min={ 0 } onChange={ ( h ) => update( { meta: { duration: ( Number( h ) || 0 ) * 3600 + minutes * 60 } } ) } __nextHasNoMarginBottom />
					<TextControl type="number" label={ __( 'Minutes', 'shapelms' ) } value={ minutes } min={ 0 } max={ 59 } onChange={ ( m ) => update( { meta: { duration: hours * 3600 + ( Number( m ) || 0 ) * 60 } } ) } __nextHasNoMarginBottom />
				</div>
				<p className="shapelms-muted">{ __( 'Leave at 0 to add up the lesson video durations automatically.', 'shapelms' ) }</p>
			</div>

			<TextControl type="number" label={ __( 'Maximum students (0 = unlimited)', 'shapelms' ) } value={ course.meta.max_students || 0 } min={ 0 } onChange={ ( v ) => update( { meta: { max_students: Number( v ) || 0 } } ) } __nextHasNoMarginBottom />

			<ToggleControl label={ __( 'Featured course', 'shapelms' ) } help={ __( 'Featured courses can be shown with the Course Grid block.', 'shapelms' ) } checked={ !! course.meta.featured } onChange={ ( featured ) => update( { meta: { featured } } ) } __nextHasNoMarginBottom />

			{ config.canManage && (
				<div className="shapelms-list-editor">
					<FormTokenField
						label={ __( 'Co-instructors', 'shapelms' ) }
						value={ selected.map( ( u ) => u.name ) }
						suggestions={ users.map( ( u ) => `${ u.name } (${ u.email })` ) }
						onInputChange={ setUserSearch }
						onChange={ ( tokens ) => {
							const next = tokens.map( ( token ) => {
								const existing = selected.find( ( u ) => u.name === token );
								if ( existing ) {
									return existing;
								}
								const match = users.find( ( u ) => `${ u.name } (${ u.email })` === token );
								return match ? { id: match.id, name: match.name } : null;
							} ).filter( Boolean );
							setSelected( next );
							update( { meta: { instructors: next.map( ( u ) => u.id ) } } );
						} }
						__experimentalExpandOnFocus
						__nextHasNoMarginBottom
					/>
					<p className="shapelms-muted">{ __( 'Type at least two characters to search users. Co-instructors can edit this course and see its students.', 'shapelms' ) }</p>
				</div>
			) }
		</>
	);
}
