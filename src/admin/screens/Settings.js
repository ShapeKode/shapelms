/**
 * Settings, rendered from the PHP schema.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { Button, TextControl, TextareaControl, ToggleControl, SelectControl, ColorPicker, TabPanel, Dropdown } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { get, post, errorMessage } from '../../shared/api';
import { PageHeader, Card, Loading, ErrorNotice } from '../../shared/components';
import { success, error as notifyError } from '../notices';

function Field( { field, value, onChange, pages } ) {
	const common = { label: field.label, help: field.description, __nextHasNoMarginBottom: true };
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

export default function Settings() {
	const [ schema, setSchema ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ pages, setPages ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ dirty, setDirty ] = useState( false );

	useEffect( () => {
		Promise.all( [ get( 'settings' ), get( 'settings/pages' ) ] )
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

	const tabs = Object.entries( schema ).map( ( [ id, section ] ) => ( { name: id, title: section.label } ) );

	return (
		<>
			<PageHeader title={ __( 'Settings', 'shapelms' ) } actions={ <Button variant="primary" isBusy={ busy } disabled={ busy || ! dirty } onClick={ save }>{ __( 'Save changes', 'shapelms' ) }</Button> } />
			<Card padding={ false }>
				<TabPanel className="shapelms-settings-tabs" tabs={ tabs }>
					{ ( tab ) => {
						const section = schema[ tab.name ];
						return (
							<div className="shapelms-settings-section">
								{ section.description && <p className="shapelms-muted">{ section.description }</p> }
								{ tab.name === 'emails' ? (
									<EmailSection fields={ section.fields } values={ values[ tab.name ] || {} } onChange={ ( key, v ) => change( tab.name, key, v ) } pages={ pages } />
								) : (
									Object.entries( section.fields ).map( ( [ key, field ] ) => (
										<Field key={ key } field={ field } value={ values[ tab.name ]?.[ key ] } onChange={ ( v ) => change( tab.name, key, v ) } pages={ pages } />
									) )
								) }
							</div>
						);
					} }
				</TabPanel>
			</Card>
		</>
	);
}
