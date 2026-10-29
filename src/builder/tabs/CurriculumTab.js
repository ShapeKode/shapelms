/**
 * Builder: curriculum with drag-and-drop topics and items.
 */
import { useState } from '@wordpress/element';
import { Button, TextControl, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { DndContext, PointerSensor, KeyboardSensor, useSensor, useSensors, closestCenter } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove, sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { post, patch, del, errorMessage } from '../../shared/api';
import { success, error as notifyError } from '../../admin/notices';
import ItemEditor from '../components/ItemEditor';
import { itemTypes, itemType } from '../itemTypes';

function Handle( { listeners, attributes } ) {
	return (
		<span className="shapelms-curriculum-builder__handle" { ...listeners } { ...attributes } aria-label={ __( 'Drag to reorder', 'shapelms' ) }>
			<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><circle cx="4" cy="3" r="1.3" fill="currentColor" /><circle cx="10" cy="3" r="1.3" fill="currentColor" /><circle cx="4" cy="7" r="1.3" fill="currentColor" /><circle cx="10" cy="7" r="1.3" fill="currentColor" /><circle cx="4" cy="11" r="1.3" fill="currentColor" /><circle cx="10" cy="11" r="1.3" fill="currentColor" /></svg>
		</span>
	);
}

function SortableItem( { item, onEdit, onDelete } ) {
	const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable( { id: `item-${ item.id }` } );
	const style = { transform: CSS.Transform.toString( transform ), transition };
	return (
		<div ref={ setNodeRef } style={ style } className={ `shapelms-curriculum-builder__item ${ isDragging ? 'is-dragging' : '' }` }>
			<Handle listeners={ listeners } attributes={ attributes } />
			<span className={ `shapelms-item-icon is-${ item.type }` } title={ itemType( item.type ).label }>{ itemType( item.type ).icon }</span>
			<button type="button" className="shapelms-curriculum-builder__item-title" onClick={ () => onEdit( item ) }>
				{ item.title }
				{ item.status === 'draft' && <span className="shapelms-badge is-neutral" style={ { marginLeft: 8 } }>{ __( 'Draft', 'shapelms' ) }</span> }
				{ item.preview && <span className="shapelms-badge is-info" style={ { marginLeft: 8 } }>{ __( 'Preview', 'shapelms' ) }</span> }
				{
					/**
					 * Extra badges after the item title. Add-ons append elements: return [ ...badges, myBadge ].
					 * Args: { item } (the curriculum item, including data added with the shapelms_curriculum_item PHP filter).
					 */
					applyFilters( 'shapelms.builder.itemBadges', [], { item } )
				}
			</button>
			<Button size="small" variant="tertiary" onClick={ () => onEdit( item ) }>{ __( 'Edit', 'shapelms' ) }</Button>
			<Button size="small" variant="tertiary" isDestructive icon="trash" label={ __( 'Delete', 'shapelms' ) } onClick={ () => onDelete( item ) } />
		</div>
	);
}

function SortableTopic( { topic, onRename, onDelete, onAddItem, onEditItem, onDeleteItem } ) {
	const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable( { id: `topic-${ topic.id }` } );
	const style = { transform: CSS.Transform.toString( transform ), transition };
	const [ adding, setAdding ] = useState( null );
	const [ title, setTitle ] = useState( '' );

	const submitAdd = () => {
		if ( ! title.trim() ) {
			return;
		}
		onAddItem( topic, adding, title.trim() );
		setTitle( '' );
		setAdding( null );
	};

	return (
		<div ref={ setNodeRef } style={ style } className={ `shapelms-curriculum-builder__topic ${ isDragging ? 'is-dragging' : '' }` }>
			<div className="shapelms-curriculum-builder__topic-head">
				<Handle listeners={ listeners } attributes={ attributes } />
				<input value={ topic.title } onChange={ ( e ) => onRename( topic, e.target.value ) } aria-label={ __( 'Topic title', 'shapelms' ) } />
				<span className="shapelms-muted">{ topic.items.length }</span>
				<Button size="small" variant="tertiary" isDestructive icon="trash" label={ __( 'Delete topic', 'shapelms' ) } onClick={ () => onDelete( topic ) } />
			</div>
			<div className="shapelms-curriculum-builder__items">
				<SortableContext items={ topic.items.map( ( i ) => `item-${ i.id }` ) } strategy={ verticalListSortingStrategy }>
					{ topic.items.map( ( item ) => (
						<SortableItem key={ item.id } item={ item } onEdit={ onEditItem } onDelete={ onDeleteItem } />
					) ) }
				</SortableContext>
				{ ! topic.items.length && <p className="shapelms-muted" style={ { margin: '4px 8px' } }>{ __( 'No lessons yet. Add one below.', 'shapelms' ) }</p> }
			</div>
			<div className="shapelms-curriculum-builder__add">
				{ adding ? (
					<>
						<TextControl value={ title } onChange={ setTitle } placeholder={ adding === 'quiz' ? __( 'Quiz title', 'shapelms' ) : ( adding === 'lesson' ? __( 'Lesson title', 'shapelms' ) : itemType( adding ).label ) } onKeyDown={ ( e ) => e.key === 'Enter' && submitAdd() } autoFocus __nextHasNoMarginBottom />
						<Button variant="primary" size="compact" onClick={ submitAdd }>{ __( 'Add', 'shapelms' ) }</Button>
						<Button variant="tertiary" size="compact" onClick={ () => setAdding( null ) }>{ __( 'Cancel', 'shapelms' ) }</Button>
					</>
				) : (
					<>
						{ Object.entries( itemTypes() ).map( ( [ type, info ] ) => (
							<Button key={ type } variant="secondary" size="compact" onClick={ () => setAdding( type ) }>{ info.add_label }</Button>
						) ) }
					</>
				) }
			</div>
		</div>
	);
}

export default function CurriculumTab( { course, setCourse } ) {
	const tree = course.curriculum || [];
	const [ newTopic, setNewTopic ] = useState( '' );
	const [ editing, setEditing ] = useState( null );
	const sensors = useSensors( useSensor( PointerSensor, { activationConstraint: { distance: 4 } } ), useSensor( KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates } ) );

	const setTree = ( next ) => setCourse( ( c ) => ( { ...c, curriculum: next } ) );

	const persistOrder = ( next ) => {
		post( 'curriculum/reorder', { course_id: course.id, tree: next.map( ( t ) => ( { id: t.id, items: t.items.map( ( i ) => i.id ) } ) ) } ).catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const addTopic = () => {
		if ( ! newTopic.trim() ) {
			return;
		}
		post( 'curriculum/topics', { course_id: course.id, title: newTopic.trim() } )
			.then( ( topic ) => {
				setTree( [ ...tree, topic ] );
				setNewTopic( '' );
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const renameTimers = {};
	const renameTopic = ( topic, title ) => {
		setTree( tree.map( ( t ) => ( t.id === topic.id ? { ...t, title } : t ) ) );
		clearTimeout( renameTimers[ topic.id ] );
		renameTimers[ topic.id ] = setTimeout( () => patch( `curriculum/topics/${ topic.id }`, { title } ).catch( ( e ) => notifyError( errorMessage( e ) ) ), 800 );
	};

	const deleteTopic = ( topic ) => {
		if ( ! window.confirm( __( 'Delete this topic and everything in it?', 'shapelms' ) ) ) {
			return;
		}
		del( `curriculum/topics/${ topic.id }` ).then( () => setTree( tree.filter( ( t ) => t.id !== topic.id ) ) ).catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const addItem = ( topic, type, title ) => {
		post( 'curriculum/items', { topic_id: topic.id, type, title } )
			.then( ( item ) => {
				setTree( tree.map( ( t ) => ( t.id === topic.id ? { ...t, items: [ ...t.items, item ] } : t ) ) );
				setEditing( item );
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const deleteItem = ( item ) => {
		if ( ! window.confirm( __( 'Delete this item? Student progress for it will be lost.', 'shapelms' ) ) ) {
			return;
		}
		del( `curriculum/items/${ item.id }` )
			.then( () => setTree( tree.map( ( t ) => ( { ...t, items: t.items.filter( ( i ) => i.id !== item.id ) } ) ) ) )
			.catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const onItemSaved = ( saved ) => {
		setTree( tree.map( ( t ) => ( { ...t, items: t.items.map( ( i ) => ( i.id === saved.id ? { ...i, ...saved } : i ) ) } ) ) );
	};

	const findTopicOf = ( itemId ) => tree.find( ( t ) => t.items.some( ( i ) => `item-${ i.id }` === itemId ) );

	const onDragEnd = ( { active, over } ) => {
		if ( ! over || active.id === over.id ) {
			return;
		}
		let next = tree;

		if ( String( active.id ).startsWith( 'topic-' ) ) {
			const from = tree.findIndex( ( t ) => `topic-${ t.id }` === active.id );
			const to = tree.findIndex( ( t ) => `topic-${ t.id }` === over.id );
			if ( from < 0 || to < 0 ) {
				return;
			}
			next = arrayMove( tree, from, to );
		} else {
			const fromTopic = findTopicOf( active.id );
			const toTopic = String( over.id ).startsWith( 'topic-' ) ? tree.find( ( t ) => `topic-${ t.id }` === over.id ) : findTopicOf( over.id );
			if ( ! fromTopic || ! toTopic ) {
				return;
			}
			const item = fromTopic.items.find( ( i ) => `item-${ i.id }` === active.id );
			if ( fromTopic.id === toTopic.id ) {
				const from = fromTopic.items.findIndex( ( i ) => `item-${ i.id }` === active.id );
				const to = fromTopic.items.findIndex( ( i ) => `item-${ i.id }` === over.id );
				next = tree.map( ( t ) => ( t.id === fromTopic.id ? { ...t, items: arrayMove( t.items, from, to ) } : t ) );
			} else {
				const toIndex = toTopic.items.findIndex( ( i ) => `item-${ i.id }` === over.id );
				next = tree.map( ( t ) => {
					if ( t.id === fromTopic.id ) {
						return { ...t, items: t.items.filter( ( i ) => i.id !== item.id ) };
					}
					if ( t.id === toTopic.id ) {
						const items = [ ...t.items ];
						items.splice( toIndex < 0 ? items.length : toIndex, 0, item );
						return { ...t, items };
					}
					return t;
				} );
			}
		}

		setTree( next );
		persistOrder( next );
	};

	return (
		<>
			<h2>{ __( 'Curriculum', 'shapelms' ) }</h2>
			<p className="shapelms-muted">{ __( 'Drag topics and lessons to reorder. Click a lesson or quiz to edit its content.', 'shapelms' ) }</p>

			<DndContext sensors={ sensors } collisionDetection={ closestCenter } onDragEnd={ onDragEnd }>
				<SortableContext items={ tree.map( ( t ) => `topic-${ t.id }` ) } strategy={ verticalListSortingStrategy }>
					<div className="shapelms-curriculum-builder">
						{ tree.map( ( topic ) => (
							<SortableTopic key={ topic.id } topic={ topic } onRename={ renameTopic } onDelete={ deleteTopic } onAddItem={ addItem } onEditItem={ setEditing } onDeleteItem={ deleteItem } />
						) ) }
					</div>
				</SortableContext>
			</DndContext>

			<div className="shapelms-curriculum-builder__add" style={ { padding: 0 } }>
				<TextControl value={ newTopic } onChange={ setNewTopic } placeholder={ __( 'New topic title, e.g. "Getting started"', 'shapelms' ) } onKeyDown={ ( e ) => e.key === 'Enter' && addTopic() } __nextHasNoMarginBottom />
				<Button variant="primary" onClick={ addTopic } disabled={ ! newTopic.trim() }>{ __( 'Add topic', 'shapelms' ) }</Button>
			</div>

			{ editing && (
				<Modal title={ itemType( editing.type ).edit_label } onRequestClose={ () => setEditing( null ) } className="shapelms-item-editor" size="large" shouldCloseOnClickOutside={ false }>
					<ItemEditor item={ editing } onSaved={ onItemSaved } onClose={ () => setEditing( null ) } />
				</Modal>
			) }
		</>
	);
}
