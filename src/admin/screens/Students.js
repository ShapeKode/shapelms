/**
 * Students: enrollments list, manual enroll, student detail.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Modal, ComboboxControl, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { get, post, del, errorMessage } from '../../shared/api';
import { usePagedList, useDebounce } from '../../shared/hooks';
import { PageHeader, Card, Toolbar, DataTable, Pagination, Badge, ErrorNotice, Avatar, Loading, formatDate } from '../../shared/components';
import { success, error as notifyError } from '../notices';

const tone = { active: 'info', completed: 'success', cancelled: 'neutral', expired: 'warning' };

function EnrollModal( { onClose, onDone } ) {
	const [ userSearch, setUserSearch ] = useState( '' );
	const [ users, setUsers ] = useState( [] );
	const [ userId, setUserId ] = useState( null );
	const [ courses, setCourses ] = useState( [] );
	const [ courseId, setCourseId ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const debounced = useDebounce( userSearch );

	useEffect( () => {
		get( 'courses', { status: 'any', per_page: 100 } ).then( ( res ) => setCourses( res.items || [] ) );
	}, [] );

	useEffect( () => {
		if ( debounced.length < 2 ) {
			return;
		}
		get( 'students/search-users', { search: debounced } ).then( setUsers );
	}, [ debounced ] );

	const submit = () => {
		setBusy( true );
		post( 'enrollments', { user_id: userId, course_id: parseInt( courseId, 10 ) } )
			.then( () => {
				success( __( 'Student enrolled.', 'shapelms' ) );
				onDone();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<Modal title={ __( 'Enroll a student', 'shapelms' ) } onRequestClose={ onClose } size="small">
			<ComboboxControl
				label={ __( 'User', 'shapelms' ) }
				value={ userId }
				onChange={ setUserId }
				onFilterValueChange={ setUserSearch }
				options={ users.map( ( u ) => ( { value: u.id, label: `${ u.name } (${ u.email })` } ) ) }
				help={ __( 'Type at least two characters to search by name or email.', 'shapelms' ) }
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( 'Course', 'shapelms' ) }
				value={ courseId }
				onChange={ setCourseId }
				options={ [ { value: '', label: __( 'Select a course', 'shapelms' ) }, ...courses.map( ( c ) => ( { value: String( c.id ), label: c.title } ) ) ] }
				__nextHasNoMarginBottom
			/>
			<div className="shapelms-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'shapelms' ) }</Button>
				<Button variant="primary" isBusy={ busy } disabled={ busy || ! userId || ! courseId } onClick={ submit }>{ __( 'Enroll', 'shapelms' ) }</Button>
			</div>
		</Modal>
	);
}

function StudentModal( { id, onClose, onChange } ) {
	const [ student, setStudent ] = useState( null );
	const [ error, setError ] = useState( '' );

	const load = () => get( `students/${ id }` ).then( setStudent ).catch( ( e ) => setError( errorMessage( e ) ) );
	useEffect( () => {
		load();
	}, [ id ] );

	const reset = ( courseId ) => {
		if ( ! window.confirm( __( 'Reset all progress for this course?', 'shapelms' ) ) ) {
			return;
		}
		post( `enrollments/${ courseId }/${ id }/reset` ).then( () => {
			success( __( 'Progress reset.', 'shapelms' ) );
			load();
			onChange();
		} );
	};

	const remove = ( courseId ) => {
		if ( ! window.confirm( __( 'Remove this student from the course?', 'shapelms' ) ) ) {
			return;
		}
		del( `enrollments/${ courseId }/${ id }` ).then( () => {
			success( __( 'Student removed from course.', 'shapelms' ) );
			load();
			onChange();
		} );
	};

	return (
		<Modal title={ student ? student.name : __( 'Student', 'shapelms' ) } onRequestClose={ onClose } size="large">
			<ErrorNotice message={ error } />
			{ ! student && ! error && <Loading /> }
			{ student && (
				<>
					<div className="shapelms-student-head">
						<Avatar src={ student.avatar } size={ 56 } />
						<div>
							<strong>{ student.name }</strong>
							<div className="shapelms-muted">{ student.email } · { sprintf( __( 'Joined %s', 'shapelms' ), formatDate( student.registered ) ) }</div>
						</div>
					</div>
					<h3>{ __( 'Enrollments', 'shapelms' ) }</h3>
					<table className="shapelms-admin-table">
						<thead>
							<tr>
								<th>{ __( 'Course', 'shapelms' ) }</th>
								<th>{ __( 'Status', 'shapelms' ) }</th>
								<th>{ __( 'Progress', 'shapelms' ) }</th>
								<th>{ __( 'Enrolled', 'shapelms' ) }</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{ student.enrollments.map( ( item ) => (
								<tr key={ item.course.id }>
									<td>{ item.course.title }</td>
									<td><Badge tone={ tone[ item.enrollment.status ] }>{ item.enrollment.status }</Badge></td>
									<td>{ item.summary.done } / { item.summary.total } ({ item.summary.percent }%)</td>
									<td>{ formatDate( item.enrollment.started_at ) }</td>
									<td className="is-actions">
										<Button size="compact" variant="tertiary" onClick={ () => reset( item.course.id ) }>{ __( 'Reset', 'shapelms' ) }</Button>
										<Button size="compact" variant="tertiary" isDestructive onClick={ () => remove( item.course.id ) }>{ __( 'Remove', 'shapelms' ) }</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
					{ student.activity?.length > 0 && (
						<>
							<h3>{ __( 'Activity', 'shapelms' ) }</h3>
							<ul className="shapelms-activity">
								{ student.activity.map( ( row ) => (
									<li key={ row.id }>
										{ row.event.replace( 'shapelms_', '' ).replace( /_/g, ' ' ) } { row.course_title && <em>{ row.course_title }</em> }
										<span className="shapelms-activity__time">{ formatDate( row.created_at ) }</span>
									</li>
								) ) }
							</ul>
						</>
					) }
				</>
			) }
		</Modal>
	);
}

export default function Students() {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebounce( search );
	const [ courses, setCourses ] = useState( [] );
	const [ enrolling, setEnrolling ] = useState( false );
	const [ viewing, setViewing ] = useState( null );
	const listState = usePagedList( 'students', { search: debounced, course_id: 0, status: '' } );

	if ( listState.query.search !== debounced ) {
		listState.setQuery( { search: debounced } );
	}

	useEffect( () => {
		get( 'courses', { status: 'any', per_page: 100 } ).then( ( res ) => setCourses( res.items || [] ) );
	}, [] );

	const columns = [
		{
			key: 'user',
			label: __( 'Student', 'shapelms' ),
			render: ( row ) => (
				<div className="shapelms-cell-title">
					<Avatar src={ row.avatar } />
					<div>
						<button type="button" className="shapelms-link-button" onClick={ () => setViewing( row.user_id ) }><strong>{ row.user_name }</strong></button>
						<div className="shapelms-muted">{ row.user_email }</div>
					</div>
				</div>
			),
		},
		{ key: 'course_title', label: __( 'Course', 'shapelms' ) },
		{ key: 'status', label: __( 'Status', 'shapelms' ), render: ( row ) => <Badge tone={ tone[ row.status ] }>{ row.status }</Badge> },
		{
			key: 'progress',
			label: __( 'Progress', 'shapelms' ),
			render: ( row ) => (
				<div className="shapelms-progress-cell">
					<span className="shapelms-progress-cell__bar"><i data-percent={ row.progress } ref={ ( el ) => el && ( el.style.width = row.progress + '%' ) } /></span>
					{ row.progress }%
				</div>
			),
		},
		{ key: 'source', label: __( 'Source', 'shapelms' ) },
		{ key: 'started_at', label: __( 'Enrolled', 'shapelms' ), render: ( row ) => formatDate( row.started_at ) },
	];

	return (
		<>
			<PageHeader title={ __( 'Students', 'shapelms' ) } actions={ <Button variant="primary" onClick={ () => setEnrolling( true ) }>{ __( 'Enroll student', 'shapelms' ) }</Button> } />
			<ErrorNotice message={ listState.error } />
			<Card padding={ false }>
				<Toolbar
					search={ search }
					onSearch={ setSearch }
					filters={ [
						{ key: 'course', value: String( listState.query.course_id ), onChange: ( v ) => listState.setQuery( { course_id: parseInt( v, 10 ) } ), options: [ { value: '0', label: __( 'All courses', 'shapelms' ) }, ...courses.map( ( c ) => ( { value: String( c.id ), label: c.title } ) ) ] },
						{ key: 'status', value: listState.query.status, onChange: ( status ) => listState.setQuery( { status } ), options: [ { value: '', label: __( 'All statuses', 'shapelms' ) }, { value: 'active', label: __( 'Active', 'shapelms' ) }, { value: 'completed', label: __( 'Completed', 'shapelms' ) }, { value: 'cancelled', label: __( 'Cancelled', 'shapelms' ) } ] },
					] }
				/>
				<DataTable columns={ columns } rows={ listState.items } loading={ listState.loading } emptyText={ __( 'No enrollments yet.', 'shapelms' ) } />
				<Pagination page={ listState.query.page } pages={ listState.pages } total={ listState.total } onChange={ listState.setPage } />
			</Card>
			{ enrolling && <EnrollModal onClose={ () => setEnrolling( false ) } onDone={ () => { setEnrolling( false ); listState.reload(); } } /> }
			{ viewing && <StudentModal id={ viewing } onClose={ () => setViewing( null ) } onChange={ listState.reload } /> }
		</>
	);
}
