/**
 * Builder: basics.
 */
import { useEffect, useState } from '@wordpress/element';
import { TextareaControl, TextControl, SelectControl, FormTokenField, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { get } from '../../shared/api';
import ClassicEditor from '../components/ClassicEditor';
import MediaPicker from '../components/MediaPicker';
import ListEditor from '../components/ListEditor';

export default function BasicsTab( { course, update } ) {
	const [ taxonomies, setTaxonomies ] = useState( { categories: [], tags: [] } );

	useEffect( () => {
		get( 'courses/taxonomies' ).then( setTaxonomies );
	}, [] );

	return (
		<>
			<h2>{ __( 'Course basics', 'shapelms' ) }</h2>

			<TextareaControl label={ __( 'Short description', 'shapelms' ) } help={ __( 'Shown on course cards and under the title.', 'shapelms' ) } value={ course.excerpt || '' } onChange={ ( excerpt ) => update( { excerpt } ) } rows={ 3 } __nextHasNoMarginBottom />

			<div className="shapelms-list-editor">
				<span className="shapelms-list-editor__label">{ __( 'Description', 'shapelms' ) }</span>
				<ClassicEditor value={ course.content || '' } onChange={ ( content ) => update( { content } ) } />
			</div>

			<div className="shapelms-builder__row">
				<MediaPicker label={ __( 'Course image', 'shapelms' ) } value={ course.image_id } previewUrl={ course.image } onChange={ ( id, url ) => update( { image_id: id, image: url } ) } />
				<TextControl label={ __( 'Intro video URL', 'shapelms' ) } help={ __( 'YouTube or Vimeo link shown in the enroll box.', 'shapelms' ) } value={ course.meta.intro_video || '' } onChange={ ( intro_video ) => update( { meta: { intro_video } } ) } __nextHasNoMarginBottom />
			</div>

			<div className="shapelms-builder__row">
				<div className="shapelms-list-editor">
					<span className="shapelms-list-editor__label">{ __( 'Categories', 'shapelms' ) }</span>
					{ taxonomies.categories.map( ( term ) => (
						<CheckboxControl
							key={ term.id }
							label={ term.name }
							checked={ ( course.category_ids || [] ).includes( term.id ) }
							onChange={ ( checked ) => {
								const current = course.category_ids || [];
								update( { categories: checked ? [ ...current, term.id ] : current.filter( ( id ) => id !== term.id ) } );
							} }
							__nextHasNoMarginBottom
						/>
					) ) }
					{ ! taxonomies.categories.length && <p className="shapelms-muted">{ __( 'No categories yet. Add some under ShapeLMS → Categories.', 'shapelms' ) }</p> }
				</div>
				<FormTokenField label={ __( 'Tags', 'shapelms' ) } value={ course.tag_names || [] } suggestions={ taxonomies.tags.map( ( t ) => t.name ) } onChange={ ( tags ) => update( { tags } ) } __nextHasNoMarginBottom />
			</div>

			<SelectControl
				label={ __( 'Level', 'shapelms' ) }
				value={ course.meta.level || 'all' }
				options={ [
					{ value: 'all', label: __( 'All levels', 'shapelms' ) },
					{ value: 'beginner', label: __( 'Beginner', 'shapelms' ) },
					{ value: 'intermediate', label: __( 'Intermediate', 'shapelms' ) },
					{ value: 'advanced', label: __( 'Advanced', 'shapelms' ) },
				] }
				onChange={ ( level ) => update( { meta: { level } } ) }
				__nextHasNoMarginBottom
			/>

			<ListEditor label={ __( "What students will learn", 'shapelms' ) } value={ course.meta.outcomes || [] } onChange={ ( outcomes ) => update( { meta: { outcomes } } ) } placeholder={ __( 'e.g. Build a responsive layout with CSS Grid', 'shapelms' ) } />
			<ListEditor label={ __( 'Requirements', 'shapelms' ) } value={ course.meta.requirements || [] } onChange={ ( requirements ) => update( { meta: { requirements } } ) } placeholder={ __( 'e.g. Basic HTML knowledge', 'shapelms' ) } />
			<ListEditor label={ __( 'Who this course is for', 'shapelms' ) } value={ course.meta.audience || [] } onChange={ ( audience ) => update( { meta: { audience } } ) } placeholder={ __( 'e.g. Designers moving into front-end', 'shapelms' ) } />
		</>
	);
}
