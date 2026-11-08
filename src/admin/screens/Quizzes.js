/**
 * Quiz attempts.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { get, post, del, errorMessage } from '../../shared/api';
import { usePagedList, takePreloadedData } from '../../shared/hooks';

// Course filter list; must match the preload in Admin/Menu.php.
const COURSE_QUERY = { status: 'any', per_page: 100 };
import { PageHeader, Card, Toolbar, BulkActions, DataTable, Pagination, Badge, ErrorNotice, Loading, formatDate } from '../../shared/components';

// Result filter, in display order (see AttemptRepository::RESULTS).
const RESULTS = [
	[ '', __( 'All', 'shapelms' ), 'all' ],
	[ 'pass', __( 'Pass', 'shapelms' ), 'pass' ],
	[ 'fail', __( 'Fail', 'shapelms' ), 'fail' ],
	[ 'pending', __( 'Pending', 'shapelms' ), 'pending' ],
];
import { success, error as notifyError } from '../notices';

function AttemptModal( { id, onClose } ) {
	const [ data, setData ] = useState( null );
	useEffect( () => {
		get( `quizzes/attempts/${ id }` ).then( setData ).catch( ( e ) => notifyError( errorMessage( e ) ) );
	}, [ id ] );

	return (
		<Modal title={ __( 'Attempt review', 'shapelms' ) } onRequestClose={ onClose } size="large">
			{ ! data && <Loading /> }
			{ data && (
				<>
					<p>
						{ data.status === 'pending_review' ? <Badge tone="info">{ __( 'Waiting for review', 'shapelms' ) }</Badge> : <Badge tone={ data.passed ? 'success' : 'danger' }>{ data.passed ? __( 'Passed', 'shapelms' ) : __( 'Failed', 'shapelms' ) }</Badge> }{ ' ' }
						<strong>{ sprintf( /* translators: %d: percent */ __( '%d%%', 'shapelms' ), data.percentage ) }</strong> · { sprintf( /* translators: 1: score, 2: maximum score */ _n( '%1$s / %2$s point', '%1$s / %2$s points', data.max_score, 'shapelms' ), data.score, data.max_score ) }
					</p>
					{ ( data.questions || [] ).map( ( q, i ) => (
						<div key={ q.id } className={ `shapelms-review-q is-${ q.is_correct ? 'correct' : 'incorrect' }` }>
							<strong>{ i + 1 }. { q.title }</strong>
							<div className="shapelms-muted">{ __( 'Answer:', 'shapelms' ) } { JSON.stringify( q.answer ) } · { __( 'Correct:', 'shapelms' ) } { JSON.stringify( q.correct ) } · { q.points }/{ q.max_points }</div>
						</div>
					) ) }
				</>
			) }
		</Modal>
	);
}

export default function Quizzes() {
	// Course filter list, rendered into the page by the server (Menu::preload); fetched only when missing.
	const [ courses, setCourses ] = useState( () => takePreloadedData( 'courses', COURSE_QUERY )?.items || [] );
	const [ viewing, setViewing ] = useState( null );
	const listState = usePagedList( 'quizzes/attempts', { course_id: 0, result: '' } );
	const [ selected, setSelected ] = useState( [] );
	const [ bulkBusy, setBulkBusy ] = useState( false );
	// Only rows on the current page count, so paging or filtering drops stale picks.
	const selectedIds = selected.filter( ( id ) => listState.items.some( ( row ) => row.id === id ) );

	const applyBulk = ( action ) => {
		const ids = selectedIds;
		if ( action !== 'delete' || ! ids.length ) {
			return;
		}
		if ( ! window.confirm( sprintf( /* translators: %d: number of attempts */ _n(
			'Delete %d quiz attempt permanently? Its answers are deleted too and the student gets the attempt back. This cannot be undone.',
			'Delete %d quiz attempts permanently? Their answers are deleted too and the students get the attempts back. This cannot be undone.',
			ids.length,
			'shapelms'
		), ids.length ) ) ) {
			return;
		}
		setBulkBusy( true );
		post( 'quizzes/attempts/bulk', { action, ids } )
			.then( ( res ) => {
				if ( res.done ) {
					/* translators: %d: number of attempts */
					success( sprintf( _n( '%d attempt deleted.', '%d attempts deleted.', res.done, 'shapelms' ), res.done ) );
				}
				if ( res.skipped ) {
					/* translators: %d: number of attempts */
					notifyError( sprintf( _n( '%d attempt was skipped: it no longer exists or you cannot edit its course.', '%d attempts were skipped: they no longer exist or you cannot edit their courses.', res.skipped, 'shapelms' ), res.skipped ) );
				}
				setSelected( [] );
				listState.reload();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBulkBusy( false ) );
	};

	useEffect( () => {
		if ( courses.length ) {
			return;
		}
		get( 'courses', COURSE_QUERY ).then( ( res ) => setCourses( res.items || [] ) );
		// Only once, on mount.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const remove = ( row ) => {
		if ( ! window.confirm( __( 'Delete this attempt? The student will get the attempt back.', 'shapelms' ) ) ) {
			return;
		}
		del( `quizzes/attempts/${ row.id }` ).then( () => {
			success( __( 'Attempt deleted.', 'shapelms' ) );
			listState.reload();
		} ).catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	const columns = [
		{ key: 'user_name', label: __( 'Student', 'shapelms' ), render: ( row ) => <><strong>{ row.user_name }</strong><div className="shapelms-muted">{ row.user_email }</div></> },
		{ key: 'quiz_title', label: __( 'Quiz', 'shapelms' ) },
		{ key: 'attempt_number', label: '#' },
		{ key: 'percentage', label: __( 'Score', 'shapelms' ), render: ( row ) => `${ row.percentage }%` },
		{ key: 'passed', label: __( 'Result', 'shapelms' ), render: ( row ) => ( row.status === 'pending_review' ? <Badge tone="info">{ __( 'Waiting for review', 'shapelms' ) }</Badge> : <Badge tone={ row.passed ? 'success' : 'danger' }>{ row.passed ? __( 'Passed', 'shapelms' ) : __( 'Failed', 'shapelms' ) }</Badge> ) },
		{ key: 'finished_at', label: __( 'Date', 'shapelms' ), render: ( row ) => formatDate( row.finished_at ) },
		{ key: 'actions', label: '', className: 'is-actions', render: ( row ) => <><Button size="compact" variant="secondary" onClick={ () => setViewing( row.id ) }>{ __( 'Review', 'shapelms' ) }</Button><Button size="compact" variant="tertiary" isDestructive onClick={ () => remove( row ) }>{ __( 'Delete', 'shapelms' ) }</Button></> },
	];

	return (
		<>
			<PageHeader title={ __( 'Quiz attempts', 'shapelms' ) } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar
					actions={ <BulkActions options={ [ { value: 'delete', label: __( 'Delete permanently', 'shapelms' ) } ] } onApply={ applyBulk } count={ selectedIds.length } busy={ bulkBusy } /> }
					filters={ [
						{ key: 'course', value: String( listState.query.course_id ), onChange: ( v ) => listState.setQuery( { course_id: parseInt( v, 10 ) } ), options: [ { value: '0', label: __( 'All courses', 'shapelms' ) }, ...courses.map( ( c ) => ( { value: String( c.id ), label: c.title } ) ) ] },
						{
							// Result, with how many attempts each one holds in the chosen course.
							key: 'result',
							value: listState.query.result,
							onChange: ( result ) => {
								setSelected( [] );
								listState.setQuery( { result } );
							},
							options: RESULTS.map( ( [ value, label, countKey ] ) => ( {
								value,
								label: sprintf( /* translators: 1: result, 2: number of attempts */ __( '%1$s (%2$d)', 'shapelms' ), label, listState.counts?.[ countKey ] ?? 0 ),
							} ) ),
						},
					] }
				/>
				<DataTable
					columns={ columns }
					rows={ listState.items }
					loading={ listState.loading }
					emptyText={ listState.query.result ? __( 'No attempts match this filter.', 'shapelms' ) : __( 'No attempts yet.', 'shapelms' ) }
					selection={ { selected: selectedIds, onChange: setSelected } }
				/>
				<Pagination page={ listState.query.page } pages={ listState.pages } total={ listState.total } onChange={ listState.setPage } />
			</Card>
			{ viewing && <AttemptModal id={ viewing } onClose={ () => setViewing( null ) } /> }
		</>
	);
}
