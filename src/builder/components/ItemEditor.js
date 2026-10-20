/**
 * Lesson / quiz editor (modal body).
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, TextControl, TextareaControl, SelectControl, ToggleControl, __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { get, patch, errorMessage } from '../../shared/api';
import { Loading } from '../../shared/components';
import { success, error as notifyError } from '../../admin/notices';
import ClassicEditor from './ClassicEditor';
import { openMedia } from './MediaPicker';
import QuestionsEditor from './QuestionsEditor';

export default function ItemEditor( { item, onSaved, onClose } ) {
	const [ data, setData ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const pending = useRef( {} );

	useEffect( () => {
		get( `curriculum/items/${ item.id }` ).then( setData ).catch( ( e ) => notifyError( errorMessage( e ) ) );
	}, [ item.id ] );

	const change = ( changes ) => {
		setData( ( d ) => ( { ...d, ...changes, meta: { ...d.meta, ...( changes.meta || {} ) } } ) );
		pending.current = { ...pending.current, ...changes, meta: { ...( pending.current.meta || {} ), ...( changes.meta || {} ) } };
	};

	const save = ( close ) => {
		setBusy( true );
		patch( `curriculum/items/${ item.id }`, pending.current )
			.then( ( saved ) => {
				pending.current = {};
				setData( saved );
				onSaved( saved );
				success( __( 'Saved.', 'shapelms' ) );
				if ( close ) {
					onClose();
				}
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( ! data ) {
		return <Loading />;
	}

	const isQuiz = data.type === 'quiz';
	const meta = data.meta || {};

	return (
		<>
			<div className="shapelms-item-editor__layout">
				<div>
					<TextControl label={ __( 'Title', 'shapelms' ) } value={ data.title } onChange={ ( title ) => change( { title } ) } __nextHasNoMarginBottom />

					{ ! isQuiz && (
						<>
							<SelectControl
								label={ __( 'Video', 'shapelms' ) }
								value={ meta.video_source || 'none' }
								options={ [
									{ value: 'none', label: __( 'No video', 'shapelms' ) },
									{ value: 'youtube', label: __( 'YouTube', 'shapelms' ) },
									{ value: 'vimeo', label: __( 'Vimeo', 'shapelms' ) },
									{ value: 'html5', label: __( 'Self-hosted (MP4)', 'shapelms' ) },
									{ value: 'embed', label: __( 'Embed code', 'shapelms' ) },
								] }
								onChange={ ( video_source ) => change( { meta: { video_source } } ) }
								__nextHasNoMarginBottom
							/>
							{ [ 'youtube', 'vimeo' ].includes( meta.video_source ) && (
								<TextControl label={ __( 'Video URL', 'shapelms' ) } value={ meta.video_url || '' } onChange={ ( video_url ) => change( { meta: { video_url } } ) } placeholder="https://" __nextHasNoMarginBottom />
							) }
							{ meta.video_source === 'html5' && (
								<div className="shapelms-list-editor">
									<TextControl label={ __( 'Video file URL', 'shapelms' ) } value={ meta.video_url || '' } onChange={ ( video_url ) => change( { meta: { video_url } } ) } __nextHasNoMarginBottom />
									<Button variant="secondary" size="compact" onClick={ () => openMedia( { title: __( 'Choose video', 'shapelms' ), type: 'video', onSelect: ( att ) => change( { meta: { video_url: att.url, video_duration: Math.round( att.fileLength || meta.video_duration || 0 ) } } ) } ) }>{ __( 'Choose from media library', 'shapelms' ) }</Button>
								</div>
							) }
							{ meta.video_source === 'embed' && (
								<TextareaControl label={ __( 'Embed code (iframe)', 'shapelms' ) } help={ __( 'Only iframes from known video hosts are kept.', 'shapelms' ) } value={ meta.video_embed || '' } onChange={ ( video_embed ) => change( { meta: { video_embed } } ) } rows={ 4 } __nextHasNoMarginBottom />
							) }
						</>
					) }

					<div className="shapelms-list-editor">
						<span className="shapelms-list-editor__label">{ isQuiz ? __( 'Instructions', 'shapelms' ) : __( 'Lesson content', 'shapelms' ) }</span>
						<ClassicEditor value={ data.content || '' } onChange={ ( content ) => change( { content } ) } height={ isQuiz ? 160 : 320 } />
					</div>

					{ ! isQuiz && (
						<div className="shapelms-attachments-editor shapelms-list-editor">
							<span className="shapelms-list-editor__label">{ __( 'Attachments', 'shapelms' ) }</span>
							<ul>
								{ ( data.attachment_files || [] ).map( ( file ) => (
									<li key={ file.id }>
										<span>{ file.title } <span className="shapelms-muted">{ file.size }</span></span>
										<Button size="small" variant="tertiary" isDestructive onClick={ () => {
											const attachments = ( meta.attachments || [] ).filter( ( id ) => id !== file.id );
											change( { meta: { attachments } } );
											setData( ( d ) => ( { ...d, attachment_files: d.attachment_files.filter( ( f ) => f.id !== file.id ) } ) );
										} }>{ __( 'Remove', 'shapelms' ) }</Button>
									</li>
								) ) }
							</ul>
							<Button variant="secondary" size="compact" onClick={ () => openMedia( { title: __( 'Add attachments', 'shapelms' ), type: '', multiple: true, onSelect: ( files ) => {
								const ids = [ ...( meta.attachments || [] ) ];
								const list = [ ...( data.attachment_files || [] ) ];
								files.forEach( ( f ) => {
									if ( ! ids.includes( f.id ) ) {
										ids.push( f.id );
										list.push( { id: f.id, title: f.title, url: f.url, size: f.filesizeHumanReadable || '' } );
									}
								} );
								change( { meta: { attachments: ids } } );
								setData( ( d ) => ( { ...d, attachment_files: list } ) );
							} } ) }>{ __( 'Add files', 'shapelms' ) }</Button>
						</div>
					) }

					{ isQuiz && <QuestionsEditor quizId={ data.id } initial={ data.questions || [] } types={ data.question_types || {} } /> }
				</div>

				<aside className="shapelms-item-editor__side">
					<SelectControl label={ __( 'Status', 'shapelms' ) } value={ data.status } options={ [ { value: 'publish', label: __( 'Published', 'shapelms' ) }, { value: 'draft', label: __( 'Draft (hidden)', 'shapelms' ) } ] } onChange={ ( status ) => change( { status } ) } __nextHasNoMarginBottom />

					{ ! isQuiz && (
						<>
							<NumberControl label={ __( 'Duration (minutes)', 'shapelms' ) } value={ Math.round( ( meta.video_duration || 0 ) / 60 ) } min={ 0 } onChange={ ( v ) => change( { meta: { video_duration: ( Number( v ) || 0 ) * 60 } } ) } __nextHasNoMarginBottom />
							<ToggleControl label={ __( 'Free preview', 'shapelms' ) } help={ __( 'Anyone can open this lesson without enrolling.', 'shapelms' ) } checked={ !! meta.preview } onChange={ ( preview ) => change( { meta: { preview } } ) } __nextHasNoMarginBottom />
						</>
					) }

					{ isQuiz && (
						<>
							<NumberControl label={ __( 'Time limit (minutes, 0 = none)', 'shapelms' ) } value={ meta.time_limit || 0 } min={ 0 } onChange={ ( v ) => change( { meta: { time_limit: Number( v ) || 0 } } ) } __nextHasNoMarginBottom />
							<NumberControl label={ __( 'Attempts allowed (0 = unlimited)', 'shapelms' ) } value={ meta.attempts_allowed || 0 } min={ 0 } onChange={ ( v ) => change( { meta: { attempts_allowed: Number( v ) || 0 } } ) } __nextHasNoMarginBottom />
							<NumberControl label={ __( 'Pass mark (%)', 'shapelms' ) } value={ meta.pass_mark ?? 70 } min={ 0 } max={ 100 } onChange={ ( v ) => change( { meta: { pass_mark: Number( v ) || 0 } } ) } __nextHasNoMarginBottom />
							<NumberControl label={ __( 'Questions per page (0 = all)', 'shapelms' ) } value={ meta.questions_per_page || 0 } min={ 0 } onChange={ ( v ) => change( { meta: { questions_per_page: Number( v ) || 0 } } ) } __nextHasNoMarginBottom />
							<SelectControl label={ __( 'Feedback', 'shapelms' ) } value={ meta.feedback_mode || 'end' } options={ [ { value: 'instant', label: __( 'After each answer', 'shapelms' ) }, { value: 'end', label: __( 'At the end', 'shapelms' ) }, { value: 'none', label: __( 'Score only', 'shapelms' ) } ] } onChange={ ( feedback_mode ) => change( { meta: { feedback_mode } } ) } __nextHasNoMarginBottom />
							<ToggleControl label={ __( 'Randomize question order', 'shapelms' ) } checked={ !! meta.randomize } onChange={ ( randomize ) => change( { meta: { randomize } } ) } __nextHasNoMarginBottom />
							<ToggleControl label={ __( 'Show correct answers in results', 'shapelms' ) } checked={ meta.show_answers !== false && meta.show_answers !== '' } onChange={ ( show_answers ) => change( { meta: { show_answers } } ) } __nextHasNoMarginBottom />
						</>
					) }
				</aside>
			</div>

			<div className="shapelms-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Close', 'shapelms' ) }</Button>
				<Button variant="secondary" isBusy={ busy } onClick={ () => save( false ) }>{ __( 'Save', 'shapelms' ) }</Button>
				<Button variant="primary" isBusy={ busy } onClick={ () => save( true ) }>{ __( 'Save & close', 'shapelms' ) }</Button>
			</div>
		</>
	);
}
