/**
 * Modules on/off.
 */
import { useEffect, useState } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { get, post, errorMessage } from '../../shared/api';
import { PageHeader, Loading, ErrorNotice, Badge } from '../../shared/components';
import { success, error as notifyError } from '../notices';

export default function Modules() {
	const [ modules, setModules ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		get( 'modules' ).then( setModules ).catch( ( e ) => setError( errorMessage( e ) ) );
	}, [] );

	const toggle = ( module, enabled ) => {
		setModules( ( list ) => list.map( ( m ) => ( m.id === module.id ? { ...m, enabled } : m ) ) );
		post( `modules/${ module.id }/toggle`, { enabled } )
			.then( ( list ) => {
				setModules( list );
				success( enabled ? __( 'Module enabled. Reload to apply.', 'shapelms' ) : __( 'Module disabled. Reload to apply.', 'shapelms' ) );
			} )
			.catch( ( e ) => notifyError( errorMessage( e ) ) );
	};

	return (
		<>
			<PageHeader title={ __( 'Modules', 'shapelms' ) } description={ __( 'Switch features on or off. Disabled modules load no code.', 'shapelms' ) } />
			<ErrorNotice message={ error } />
			{ ! modules && ! error && <Loading /> }
			{ modules && (
				<div className="shapelms-modules-grid">
					{ modules.map( ( m ) => (
						<div key={ m.id } className={ `shapelms-module-card ${ m.enabled ? 'is-enabled' : '' }` }>
							<div className="shapelms-module-card__head">
								<h3>{ m.name }</h3>
								{ m.is_pro && <Badge tone="info">{ __( 'Add-on', 'shapelms' ) }</Badge> }
							</div>
							<p>{ m.description }</p>
							{ m.dependencies.length > 0 && <p className="shapelms-muted">{ __( 'Requires:', 'shapelms' ) } { m.dependencies.join( ', ' ) }</p> }
							<ToggleControl label={ m.enabled ? __( 'Enabled', 'shapelms' ) : __( 'Disabled', 'shapelms' ) } checked={ m.enabled } onChange={ ( v ) => toggle( m, v ) } __nextHasNoMarginBottom />
						</div>
					) ) }
				</div>
			) }
		</>
	);
}
