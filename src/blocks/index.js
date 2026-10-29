/**
 * Block editor registrations. All blocks are dynamic (rendered by PHP) and
 * previewed with ServerSideRender.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, TextControl, ToggleControl, Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

const grid = ( props ) => {
	const { attributes, setAttributes } = props;
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Courses', 'shapelms' ) }>
					<RangeControl label={ __( 'Number of courses', 'shapelms' ) } value={ attributes.perPage } min={ 1 } max={ 48 } onChange={ ( perPage ) => setAttributes( { perPage } ) } />
					<RangeControl label={ __( 'Columns', 'shapelms' ) } value={ attributes.columns } min={ 1 } max={ 4 } onChange={ ( columns ) => setAttributes( { columns } ) } />
					<TextControl label={ __( 'Category slug', 'shapelms' ) } value={ attributes.category } onChange={ ( category ) => setAttributes( { category } ) } />
					<TextControl label={ __( 'Tag slug', 'shapelms' ) } value={ attributes.tag } onChange={ ( tag ) => setAttributes( { tag } ) } />
					<SelectControl
						label={ __( 'Order by', 'shapelms' ) }
						value={ attributes.orderBy }
						options={ [
							{ value: 'date', label: __( 'Newest', 'shapelms' ) },
							{ value: 'title', label: __( 'Title', 'shapelms' ) },
							{ value: 'price', label: __( 'Price', 'shapelms' ) },
							{ value: 'popular', label: __( 'Popular', 'shapelms' ) },
						] }
						onChange={ ( orderBy ) => setAttributes( { orderBy } ) }
					/>
					<SelectControl
						label={ __( 'Level', 'shapelms' ) }
						value={ attributes.level }
						options={ [
							{ value: '', label: __( 'Any', 'shapelms' ) },
							{ value: 'beginner', label: __( 'Beginner', 'shapelms' ) },
							{ value: 'intermediate', label: __( 'Intermediate', 'shapelms' ) },
							{ value: 'advanced', label: __( 'Advanced', 'shapelms' ) },
						] }
						onChange={ ( level ) => setAttributes( { level } ) }
					/>
					<SelectControl
						label={ __( 'Price', 'shapelms' ) }
						value={ attributes.price }
						options={ [
							{ value: '', label: __( 'Any', 'shapelms' ) },
							{ value: 'free', label: __( 'Free', 'shapelms' ) },
							{ value: 'paid', label: __( 'Paid', 'shapelms' ) },
						] }
						onChange={ ( price ) => setAttributes( { price } ) }
					/>
					<ToggleControl label={ __( 'Featured courses only', 'shapelms' ) } checked={ attributes.featured } onChange={ ( featured ) => setAttributes( { featured } ) } />
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block="shapelms/course-grid" attributes={ attributes } />
		</div>
	);
};

const courseIdEdit = ( blockName, title ) => ( props ) => {
	const { attributes, setAttributes } = props;
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Course', 'shapelms' ) }>
					<TextControl
						type="number"
						label={ __( 'Course ID (0 = current course)', 'shapelms' ) }
						value={ attributes.courseId }
						onChange={ ( value ) => setAttributes( { courseId: parseInt( value, 10 ) || 0 } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ attributes.courseId ? (
				<ServerSideRender block={ blockName } attributes={ attributes } />
			) : (
				<Placeholder label={ title } instructions={ __( 'Shows for the current course on course pages. Set a course ID to use it elsewhere.', 'shapelms' ) } />
			) }
		</div>
	);
};

const placeholderEdit = ( label, instructions ) => () => (
	<div { ...useBlockProps() }>
		<Placeholder label={ label } instructions={ instructions } />
	</div>
);

registerBlockType( 'shapelms/course-grid', { edit: grid, save: () => null } );
registerBlockType( 'shapelms/enroll-button', { edit: courseIdEdit( 'shapelms/enroll-button', __( 'Course Enroll Box', 'shapelms' ) ), save: () => null } );
registerBlockType( 'shapelms/curriculum', { edit: courseIdEdit( 'shapelms/curriculum', __( 'Course Curriculum', 'shapelms' ) ), save: () => null } );
registerBlockType( 'shapelms/dashboard', { edit: placeholderEdit( __( 'LMS Dashboard', 'shapelms' ), __( 'The student and instructor dashboard renders here on the front end.', 'shapelms' ) ), save: () => null } );
registerBlockType( 'shapelms/checkout', { edit: placeholderEdit( __( 'LMS Checkout', 'shapelms' ), __( 'The checkout renders here on the front end.', 'shapelms' ) ), save: () => null } );
registerBlockType( 'shapelms/instructor-registration', { edit: placeholderEdit( __( 'Instructor Registration', 'shapelms' ), __( 'The instructor application form renders here on the front end.', 'shapelms' ) ), save: () => null } );
registerBlockType( 'shapelms/login', { edit: placeholderEdit( __( 'LMS Sign In', 'shapelms' ), __( 'The login, lost password and reset password forms render here on the front end.', 'shapelms' ) ), save: () => null } );
registerBlockType( 'shapelms/register', { edit: placeholderEdit( __( 'LMS Sign Up', 'shapelms' ), __( 'The student registration form renders here on the front end.', 'shapelms' ) ), save: () => null } );
