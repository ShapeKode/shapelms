/**
 * Course builder: tabs, autosave, publish.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { Button, SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { get, patch, errorMessage } from '../shared/api';
import { Loading, ErrorNotice } from '../shared/components';
import { success, error as notifyError } from '../admin/notices';
import BasicsTab from './tabs/BasicsTab';
import CurriculumTab from './tabs/CurriculumTab';
import SettingsTab from './tabs/SettingsTab';
import PricingTab from './tabs/PricingTab';

const coreTabs = [
	{ id: 'basics', label: __( 'Basics', 'shapelms' ), Component: BasicsTab },
	{ id: 'curriculum', label: __( 'Curriculum', 'shapelms' ), Component: CurriculumTab },
	{ id: 'settings', label: __( 'Settings', 'shapelms' ), Component: SettingsTab },
	{ id: 'pricing', label: __( 'Pricing', 'shapelms' ), Component: PricingTab },
];

function Snackbars() {
	const notices = useSelect( ( select ) => select( noticesStore ).getNotices(), [] );
	const { removeNotice } = useDispatch( noticesStore );
	return <SnackbarList notices={ notices.filter( ( n ) => n.type === 'snackbar' ) } onRemove={ removeNotice } className="shapelms-snackbars" />;
}

export default function App( { courseId, config } ) {
	const [ course, setCourse ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ tab, setTab ] = useState( () => new URL( window.location.href ).searchParams.get( 'tab' ) || 'basics' );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ savedAt, setSavedAt ] = useState( null );
	const pending = useRef( {} );
	const timer = useRef( null );

	useEffect( () => {
		get( `courses/${ courseId }/builder` ).then( setCourse ).catch( ( e ) => setError( errorMessage( e ) ) );
	}, [ courseId ] );

	useEffect( () => {
		const warn = ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	const flush = useCallback( () => {
		const payload = pending.current;
		pending.current = {};
		if ( ! Object.keys( payload ).length ) {
			return Promise.resolve();
		}
		setSaving( true );
		return patch( `courses/${ courseId }`, payload )
			.then( ( data ) => {
				setCourse( ( c ) => ( { ...data, curriculum: c?.curriculum || data.curriculum } ) );
				setSavedAt( new Date() );
				if ( ! Object.keys( pending.current ).length ) {
					setDirty( false );
				}
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setSaving( false ) );
	}, [ courseId ] );

	/**
	 * Queue a change: { title, content, excerpt, slug, status, image_id, meta: {...}, categories, tags }.
	 * Local state updates immediately; the save is debounced.
	 */
	const update = useCallback( ( changes ) => {
		setCourse( ( c ) => {
			const next = { ...c, ...changes };
			if ( changes.meta ) {
				next.meta = { ...c.meta, ...changes.meta };
			}
			if ( changes.categories ) {
				next.category_ids = changes.categories;
			}
			if ( changes.tags ) {
				next.tag_names = changes.tags;
			}
			return next;
		} );
		pending.current = {
			...pending.current,
			...changes,
			meta: { ...( pending.current.meta || {} ), ...( changes.meta || {} ) },
		};
		// Keep meta changes queued earlier; only drop the key when nothing is in it.
		if ( ! Object.keys( pending.current.meta ).length ) {
			delete pending.current.meta;
		}
		setDirty( true );
		clearTimeout( timer.current );
		timer.current = setTimeout( flush, 1200 );
	}, [ flush ] );

	const saveNow = () => {
		clearTimeout( timer.current );
		return flush();
	};

	const setStatus = ( status ) => {
		clearTimeout( timer.current );
		pending.current = { ...pending.current, status };
		flush().then( () => success( status === 'publish' ? __( 'Course published.', 'shapelms' ) : status === 'pending' ? __( 'Course submitted for review.', 'shapelms' ) : __( 'Course saved as draft.', 'shapelms' ) ) );
	};

	if ( error ) {
		return <ErrorNotice message={ error } />;
	}
	if ( ! course ) {
		return <Loading />;
	}

	// Add-ons can add, remove or reorder sections: [ { id, label, Component } ].
	const tabs = applyFilters( 'shapelms.builder.tabs', coreTabs, { course, config } );
	const Active = tabs.find( ( t ) => t.id === tab )?.Component || tabs[ 0 ]?.Component || BasicsTab;
	const isPublished = course.status === 'publish';

	return (
		<div className="shapelms-builder">
			<div className="shapelms-builder__bar">
				<input className="shapelms-builder__title-input" value={ course.title } onChange={ ( e ) => update( { title: e.target.value } ) } placeholder={ __( 'Course title', 'shapelms' ) } aria-label={ __( 'Course title', 'shapelms' ) } />
				<span className="shapelms-builder__status">
					{ saving ? __( 'Saving…', 'shapelms' ) : dirty ? __( 'Unsaved changes', 'shapelms' ) : savedAt ? __( 'Saved', 'shapelms' ) : '' }
				</span>
				<Button variant="tertiary" href={ course.preview_url || course.url } target="_blank">{ __( 'Preview', 'shapelms' ) }</Button>
				<Button variant="secondary" onClick={ saveNow } isBusy={ saving } disabled={ ! dirty || saving }>{ __( 'Save', 'shapelms' ) }</Button>
				{ course.can_change_status === false ? null : isPublished ? (
					<Button variant="primary" onClick={ () => setStatus( 'draft' ) } disabled={ saving }>{ __( 'Unpublish', 'shapelms' ) }</Button>
				) : course.can_publish ? (
					<Button variant="primary" onClick={ () => setStatus( 'publish' ) } disabled={ saving }>{ __( 'Publish', 'shapelms' ) }</Button>
				) : (
					<Button variant="primary" onClick={ () => setStatus( 'pending' ) } disabled={ saving || course.status === 'pending' }>{ course.status === 'pending' ? __( 'Awaiting review', 'shapelms' ) : __( 'Submit for review', 'shapelms' ) }</Button>
				) }
			</div>

			<div className="shapelms-builder__layout">
				<nav className="shapelms-builder__nav" aria-label={ __( 'Builder sections', 'shapelms' ) }>
					{ tabs.map( ( t ) => (
						<button key={ t.id } type="button" className={ t.id === tab ? 'is-active' : '' } onClick={ () => setTab( t.id ) }>{ t.label }</button>
					) ) }
				</nav>
				<div className="shapelms-builder__panel">
					<Active course={ course } update={ update } setCourse={ setCourse } config={ config } />
				</div>
			</div>
			<Snackbars />
		</div>
	);
}
