/**
 * Admin dashboard.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { get, errorMessage } from '../../shared/api';
import { PageHeader, Card, StatCard, Loading, ErrorNotice, Empty, formatDate } from '../../shared/components';
import LineChart from '../components/LineChart';

const eventLabels = {
	shapelms_course_enrolled: __( 'enrolled in', 'shapelms' ),
	shapelms_course_completed: __( 'completed', 'shapelms' ),
	shapelms_quiz_finished: __( 'finished a quiz in', 'shapelms' ),
	shapelms_order_completed: __( 'purchased', 'shapelms' ),
	shapelms_review_submitted: __( 'reviewed', 'shapelms' ),
	shapelms_instructor_applied: __( 'applied to become an instructor', 'shapelms' ),
};

export default function Dashboard( { config } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		get( 'reports/overview' ).then( setData ).catch( ( e ) => setError( errorMessage( e ) ) );
	}, [] );

	return (
		<>
			<PageHeader
				title={ __( 'Dashboard', 'shapelms' ) }
				description={ __( 'Last 30 days at a glance.', 'shapelms' ) }
				actions={
					<>
						<Button variant="secondary" href={ config.adminUrl + 'admin.php?page=shapelms-reports' }>{ __( 'Reports', 'shapelms' ) }</Button>
						<Button variant="primary" href={ config.newCourse }>{ __( 'New course', 'shapelms' ) }</Button>
					</>
				}
			/>
			<ErrorNotice message={ error } />
			{ ! config.setupDone && (
				<Card className="shapelms-setup-banner">
					<strong>{ __( 'Finish setting up ShapeLMS', 'shapelms' ) }</strong>
					<p>{ __( 'Create the dashboard and checkout pages, choose your currency and import a demo course.', 'shapelms' ) }</p>
					<Button variant="primary" href={ config.adminUrl + 'admin.php?page=shapelms-tools&tab=setup' }>{ __( 'Run setup wizard', 'shapelms' ) }</Button>
				</Card>
			) }
			{ ! data && ! error && <Loading /> }
			{ data && (
				<>
					<div className="shapelms-stats-grid">
						<StatCard label={ __( 'Published courses', 'shapelms' ) } value={ data.totals.courses } />
						<StatCard label={ __( 'Students', 'shapelms' ) } value={ data.totals.students } hint={ sprintf( __( '%d active', 'shapelms' ), data.totals.active ) } />
						<StatCard label={ __( 'Enrollments (30 days)', 'shapelms' ) } value={ data.period.enrollments } />
						<StatCard label={ __( 'Completions (30 days)', 'shapelms' ) } value={ data.period.completions } />
						{ config.canManage && <StatCard label={ __( 'Revenue (30 days)', 'shapelms' ) } value={ data.period_revenue_html } hint={ sprintf( __( '%s total', 'shapelms' ), data.revenue_html ) } tone="success" /> }
						{ config.canManage && config.pendingInstructors > 0 && (
							<StatCard label={ __( 'Pending instructors', 'shapelms' ) } value={ config.pendingInstructors } tone="warning" />
						) }
					</div>

					<div className="shapelms-grid-2">
						<Card title={ __( 'Enrollments', 'shapelms' ) }>
							<LineChart series={ [ { label: __( 'Enrollments', 'shapelms' ), data: data.series.enrollments }, { label: __( 'Completions', 'shapelms' ), data: data.series.completions, color: '#15803d' } ] } />
						</Card>
						<Card title={ __( 'Recent activity', 'shapelms' ) }>
							{ data.recent.length ? (
								<ul className="shapelms-activity">
									{ data.recent.map( ( row ) => (
										<li key={ row.id }>
											<strong>{ row.user_name }</strong> { eventLabels[ row.event ] || row.event } { row.course_title && <em>{ row.course_title }</em> }
											<span className="shapelms-activity__time">{ formatDate( row.created_at ) }</span>
										</li>
									) ) }
								</ul>
							) : (
								<Empty title={ __( 'No activity yet', 'shapelms' ) } description={ __( 'Enrollments, completions and purchases will show up here.', 'shapelms' ) } />
							) }
						</Card>
					</div>
				</>
			) }
		</>
	);
}
