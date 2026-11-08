/**
 * Builder: settings.
 */
import { useEffect, useState } from '@wordpress/element';
import { TextControl, ToggleControl, FormTokenField, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { get, list } from '../../shared/api';
import { useDebounce } from '../../shared/hooks';
import SchedulePanel from '../components/SchedulePanel';

export default function SettingsTab( { course, update, config, scheduleDraft, setScheduleDraft, applySchedule } ) {
	const [ userSearch, setUserSearch ] = useState( '' );
	const [ users, setUsers ] = useState( [] );
	const [ selected, setSelected ] = useState( [] );
	const debounced = useDebounce( userSearch );
	const [ courseSearch, setCourseSearch ] = useState( '' );
	const [ courses, setCourses ] = useState( [] );
	const [ prereqs, setPrereqs ] = useState( [] );
	const debouncedCourse = useDebounce( courseSearch );
	const [ choosingPassword, setChoosingPassword ] = useState( false );
	const [ password, setPassword ] = useState( course.password || '' );

	useEffect( () => {
		const ids = course.meta.prerequisites || [];
		setPrereqs( ( course.prerequisites || [] ).filter( ( c ) => c && ids.includes( c.id ) ).map( ( c ) => ( { id: c.id, title: c.title } ) ) );
	}, [ course.prerequisites, course.meta.prerequisites ] );

	useEffect( () => {
		if ( debouncedCourse.length < 2 ) {
			return;
		}
		list( 'courses', { search: debouncedCourse, per_page: 10 } ).then( ( res ) => setCourses( res.items.filter( ( c ) => c.id !== course.id ) ) );
	}, [ debouncedCourse, course.id ] );

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

	const visibility = choosingPassword ? 'password' : course.visibility || 'public';
	const visibilityOptions = [
		{ value: 'public', label: __( 'Public', 'shapelms' ) },
		{ value: 'password', label: __( 'Password protected', 'shapelms' ) },
	];
	if ( course.can_publish || course.status === 'private' ) {
		visibilityOptions.push( { value: 'private', label: __( 'Private', 'shapelms' ) } );
	}
	const visibilityHelp = {
		public: course.status === 'private' ? __( 'Anyone can see the course once it is published. It moves to draft until you publish it again.', 'shapelms' ) : __( 'Anyone can see the course once it is published.', 'shapelms' ),
		password: __( 'Listed in the catalog, but visitors need the password to see the details and to enroll or buy. Enrolled students never need it.', 'shapelms' ),
		private: __( 'Hidden from the catalog right away. Only admins, its instructors and enrolled students can open it. Add students manually.', 'shapelms' ),
	};

	// Same rule as the server: a paid course or a hidden one cannot be public.
	const isPaid = course.meta.price_type === 'paid' && Number( course.meta.price ) > 0;
	const canBePublic = ! isPaid && visibility === 'public';
	let publicHelp = __( 'Anyone can read the lessons without enrolling or logging in. Taking quizzes and saving progress still need enrollment.', 'shapelms' );
	if ( isPaid ) {
		publicHelp = __( 'Paid courses cannot be public.', 'shapelms' );
	} else if ( visibility !== 'public' ) {
		publicHelp = __( 'Only available when Visibility is set to Public.', 'shapelms' );
	}

	// The password is only sent once it has a value: an empty one must not lift the protection.
	const changeVisibility = ( next ) => {
		if ( next === 'password' && ! password.trim() ) {
			setChoosingPassword( true );
			return;
		}
		setChoosingPassword( false );
		update( next === 'password' ? { visibility: next, password } : { visibility: next } );
	};
	const changePassword = ( next ) => {
		setPassword( next );
		if ( next.trim() ) {
			setChoosingPassword( false );
			update( { visibility: 'password', password: next } );
		}
	};

	return (
		<>
			<h2>{ __( 'Settings', 'shapelms' ) }</h2>

			<TextControl label={ __( 'URL slug', 'shapelms' ) } value={ course.slug || '' } onChange={ ( slug ) => update( { slug } ) } __nextHasNoMarginBottom />

			{ course.can_change_status !== false && (
				<div className="shapelms-list-editor">
					<SelectControl label={ __( 'Visibility', 'shapelms' ) } help={ visibilityHelp[ visibility ] } value={ visibility } options={ visibilityOptions } onChange={ changeVisibility } __nextHasNoMarginBottom />
					{ visibility === 'password' && (
						<TextControl
							label={ __( 'Course password', 'shapelms' ) }
							help={ password.trim() ? '' : __( 'Enter a password. The course keeps its current visibility until you do.', 'shapelms' ) }
							value={ password }
							maxLength={ 255 }
							autoComplete="off"
							onChange={ changePassword }
							__nextHasNoMarginBottom
						/>
					) }
				</div>
			) }

			<ToggleControl label={ __( 'Public course', 'shapelms' ) } help={ publicHelp } checked={ canBePublic && !! course.meta.public } disabled={ ! canBePublic } onChange={ ( next ) => update( { meta: { public: next } } ) } __nextHasNoMarginBottom />

			{ course.can_change_status !== false && course.can_publish && course.status !== 'private' && (
				<SchedulePanel course={ course } draft={ scheduleDraft } setDraft={ setScheduleDraft } apply={ applySchedule } />
			) }

			<div className="shapelms-list-editor">
				<span className="shapelms-list-editor__label">{ __( 'Total duration', 'shapelms' ) }</span>
				<div className="shapelms-builder__row">
					<TextControl type="number" label={ __( 'Hours', 'shapelms' ) } value={ hours } min={ 0 } onChange={ ( h ) => update( { meta: { duration: ( Number( h ) || 0 ) * 3600 + minutes * 60 } } ) } __nextHasNoMarginBottom />
					<TextControl type="number" label={ __( 'Minutes', 'shapelms' ) } value={ minutes } min={ 0 } max={ 59 } onChange={ ( m ) => update( { meta: { duration: hours * 3600 + ( Number( m ) || 0 ) * 60 } } ) } __nextHasNoMarginBottom />
				</div>
				<p className="shapelms-muted">{ __( 'Leave at 0 to add up the lesson video durations automatically.', 'shapelms' ) }</p>
			</div>

			<TextControl type="number" label={ __( 'Maximum students (0 = unlimited)', 'shapelms' ) } value={ course.meta.max_students || 0 } min={ 0 } onChange={ ( v ) => update( { meta: { max_students: Number( v ) || 0 } } ) } __nextHasNoMarginBottom />

			<SelectControl
				label={ __( 'Course progression', 'shapelms' ) }
				help={ __( 'Step by step locks each lesson and quiz until the previous one is completed.', 'shapelms' ) }
				value={ course.meta.progression || 'default' }
				options={ [
					{ value: 'default', label: config.sequentialDefault ? __( 'Site default (step by step)', 'shapelms' ) : __( 'Site default (free order)', 'shapelms' ) },
					{ value: 'linear', label: __( 'Step by step', 'shapelms' ) },
					{ value: 'free', label: __( 'Free order', 'shapelms' ) },
				] }
				onChange={ ( progression ) => update( { meta: { progression } } ) }
				__nextHasNoMarginBottom
			/>

			<div className="shapelms-list-editor">
				<FormTokenField
					label={ __( 'Prerequisite courses', 'shapelms' ) }
					value={ prereqs.map( ( c ) => c.title ) }
					suggestions={ courses.map( ( c ) => c.title ) }
					onInputChange={ setCourseSearch }
					onChange={ ( tokens ) => {
						const next = tokens.map( ( token ) => {
							const existing = prereqs.find( ( c ) => c.title === token );
							if ( existing ) {
								return existing;
							}
							const match = courses.find( ( c ) => c.title === token );
							return match ? { id: match.id, title: match.title } : null;
						} ).filter( Boolean );
						setPrereqs( next );
						update( { meta: { prerequisites: next.map( ( c ) => c.id ) } } );
					} }
					__experimentalExpandOnFocus
					__nextHasNoMarginBottom
				/>
				<p className="shapelms-muted">{ __( 'Students must complete these courses before they can enroll. Type at least two characters to search.', 'shapelms' ) }</p>
			</div>

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

			{
				/**
				 * Extra course settings. Add-ons append elements: return [ ...panels, myPanel ].
				 * Args: { course, update, config }. A key passed to update( { meta: { key } } ) is saved only
				 * when it is registered for courses through the shapelms_meta_definitions PHP filter.
				 */
				applyFilters( 'shapelms.builder.courseSettings', [], { course, update, config } )
			}
		</>
	);
}
