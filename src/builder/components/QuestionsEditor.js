/**
 * Questions editor inside the quiz editor.
 */
import { useState } from '@wordpress/element';
import { Button, TextControl, TextareaControl, SelectControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { post, patch, del, errorMessage } from '../../shared/api';
import { success, error as notifyError } from '../../admin/notices';

const newId = () => Math.random().toString( 36 ).slice( 2, 8 );

function OptionsEditor( { question, onChange } ) {
	const multi = question.type === 'multiple_choice';
	const options = question.options || [];
	const correct = question.correct || [];

	const setOption = ( index, text ) => {
		const next = options.map( ( o, i ) => ( i === index ? { ...o, text } : o ) );
		onChange( { options: next } );
	};
	const toggleCorrect = ( id ) => {
		if ( multi ) {
			onChange( { correct: correct.includes( id ) ? correct.filter( ( c ) => c !== id ) : [ ...correct, id ] } );
		} else {
			onChange( { correct: [ id ] } );
		}
	};
	const remove = ( index ) => {
		const id = options[ index ].id;
		onChange( { options: options.filter( ( _, i ) => i !== index ), correct: correct.filter( ( c ) => c !== id ) } );
	};

	return (
		<div className="shapelms-list-editor">
			<span className="shapelms-list-editor__label">{ __( 'Answer options (tick the correct ones)', 'shapelms' ) }</span>
			{ options.map( ( option, i ) => (
				<div className="shapelms-option-row" key={ option.id }>
					<input type={ multi ? 'checkbox' : 'radio' } checked={ correct.includes( option.id ) } onChange={ () => toggleCorrect( option.id ) } aria-label={ __( 'Correct', 'shapelms' ) } />
					<TextControl value={ option.text } onChange={ ( v ) => setOption( i, v ) } placeholder={ /* translators: %d: option number */ sprintf( __( 'Option %d', 'shapelms' ), i + 1 ) } __nextHasNoMarginBottom />
					<Button icon="no-alt" label={ __( 'Remove', 'shapelms' ) } onClick={ () => remove( i ) } />
				</div>
			) ) }
			<Button variant="secondary" size="compact" onClick={ () => onChange( { options: [ ...options, { id: newId(), text: '', image: 0 } ] } ) }>{ __( 'Add option', 'shapelms' ) }</Button>
			{ multi && <ToggleControl label={ __( 'Give partial credit', 'shapelms' ) } checked={ !! question.settings?.partial_credit } onChange={ ( partial_credit ) => onChange( { settings: { ...question.settings, partial_credit } } ) } __nextHasNoMarginBottom /> }
		</div>
	);
}

function QuestionForm( { question, types, onChange, onSave, onCancel, busy } ) {
	return (
		<div className="shapelms-questions__editor">
			<div className="shapelms-builder__row">
				<SelectControl label={ __( 'Question type', 'shapelms' ) } value={ question.type } options={ Object.entries( types ).map( ( [ value, label ] ) => ( { value, label } ) ) } onChange={ ( type ) => onChange( { type, correct: type === 'true_false' ? [ 'true' ] : [] } ) } __nextHasNoMarginBottom />
				<TextControl type="number" label={ __( 'Points', 'shapelms' ) } value={ question.points } min={ 0 } step={ 0.5 } onChange={ ( v ) => onChange( { points: Number( v ) || 0 } ) } __nextHasNoMarginBottom />
			</div>

			<TextControl label={ __( 'Question', 'shapelms' ) } value={ question.title } onChange={ ( title ) => onChange( { title } ) } __nextHasNoMarginBottom />

			{ question.type === 'fill_blank' ? (
				<TextareaControl label={ __( 'Text with blanks', 'shapelms' ) } help={ __( 'Write {blank} where a gap should appear. Then list one accepted answer per blank below; separate alternatives with |.', 'shapelms' ) } value={ question.description || '' } onChange={ ( description ) => onChange( { description } ) } rows={ 3 } __nextHasNoMarginBottom />
			) : (
				<TextareaControl label={ __( 'Extra details (optional)', 'shapelms' ) } value={ question.description || '' } onChange={ ( description ) => onChange( { description } ) } rows={ 2 } __nextHasNoMarginBottom />
			) }

			{ [ 'single_choice', 'multiple_choice' ].includes( question.type ) && <OptionsEditor question={ question } onChange={ onChange } /> }

			{ question.type === 'true_false' && (
				<SelectControl label={ __( 'Correct answer', 'shapelms' ) } value={ question.correct?.[ 0 ] || 'true' } options={ [ { value: 'true', label: __( 'True', 'shapelms' ) }, { value: 'false', label: __( 'False', 'shapelms' ) } ] } onChange={ ( v ) => onChange( { correct: [ v ] } ) } __nextHasNoMarginBottom />
			) }

			{ question.type === 'short_answer' && (
				<>
					<TextareaControl label={ __( 'Accepted answers (one per line)', 'shapelms' ) } value={ ( question.correct || [] ).join( '\n' ) } onChange={ ( v ) => onChange( { correct: v.split( '\n' ).map( ( s ) => s.trim() ).filter( Boolean ) } ) } rows={ 3 } __nextHasNoMarginBottom />
					<ToggleControl label={ __( 'Case sensitive', 'shapelms' ) } checked={ !! question.settings?.case_sensitive } onChange={ ( case_sensitive ) => onChange( { settings: { ...question.settings, case_sensitive } } ) } __nextHasNoMarginBottom />
				</>
			) }

			{ question.type === 'fill_blank' && (
				<>
					<TextareaControl label={ __( 'Answers, one line per blank (alternatives separated by |)', 'shapelms' ) } value={ ( question.correct || [] ).join( '\n' ) } onChange={ ( v ) => onChange( { correct: v.split( '\n' ).map( ( s ) => s.trim() ) } ) } rows={ 3 } __nextHasNoMarginBottom />
					<ToggleControl label={ __( 'Case sensitive', 'shapelms' ) } checked={ !! question.settings?.case_sensitive } onChange={ ( case_sensitive ) => onChange( { settings: { ...question.settings, case_sensitive } } ) } __nextHasNoMarginBottom />
				</>
			) }

			<TextareaControl label={ __( 'Explanation (shown after answering)', 'shapelms' ) } value={ question.explanation || '' } onChange={ ( explanation ) => onChange( { explanation } ) } rows={ 2 } __nextHasNoMarginBottom />

			<div className="shapelms-modal-actions">
				<Button variant="tertiary" onClick={ onCancel }>{ __( 'Cancel', 'shapelms' ) }</Button>
				<Button variant="primary" isBusy={ busy } onClick={ onSave }>{ __( 'Save question', 'shapelms' ) }</Button>
			</div>
		</div>
	);
}

export default function QuestionsEditor( { quizId, initial, types } ) {
	const [ questions, setQuestions ] = useState( initial );
	const [ editing, setEditing ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const add = () => {
		post( 'questions', { quiz_id: quizId, title: __( 'New question', 'shapelms' ), type: 'single_choice' } )
			.then( ( q ) => {
				const withOptions = { ...q, options: [ { id: newId(), text: '', image: 0 }, { id: newId(), text: '', image: 0 } ] };
				setQuestions( [ ...questions, withOptions ] );
				setEditing( withOptions );
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const save = () => {
		setBusy( true );
		const { id, ...fields } = editing;
		patch( `questions/${ id }`, fields )
			.then( ( saved ) => {
				setQuestions( questions.map( ( q ) => ( q.id === saved.id ? saved : q ) ) );
				setEditing( null );
				success( __( 'Question saved.', 'shapelms' ) );
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const remove = ( q ) => {
		if ( ! window.confirm( __( 'Delete this question?', 'shapelms' ) ) ) {
			return;
		}
		del( `questions/${ q.id }` ).then( () => setQuestions( questions.filter( ( x ) => x.id !== q.id ) ) ).catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const move = ( index, dir ) => {
		const next = [ ...questions ];
		const target = index + dir;
		if ( target < 0 || target >= next.length ) {
			return;
		}
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		setQuestions( next );
		post( 'questions/reorder', { quiz_id: quizId, ids: next.map( ( q ) => q.id ) } ).catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	return (
		<div className="shapelms-questions shapelms-list-editor">
			<span className="shapelms-list-editor__label">{ /* translators: %d: number of questions */ sprintf( __( 'Questions (%d)', 'shapelms' ), questions.length ) }</span>
			{ questions.map( ( q, i ) => (
				<div key={ q.id } className={ `shapelms-questions__item ${ editing?.id === q.id ? 'is-active' : '' }` }>
					<span className="shapelms-muted">{ i + 1 }.</span>
					<button type="button" className="shapelms-curriculum-builder__item-title" onClick={ () => setEditing( q ) }>{ q.title || __( '(untitled)', 'shapelms' ) }</button>
					<span className="shapelms-badge is-neutral">{ types[ q.type ] || q.type }</span>
					<span className="shapelms-muted">{ q.points } { __( 'pts', 'shapelms' ) }</span>
					<Button size="small" variant="tertiary" icon="arrow-up-alt2" label={ __( 'Move up', 'shapelms' ) } onClick={ () => move( i, -1 ) } disabled={ i === 0 } />
					<Button size="small" variant="tertiary" icon="arrow-down-alt2" label={ __( 'Move down', 'shapelms' ) } onClick={ () => move( i, 1 ) } disabled={ i === questions.length - 1 } />
					<Button size="small" variant="tertiary" isDestructive icon="trash" label={ __( 'Delete', 'shapelms' ) } onClick={ () => remove( q ) } />
				</div>
			) ) }
			<Button variant="secondary" size="compact" onClick={ add }>{ __( 'Add question', 'shapelms' ) }</Button>

			{ editing && <QuestionForm question={ editing } types={ types } onChange={ ( changes ) => setEditing( { ...editing, ...changes } ) } onSave={ save } onCancel={ () => setEditing( null ) } busy={ busy } /> }
		</div>
	);
}
