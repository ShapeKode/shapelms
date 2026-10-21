/**
 * Quiz attempts.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { get, del, errorMessage } from '../../shared/api';
import { usePagedList } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, DataTable, Pagination, Badge, ErrorNotice, Loading, formatDate } from '../../shared/components';
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
						<Badge tone={ data.passed ? 'success' : 'danger' }>{ data.passed ? __( 'Passed', 'shapelms' ) : __( 'Failed', 'shapelms' ) }</Badge>{ ' ' }
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
	const [ courses, setCourses ] = useState( [] );
	const [ viewing, setViewing ] = useState( null );
	const listState = usePagedList( 'quizzes/attempts', { course_id: 0 } );

	useEffect( () => {
		get( 'courses', { status: 'any', per_page: 100 } ).then( ( res ) => setCourses( res.items || [] ) );
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
		{ key: 'passed', label: __( 'Result', 'shapelms' ), render: ( row ) => <Badge tone={ row.passed ? 'success' : 'danger' }>{ row.passed ? __( 'Passed', 'shapelms' ) : __( 'Failed', 'shapelms' ) }</Badge> },
		{ key: 'finished_at', label: __( 'Date', 'shapelms' ), render: ( row ) => formatDate( row.finished_at ) },
		{ key: 'actions', label: '', className: 'is-actions', render: ( row ) => <><Button size="compact" variant="secondary" onClick={ () => setViewing( row.id ) }>{ __( 'Review', 'shapelms' ) }</Button><Button size="compact" variant="tertiary" isDestructive onClick={ () => remove( row ) }>{ __( 'Delete', 'shapelms' ) }</Button></> },
	];

	return (
		<>
			<PageHeader title={ __( 'Quiz attempts', 'shapelms' ) } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar filters={ [ { key: 'course', value: String( listState.query.course_id ), onChange: ( v ) => listState.setQuery( { course_id: parseInt( v, 10 ) } ), options: [ { value: '0', label: __( 'All courses', 'shapelms' ) }, ...courses.map( ( c ) => ( { value: String( c.id ), label: c.title } ) ) ] } ] } />
				<DataTable columns={ columns } rows={ listState.items } loading={ listState.loading } emptyText={ __( 'No attempts yet.', 'shapelms' ) } />
				<Pagination page={ listState.query.page } pages={ listState.pages } total={ listState.total } onChange={ listState.setPage } />
			</Card>
			{ viewing && <AttemptModal id={ viewing } onClose={ () => setViewing( null ) } /> }
		</>
	);
}
