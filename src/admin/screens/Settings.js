/**
 * Settings, rendered from the PHP schema.
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, TextControl, TextareaControl, ToggleControl, SelectControl, ColorPicker, TabPanel, Dropdown } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { get, post, errorMessage } from '../../shared/api';
import { PageHeader, Card, Loading, ErrorNotice } from '../../shared/components';
import { takePreloadedData } from '../../shared/hooks';
import { success, error as notifyError } from '../notices';
import PaymentMethods from '../components/PaymentMethods';

function Field( { field, value, onChange, pages } ) {
	const common = { label: field.label, help: field.description, placeholder: field.placeholder, __nextHasNoMarginBottom: true };
	switch ( field.type ) {
		case 'checkbox':
			return <ToggleControl { ...common } checked={ !! value } onChange={ onChange } />;
		case 'number':
			return <TextControl type="number" { ...common } value={ value } min={ field.min } max={ field.max } onChange={ ( v ) => onChange( Number( v ) ) } />;
		case 'select':
			return <SelectControl { ...common } value={ String( value ) } options={ Object.entries( field.options || {} ).map( ( [ k, l ] ) => ( { value: k, label: l } ) ) } onChange={ onChange } />;
		case 'page':
			return <SelectControl { ...common } value={ String( value ) } options={ [ { value: '0', label: __( '— Select a page —', 'shapelms' ) }, ...pages.map( ( p ) => ( { value: String( p.id ), label: p.title } ) ) ] } onChange={ ( v ) => onChange( Number( v ) ) } />;
		case 'textarea':
			return <TextareaControl { ...common } value={ value || '' } onChange={ onChange } rows={ 5 } />;
		case 'color':
			return (
				<div className="shapelms-color-field">
					<span className="components-base-control__label">{ field.label }</span>
					<Dropdown
						renderToggle={ ( { onToggle } ) => (
							<button type="button" className="shapelms-color-swatch" onClick={ onToggle } ref={ ( el ) => el && ( el.style.background = value ) } aria-label={ field.label }>
								<span>{ value }</span>
							</button>
						) }
						renderContent={ () => <ColorPicker color={ value } onChange={ onChange } enableAlpha={ false } /> }
					/>
				</div>
			);
		case 'secret':
			return <TextControl { ...common } type="password" value={ value || '' } onChange={ onChange } autoComplete="new-password" />;
		case 'email':
			return <TextControl { ...common } type="email" value={ value || '' } onChange={ onChange } />;
		default:
			return <TextControl { ...common } value={ value || '' } onChange={ onChange } />;
	}
}

function EmailSection( { fields, values, onChange, pages } ) {
	const groups = useMemo( () => {
		const out = { general: [] };
		Object.entries( fields ).forEach( ( [ key, field ] ) => {
			const group = field.group || 'general';
			out[ group ] = out[ group ] || [];
			out[ group ].push( [ key, field ] );
		} );
		return out;
	}, [ fields ] );

	return (
		<>
			{ groups.general.map( ( [ key, field ] ) => (
				<Field key={ key } field={ field } value={ values[ key ] } onChange={ ( v ) => onChange( key, v ) } pages={ pages } />
			) ) }
			{ Object.entries( groups ).filter( ( [ g ] ) => g !== 'general' ).map( ( [ group, list ] ) => {
				const enabledKey = `${ group }_enabled`;
				return (
					<details key={ group } className="shapelms-email-group">
						<summary>
							<strong>{ fields[ enabledKey ]?.label || group }</strong>
							<span className={ `shapelms-badge is-${ values[ enabledKey ] ? 'success' : 'neutral' }` }>{ values[ enabledKey ] ? __( 'On', 'shapelms' ) : __( 'Off', 'shapelms' ) }</span>
						</summary>
						<div className="shapelms-email-group__body">
							{ list.map( ( [ key, field ] ) => (
								<Field key={ key } field={ key === enabledKey ? { ...field, label: __( 'Send this email', 'shapelms' ) } : field } value={ values[ key ] } onChange={ ( v ) => onChange( key, v ) } pages={ pages } />
							) ) }
						</div>
					</details>
				);
			} ) }
		</>
	);
}

// Payment fields live in the "commerce" section but are shown on their own tab.
const PAYMENTS_TAB = 'payments';

export default function Settings( { config = {} } ) {
	// Server-preloaded responses, taken once on first render.
	const preloaded = useRef( null );
	if ( ! preloaded.current ) {
		preloaded.current = { s: takePreloadedData( 'settings' ), p: takePreloadedData( 'settings/pages' ) };
	}
	const [ schema, setSchema ] = useState( () => preloaded.current.s?.schema || null );
	const [ values, setValues ] = useState( () => preloaded.current.s?.values || {} );
	const [ pages, setPages ] = useState( () => preloaded.current.p || [] );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ dirty, setDirty ] = useState( false );

	useEffect( () => {
		// Normally rendered into the page by the server; fetch only what is missing.
		if ( preloaded.current.s && preloaded.current.p ) {
			return;
		}
		Promise.all( [ preloaded.current.s || get( 'settings' ), preloaded.current.p || get( 'settings/pages' ) ] )
			.then( ( [ s, p ] ) => {
				setSchema( s.schema );
				setValues( s.values );
				setPages( p );
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [] );

	const change = ( section, key, value ) => {
		setValues( ( v ) => ( { ...v, [ section ]: { ...v[ section ], [ key ]: value } } ) );
		setDirty( true );
	};

	const save = () => {
		setBusy( true );
		post( 'settings', { settings: values } )
			.then( ( res ) => {
				setValues( res.values );
				setDirty( false );
				success( __( 'Settings saved.', 'shapelms' ) );
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( error ) {
		return <ErrorNotice message={ error } />;
	}
	if ( ! schema ) {
		return <Loading />;
	}

	const tabs = [];
	Object.entries( schema ).forEach( ( [ id, section ] ) => {
		tabs.push( { name: id, title: section.label } );
		if ( id === 'commerce' && section.gateways ) {
			tabs.push( { name: PAYMENTS_TAB, title: __( 'Payments', 'shapelms' ) } );
		}
	} );
	const urlTab = new URL( window.location.href ).searchParams.get( 'tab' );
	const initialTab = tabs.some( ( t ) => t.name === urlTab ) ? urlTab : tabs[ 0 ]?.name;
	const onTabSelect = ( name ) => {
		const url = new URL( window.location.href );
		if ( url.searchParams.get( 'tab' ) === name ) {
			return;
		}
		url.searchParams.set( 'tab', name );
		url.searchParams.delete( 'method' );
		window.history.replaceState( null, '', url );
	};
	const renderSection = ( tabName ) => {
		if ( tabName === PAYMENTS_TAB ) {
			const commerce = values.commerce || {};
			return (
				<div className="shapelms-settings-section shapelms-settings-section--wide">
					<p className="shapelms-muted">{ __( 'Choose how students pay for courses on Native Checkout. Select a method to add its API keys and options.', 'shapelms' ) }</p>
					<PaymentMethods
						section={ schema.commerce }
						values={ commerce }
						engine={ commerce.engine }
						adminUrl={ config.adminUrl }
						onChange={ ( key, v ) => change( 'commerce', key, v ) }
						renderField={ ( key, field ) => (
							<Field key={ key } field={ field } value={ commerce[ key ] } onChange={ ( v ) => change( 'commerce', key, v ) } pages={ pages } />
						) }
					/>
				</div>
			);
		}
		const section = schema[ tabName ];
		const fields = Object.entries( section.fields ).filter( ( [ , field ] ) => field.tab !== PAYMENTS_TAB );
		return (
			<div className="shapelms-settings-section">
				{ section.description && <p className="shapelms-muted">{ section.description }</p> }
				{ tabName === 'emails' ? (
					<EmailSection fields={ section.fields } values={ values[ tabName ] || {} } onChange={ ( key, v ) => change( tabName, key, v ) } pages={ pages } />
				) : (
					fields.map( ( [ key, field ] ) => (
						<Field key={ key } field={ field } value={ values[ tabName ]?.[ key ] } onChange={ ( v ) => change( tabName, key, v ) } pages={ pages } />
					) )
				) }
			</div>
		);
	};
	const saveButton = <Button variant="primary" isBusy={ busy } disabled={ busy || ! dirty } onClick={ save }>{ __( 'Save changes', 'shapelms' ) }</Button>;

	return (
		<>
			<PageHeader title={ __( 'Settings', 'shapelms' ) } actions={ saveButton } />
			<Card padding={ false }>
				<TabPanel className="shapelms-settings-tabs" tabs={ tabs } initialTabName={ initialTab } onSelect={ onTabSelect }>
					{ ( tab ) => renderSection( tab.name ) }
				</TabPanel>
				{ /* A second Save at the bottom, so long tabs don't need a scroll back up. */ }
				<div className="shapelms-settings-footer">
					{ saveButton }
					{ dirty && ! busy && <span className="shapelms-muted">{ __( 'You have unsaved changes.', 'shapelms' ) }</span> }
				</div>
			</Card>
		</>
	);
}
