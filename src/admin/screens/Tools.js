/**
 * Tools: setup wizard, import/export, status.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, SelectControl, ToggleControl, TabPanel, FormFileUpload, TextControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { get, post, errorMessage } from '../../shared/api';
import { PageHeader, Card, Loading, ErrorNotice, Badge } from '../../shared/components';
import { success, error as notifyError } from '../notices';

function Setup( { status, reload, config } ) {
	const [ step, setStep ] = useState( 0 );
	const [ engine, setEngine ] = useState( 'native' );
	const [ currency, setCurrency ] = useState( 'USD' );
	const [ currencies, setCurrencies ] = useState( {} );
	const [ importDemo, setImportDemo ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ done, setDone ] = useState( null );

	useEffect( () => {
		get( 'settings' ).then( ( s ) => {
			setCurrencies( s.schema.commerce.fields.currency.options );
			setCurrency( s.values.commerce.currency );
			setEngine( s.values.commerce.engine );
		} );
	}, [] );

	const finish = () => {
		setBusy( true );
		post( 'tools/setup', { settings: { commerce: { engine, currency } }, create_pages: true, import_demo: importDemo } )
			.then( ( res ) => {
				setDone( res );
				reload();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( done ) {
		return (
			<Card title={ __( 'You are all set!', 'shapelms' ) }>
				<p>{ __( 'Pages were created and your settings are saved.', 'shapelms' ) }</p>
				<div className="shapelms-modal-actions shapelms-modal-actions--left">
					<Button variant="primary" href={ config.newCourse }>{ __( 'Create your first course', 'shapelms' ) }</Button>
					{ done.demo_course > 0 && <Button variant="secondary" href={ `${ config.adminUrl }post.php?post=${ done.demo_course }&action=edit` }>{ __( 'Open the demo course', 'shapelms' ) }</Button> }
					<Button variant="tertiary" href={ `${ config.adminUrl }admin.php?page=shapelms-settings` }>{ __( 'All settings', 'shapelms' ) }</Button>
				</div>
			</Card>
		);
	}

	const steps = [
		{
			title: __( 'Welcome', 'shapelms' ),
			body: (
				<>
					<p>{ __( 'This wizard creates the pages ShapeLMS needs (dashboard, checkout, instructor registration), sets your currency and can import a demo course so you can see everything working.', 'shapelms' ) }</p>
					<p className="shapelms-muted">{ __( 'You can change all of this later in Settings.', 'shapelms' ) }</p>
				</>
			),
		},
		{
			title: __( 'Selling', 'shapelms' ),
			body: (
				<>
					<SelectControl label={ __( 'How do you want to sell courses?', 'shapelms' ) } value={ engine } onChange={ setEngine } options={ [ { value: 'native', label: __( 'ShapeLMS checkout (Stripe, PayPal)', 'shapelms' ) }, { value: 'woocommerce', label: status.woocommerce ? __( 'WooCommerce', 'shapelms' ) : __( 'WooCommerce (not installed)', 'shapelms' ) } ] } __nextHasNoMarginBottom />
					<SelectControl label={ __( 'Currency', 'shapelms' ) } value={ currency } onChange={ setCurrency } options={ Object.entries( currencies ).map( ( [ k, l ] ) => ( { value: k, label: l } ) ) } __nextHasNoMarginBottom />
					<p className="shapelms-muted">{ __( 'Add your Stripe or PayPal keys in Settings → Selling when you are ready to accept payments. Free courses work right away.', 'shapelms' ) }</p>
				</>
			),
		},
		{
			title: __( 'Demo content', 'shapelms' ),
			body: (
				<>
					<ToggleControl label={ __( 'Import a demo course with lessons and a quiz', 'shapelms' ) } checked={ importDemo } onChange={ setImportDemo } __nextHasNoMarginBottom />
					<p className="shapelms-muted">{ __( 'The demo course is created as a draft. Delete it whenever you like.', 'shapelms' ) }</p>
				</>
			),
		},
	];

	return (
		<Card title={ /* translators: 1: current step, 2: total steps, 3: step title */ sprintf( __( 'Step %1$d of %2$d: %3$s', 'shapelms' ), step + 1, steps.length, steps[ step ].title ) }>
			<div className="shapelms-wizard-body">{ steps[ step ].body }</div>
			<div className="shapelms-modal-actions">
				{ step > 0 && <Button variant="tertiary" onClick={ () => setStep( step - 1 ) }>{ __( 'Back', 'shapelms' ) }</Button> }
				{ step < steps.length - 1 && <Button variant="primary" onClick={ () => setStep( step + 1 ) }>{ __( 'Continue', 'shapelms' ) }</Button> }
				{ step === steps.length - 1 && <Button variant="primary" isBusy={ busy } disabled={ busy } onClick={ finish }>{ __( 'Finish setup', 'shapelms' ) }</Button> }
			</div>
		</Card>
	);
}

function ImportExport( { config } ) {
	const [ ids, setIds ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const exportJson = () => {
		setBusy( true );
		get( 'tools/export', { ids } )
			.then( ( data ) => {
				const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
				const a = document.createElement( 'a' );
				a.href = URL.createObjectURL( blob );
				a.download = 'shapelms-courses.json';
				a.click();
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const importJson = ( event ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new FileReader();
		reader.onload = () => {
			let data;
			try {
				data = JSON.parse( reader.result );
			} catch ( e ) {
				notifyError( __( 'That file is not valid JSON.', 'shapelms' ) );
				return;
			}
			setBusy( true );
			post( 'tools/import', { data } )
				.then( ( res ) => success( sprintf( /* translators: %d: number of courses */ _n( 'Imported %d course.', 'Imported %d courses.', res.imported, 'shapelms' ), res.imported ) ) )
				.catch( ( e ) => notifyError( errorMessage( e ) ) )
				.finally( () => setBusy( false ) );
		};
		reader.readAsText( file );
	};

	const demo = () => {
		setBusy( true );
		post( 'tools/demo' )
			.then( ( res ) => {
				success( __( 'Demo course created.', 'shapelms' ) );
				window.location.href = res.edit_url;
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const samplePack = () => {
		setBusy( true );
		post( 'tools/demo', { pack: 'courses' } )
			.then( ( res ) => {
				success( sprintf( /* translators: %d: number of courses */ _n( 'Created %d sample course.', 'Created %d sample courses.', res.count, 'shapelms' ), res.count ) );
				window.open( res.archive_url, '_blank' );
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="shapelms-grid-2">
			<Card title={ __( 'Export courses', 'shapelms' ) }>
				<p>{ __( 'Downloads courses with their curriculum and quiz questions as JSON. Enrollments are not included.', 'shapelms' ) }</p>
				<TextControl label={ __( 'Course IDs (comma separated, empty = all)', 'shapelms' ) } value={ ids } onChange={ setIds } __nextHasNoMarginBottom />
				<Button variant="primary" isBusy={ busy } onClick={ exportJson }>{ __( 'Export JSON', 'shapelms' ) }</Button>
			</Card>
			<Card title={ __( 'Import courses', 'shapelms' ) }>
				<p>{ __( 'Upload a JSON file exported from ShapeLMS. Courses are created as new drafts.', 'shapelms' ) }</p>
				<FormFileUpload accept="application/json" onChange={ importJson } variant="primary" disabled={ busy }>{ __( 'Choose JSON file', 'shapelms' ) }</FormFileUpload>
				<hr />
				<p>{ __( 'Or create the bundled demo course (one draft), or a sample pack of six published courses to try the archive, filters, checkout and dashboards.', 'shapelms' ) }</p>
				<div className="shapelms-builder__row">
					<Button variant="secondary" isBusy={ busy } onClick={ demo }>{ __( 'Import demo course', 'shapelms' ) }</Button>
					<Button variant="secondary" isBusy={ busy } onClick={ samplePack }>{ __( 'Create 6 sample courses', 'shapelms' ) }</Button>
				</div>
			</Card>
		</div>
	);
}

function Status( { status } ) {
	const rows = [
		[ __( 'Plugin version', 'shapelms' ), status.version ],
		[ __( 'Database version', 'shapelms' ), status.db_version ],
		[ __( 'WordPress', 'shapelms' ), status.wp ],
		[ __( 'PHP', 'shapelms' ), status.php ],
		[ __( 'Pretty permalinks', 'shapelms' ), status.permalinks ? <Badge tone="success">{ __( 'On', 'shapelms' ) }</Badge> : <Badge tone="danger">{ __( 'Off — lesson URLs need pretty permalinks', 'shapelms' ) }</Badge> ],
		[ __( 'WooCommerce', 'shapelms' ), status.woocommerce ? __( 'Active', 'shapelms' ) : __( 'Not active', 'shapelms' ) ],
		[ __( 'Action Scheduler', 'shapelms' ), status.action_scheduler ? __( 'Available (background emails)', 'shapelms' ) : __( 'Not available (WP-Cron is used)', 'shapelms' ) ],
	];
	return (
		<div className="shapelms-grid-2">
			<Card title={ __( 'System', 'shapelms' ) }>
				<table className="shapelms-admin-table shapelms-admin-table--kv"><tbody>{ rows.map( ( [ k, v ] ) => <tr key={ k }><th>{ k }</th><td>{ v }</td></tr> ) }</tbody></table>
			</Card>
			<Card title={ __( 'Pages', 'shapelms' ) }>
				<table className="shapelms-admin-table shapelms-admin-table--kv"><tbody>
					{ Object.entries( status.pages ).map( ( [ key, page ] ) => (
						<tr key={ key }><th>{ key.replace( '_', ' ' ) }</th><td>{ page.exists ? <a href={ page.url } target="_blank" rel="noreferrer">{ page.url }</a> : <Badge tone="warning">{ __( 'Missing', 'shapelms' ) }</Badge> }</td></tr>
					) ) }
				</tbody></table>
			</Card>
		</div>
	);
}

export default function Tools( { config } ) {
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( '' );
	const initial = new URL( window.location.href ).searchParams.get( 'tab' ) || 'status';

	const load = () => get( 'tools/status' ).then( setStatus ).catch( ( e ) => setError( errorMessage( e ) ) );
	useEffect( () => {
		load();
	}, [] );

	return (
		<>
			<PageHeader title={ __( 'Tools', 'shapelms' ) } />
			<ErrorNotice message={ error } />
			{ ! status && ! error && <Loading /> }
			{ status && (
				<TabPanel className="shapelms-tools-tabs" initialTabName={ initial } tabs={ [ { name: 'status', title: __( 'Status', 'shapelms' ) }, { name: 'setup', title: __( 'Setup wizard', 'shapelms' ) }, { name: 'import', title: __( 'Import / Export', 'shapelms' ) } ] }>
					{ ( tab ) => {
						if ( tab.name === 'setup' ) {
							return <Setup status={ status } reload={ load } config={ config } />;
						}
						if ( tab.name === 'import' ) {
							return <ImportExport config={ config } />;
						}
						return <Status status={ status } />;
					} }
				</TabPanel>
			) }
		</>
	);
}
