/**
 * Reports.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { get, errorMessage } from '../../shared/api';
import { PageHeader, Card, StatCard, Loading, ErrorNotice, DataTable } from '../../shared/components';
import LineChart from '../components/LineChart';

function daysAgo( n ) {
	const d = new Date();
	d.setDate( d.getDate() - n );
	return d.toISOString().slice( 0, 10 );
}

export default function Reports( { config } ) {
	const [ range, setRange ] = useState( '30' );
	const [ courseId, setCourseId ] = useState( '0' );
	const [ courses, setCourses ] = useState( [] );
	const [ overview, setOverview ] = useState( null );
	const [ rows, setRows ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		get( 'courses', { status: 'any', per_page: 100 } ).then( ( res ) => setCourses( res.items || [] ) );
		get( 'reports/courses' ).then( setRows ).catch( ( e ) => setError( errorMessage( e ) ) );
	}, [] );

	useEffect( () => {
		setOverview( null );
		get( 'reports/overview', { from: daysAgo( parseInt( range, 10 ) - 1 ), to: daysAgo( 0 ), course_id: parseInt( courseId, 10 ) } )
			.then( setOverview )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [ range, courseId ] );

	const exportCsv = () => {
		if ( ! rows ) {
			return;
		}
		const header = [ 'Course', 'Students', 'Completed', 'Completion rate', 'Rating', 'Reviews' ];
		const lines = rows.map( ( r ) => [ r.title, r.students, r.completed, r.completion_rate + '%', r.rating, r.reviews ].map( ( v ) => `"${ String( v ).replace( /"/g, '""' ) }"` ).join( ',' ) );
		const blob = new Blob( [ [ header.join( ',' ), ...lines ].join( '\n' ) ], { type: 'text/csv' } );
		const a = document.createElement( 'a' );
		a.href = URL.createObjectURL( blob );
		a.download = 'shapelms-courses.csv';
		a.click();
	};

	return (
		<>
			<PageHeader title={ __( 'Reports', 'shapelms' ) } actions={ <Button variant="secondary" onClick={ exportCsv } disabled={ ! rows }>{ __( 'Export CSV', 'shapelms' ) }</Button> } />
			<ErrorNotice message={ error } />
			<div className="shapelms-toolbar shapelms-toolbar--plain">
				<SelectControl value={ range } onChange={ setRange } options={ [ { value: '7', label: __( 'Last 7 days', 'shapelms' ) }, { value: '30', label: __( 'Last 30 days', 'shapelms' ) }, { value: '90', label: __( 'Last 90 days', 'shapelms' ) }, { value: '365', label: __( 'Last year', 'shapelms' ) } ] } __nextHasNoMarginBottom />
				<SelectControl value={ courseId } onChange={ setCourseId } options={ [ { value: '0', label: __( 'All courses', 'shapelms' ) }, ...courses.map( ( c ) => ( { value: String( c.id ), label: c.title } ) ) ] } __nextHasNoMarginBottom />
			</div>
			{ ! overview && <Loading /> }
			{ overview && (
				<>
					<div className="shapelms-stats-grid">
						<StatCard label={ __( 'Enrollments', 'shapelms' ) } value={ overview.period.enrollments } />
						<StatCard label={ __( 'Completions', 'shapelms' ) } value={ overview.period.completions } />
						<StatCard label={ __( 'Completion rate', 'shapelms' ) } value={ overview.totals.students ? Math.round( ( overview.totals.completed / overview.totals.students ) * 100 ) + '%' : '—' } />
						{ config.canManage && <StatCard label={ __( 'Revenue', 'shapelms' ) } value={ overview.period_revenue_html } tone="success" /> }
					</div>
					<Card title={ __( 'Enrollments and completions', 'shapelms' ) }>
						<LineChart series={ [ { label: __( 'Enrollments', 'shapelms' ), data: overview.series.enrollments }, { label: __( 'Completions', 'shapelms' ), data: overview.series.completions, color: '#15803d' } ] } />
					</Card>
				</>
			) }
			<Card title={ __( 'Courses', 'shapelms' ) } padding={ false }>
				<DataTable
					loading={ ! rows }
					rows={ rows || [] }
					columns={ [
						{ key: 'title', label: __( 'Course', 'shapelms' ), render: ( r ) => <a href={ r.url } target="_blank" rel="noreferrer">{ r.title }</a> },
						{ key: 'students', label: __( 'Students', 'shapelms' ) },
						{ key: 'completed', label: __( 'Completed', 'shapelms' ) },
						{ key: 'completion_rate', label: __( 'Completion rate', 'shapelms' ), render: ( r ) => `${ r.completion_rate }%` },
						{ key: 'rating', label: __( 'Rating', 'shapelms' ), render: ( r ) => ( r.reviews ? `${ Number( r.rating ).toFixed( 1 )} ★ (${ r.reviews })` : '—' ) },
					] }
				/>
			</Card>
		</>
	);
}
