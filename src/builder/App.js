/**
 * Course builder: tabs, autosave, publish.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { Button, SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { get, patch, errorMessage } from '../shared/api';
import { Loading, ErrorNotice } from '../shared/components';
import { success, error as notifyError } from '../admin/notices';
import BasicsTab from './tabs/BasicsTab';
import CurriculumTab from './tabs/CurriculumTab';
import SettingsTab from './tabs/SettingsTab';
import PricingTab from './tabs/PricingTab';
import { formatSchedule } from './components/SchedulePanel';

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
	// A complete, future date the user picked but has not confirmed yet.
	const [ scheduleDraft, setScheduleDraft ] = useState( '' );
	const pending = useRef( {} );
	const timer = useRef( null );

	useEffect( () => {
		get( `courses/${ courseId }/builder` ).then( setCourse ).catch( ( e ) => setError( errorMessage( e ) ) );
	}, [ courseId ] );

	useEffect( () => {
		const warn = ( e ) => {
			if ( dirty || scheduleDraft ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty, scheduleDraft ] );

	const flush = useCallback( () => {
		const payload = pending.current;
		pending.current = {};
		if ( ! Object.keys( payload ).length ) {
			return Promise.resolve( true );
		}
		setSaving( true );
		return patch( `courses/${ courseId }`, payload )
			.then( ( data ) => {
				// Re-apply edits made while this save was in flight so the response does not undo them.
				const queued = pending.current;
				setCourse( ( c ) => {
					const next = {
						...data,
						...queued,
						meta: { ...data.meta, ...( queued.meta || {} ) },
						curriculum: c?.curriculum || data.curriculum,
					};
					if ( queued.categories ) {
						next.category_ids = queued.categories;
					}
					if ( queued.tags ) {
						next.tag_names = queued.tags;
					}
					return next;
				} );
				setSavedAt( new Date() );
				if ( ! Object.keys( pending.current ).length ) {
					setDirty( false );
				}
				return true;
			} )
			.catch( ( e ) => {
				notifyError( errorMessage( e ) );
				return false;
			} )
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
		flush().then( ( ok ) => ok && success( status === 'publish' ? __( 'Course published.', 'shapelms' ) : status === 'pending' ? __( 'Course submitted for review.', 'shapelms' ) : __( 'Course saved as draft.', 'shapelms' ) ) );
	};

	const applySchedule = ( schedule ) => {
		clearTimeout( timer.current );
		pending.current = { ...pending.current, schedule };
		flush().then( ( ok ) => {
			if ( ! ok ) {
				return;
			}
			setScheduleDraft( '' );
			success( schedule ? sprintf( /* translators: %s: date and time */ __( 'Course scheduled for %s.', 'shapelms' ), formatSchedule( schedule, 'medium' ) ) : __( 'Schedule cancelled. The course is now a draft.', 'shapelms' ) );
		} );
	};

	// The builder sits inside the classic editor's <form id="post">, whose hidden #title
	// still holds the title from page load. Keep it in step so anything that submits that
	// form (or WordPress autosave) sends the current title instead of reverting it.
	const title = course?.title;
	useEffect( () => {
		const classic = document.getElementById( 'title' );
		if ( classic && typeof title === 'string' && classic.value !== title ) {
			classic.value = title;
		}
	}, [ title ] );

	// Enter in a text field would submit that classic form and reload the page with the
	// old title. Stop it for every field in the builder; fields that act on Enter (add a
	// topic, add a lesson) still get their own key handler first.
	const blockFormSubmit = ( e ) => {
		if ( e.key === 'Enter' && e.target.tagName === 'INPUT' ) {
			e.preventDefault();
		}
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
	// A private course is live too: its way back is Unpublish.
	const isPublished = course.status === 'publish' || course.status === 'private';

	return (
		// eslint-disable-next-line jsx-a11y/no-static-element-interactions -- only stops implicit form submission.
		<div className="shapelms-builder" onKeyDown={ blockFormSubmit }>
			<div className="shapelms-builder__bar">
				<input className="shapelms-builder__title-input" value={ course.title } onChange={ ( e ) => update( { title: e.target.value } ) } onKeyDown={ ( e ) => e.key === 'Enter' && saveNow() } placeholder={ __( 'Course title', 'shapelms' ) } aria-label={ __( 'Course title', 'shapelms' ) } />
				<span className="shapelms-builder__status">
					{ saving ? __( 'Saving…', 'shapelms' ) : dirty ? __( 'Unsaved changes', 'shapelms' ) : savedAt ? __( 'Saved', 'shapelms' ) : '' }
				</span>
				{ course.status === 'future' && course.schedule && (
					<span className="shapelms-badge is-info">{ sprintf( /* translators: %s: date and time */ __( 'Scheduled: %s', 'shapelms' ), formatSchedule( course.schedule, 'medium' ) ) }</span>
				) }
				<Button variant="tertiary" href={ course.preview_url || course.url } target="_blank">{ __( 'Preview', 'shapelms' ) }</Button>
				<Button variant="secondary" onClick={ saveNow } isBusy={ saving } disabled={ ! dirty || saving }>{ __( 'Save', 'shapelms' ) }</Button>
				{ course.can_change_status === false ? null : scheduleDraft && course.status !== 'private' ? (
					<Button variant="primary" onClick={ () => applySchedule( scheduleDraft ) } disabled={ saving }>{ course.status === 'future' ? __( 'Update schedule', 'shapelms' ) : __( 'Schedule course', 'shapelms' ) }</Button>
				) : isPublished ? (
					<Button variant="primary" onClick={ () => setStatus( 'draft' ) } disabled={ saving }>{ __( 'Unpublish', 'shapelms' ) }</Button>
				) : course.can_publish ? (
					<Button variant="primary" onClick={ () => setStatus( 'publish' ) } disabled={ saving }>{ course.status === 'future' ? __( 'Publish now', 'shapelms' ) : __( 'Publish', 'shapelms' ) }</Button>
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
					<Active course={ course } update={ update } setCourse={ setCourse } config={ config } scheduleDraft={ scheduleDraft } setScheduleDraft={ setScheduleDraft } applySchedule={ applySchedule } />
				</div>
			</div>
			<Snackbars />
		</div>
	);
}
