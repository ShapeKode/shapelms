/**
 * Payments tab: a list of payment methods and the settings of the selected one.
 *
 * Field definitions come from the "commerce" settings section (fields tagged
 * tab: payments, grouped by their gateway key); the cards come from section.gateways.
 */
import { useMemo, useState } from '@wordpress/element';
import { Button, ToggleControl, Notice } from '@wordpress/components';
import { Icon, check, copy, external, chevronRight, caution } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';

const MASK = '********';

const filled = ( value ) => ( typeof value === 'string' ? value.trim() !== '' : !! value );

/**
 * Groups the payment fields by gateway and finds each gateway's special fields.
 *
 * @param {Object} fields Section fields.
 * @return {Object} gateway id => { enabled, mode, webhook, required, other }.
 */
function groupFields( fields ) {
	const out = {};
	Object.entries( fields ).forEach( ( [ key, field ] ) => {
		if ( field.tab !== 'payments' || ! field.gateway ) {
			return;
		}
		const g = ( out[ field.gateway ] = out[ field.gateway ] || { keys: [], other: [], required: [] } );
		const entry = [ key, field ];
		if ( field.role === 'enabled' ) {
			g.enabled = entry;
		} else if ( field.role === 'mode' ) {
			g.mode = entry;
		} else if ( field.role === 'webhook' ) {
			g.webhook = entry;
		} else if ( field.required && field.type !== 'textarea' ) {
			g.keys.push( entry );
		} else {
			g.other.push( entry );
		}
		if ( field.required ) {
			g.required.push( key );
		}
	} );
	return out;
}

function statusOf( group, values ) {
	if ( ! group?.enabled || ! values[ group.enabled[ 0 ] ] ) {
		return 'off';
	}
	return group.required.every( ( key ) => filled( values[ key ] ) ) ? 'active' : 'setup';
}

function StatusBadge( { status } ) {
	const map = {
		active: [ 'success', __( 'Active', 'shapelms' ) ],
		setup: [ 'warning', __( 'Setup needed', 'shapelms' ) ],
		off: [ 'neutral', __( 'Off', 'shapelms' ) ],
	};
	const [ tone, label ] = map[ status ];
	return <span className={ `shapelms-badge is-${ tone }` }>{ label }</span>;
}

function MethodTile( { gateway } ) {
	return (
		<span className="shapelms-pay-tile" ref={ ( el ) => el && el.style.setProperty( '--tile', gateway.color || '#3b5bdb' ) } aria-hidden="true">
			{ gateway.initial || gateway.label.charAt( 0 ) }
		</span>
	);
}

function CopyField( { label, value } ) {
	const [ copied, setCopied ] = useState( false );
	const doCopy = () => {
		const done = () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 1800 );
		};
		if ( window.navigator.clipboard?.writeText ) {
			window.navigator.clipboard.writeText( value ).then( done );
			return;
		}
		const area = document.createElement( 'textarea' );
		area.value = value;
		document.body.appendChild( area );
		area.select();
		document.execCommand( 'copy' );
		area.remove();
		done();
	};
	return (
		<div className="shapelms-copy-field">
			<span className="components-base-control__label">{ label }</span>
			<div className="shapelms-copy-field__row">
				<code>{ value }</code>
				<Button variant="secondary" size="compact" icon={ copied ? check : copy } onClick={ doCopy }>
					{ copied ? __( 'Copied', 'shapelms' ) : __( 'Copy', 'shapelms' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * Warns when a Stripe key does not match the chosen mode (a common setup mistake).
 *
 * @param {string}  id     Gateway id.
 * @param {Object}  values Section values.
 * @param {boolean} test   Test mode on.
 * @return {string} Warning, or ''.
 */
function keyWarning( id, values, test ) {
	if ( id !== 'stripe' ) {
		return '';
	}
	const wrong = test ? 'live' : 'test';
	const bad = [ values.stripe_publishable_key, values.stripe_secret_key ].some( ( v ) => typeof v === 'string' && v !== MASK && new RegExp( `^(pk|sk|rk)_${ wrong }_` ).test( v.trim() ) );
	if ( ! bad ) {
		return '';
	}
	return test
		? __( 'Test mode is on but a live key is entered. Use your pk_test_ and sk_test_ keys, or turn test mode off.', 'shapelms' )
		: __( 'Live mode is on but a test key is entered. Real payments need your pk_live_ and sk_live_ keys.', 'shapelms' );
}

function MethodPanel( { id, gateway, group, values, onChange, renderField, status } ) {
	const enabledKey = group.enabled?.[ 0 ];
	const isOn = !! values[ enabledKey ];
	const modeKey = group.mode?.[ 0 ];
	const test = modeKey ? !! values[ modeKey ] : false;
	const missing = group.required.filter( ( key ) => ! filled( values[ key ] ) );
	const warning = keyWarning( id, values, test );
	const fieldsOf = ( list ) => list.map( ( [ key, field ] ) => renderField( key, field ) );

	return (
		<div className="shapelms-pay-panel">
			<div className="shapelms-pay-panel__head">
				<MethodTile gateway={ gateway } />
				<div className="shapelms-pay-panel__title">
					<h3>
						{ gateway.label } <StatusBadge status={ status } />
						{ modeKey && isOn && <span className={ `shapelms-badge is-${ test ? 'info' : 'success' }` }>{ test ? __( 'Test', 'shapelms' ) : __( 'Live', 'shapelms' ) }</span> }
					</h3>
					<p>{ gateway.description }</p>
				</div>
				{ enabledKey && (
					<ToggleControl
						className="shapelms-pay-panel__toggle"
						label={ isOn ? __( 'Enabled', 'shapelms' ) : __( 'Disabled', 'shapelms' ) }
						checked={ isOn }
						onChange={ ( v ) => onChange( enabledKey, v ) }
						__nextHasNoMarginBottom
					/>
				) }
			</div>

			{ isOn && missing.length > 0 && (
				<Notice status="warning" isDismissible={ false } className="shapelms-pay-notice">
					{ gateway.offline
						? __( 'Fill in the required details below so students know how to pay.', 'shapelms' )
						: __( 'Add your API credentials below. This method is hidden at checkout until they are saved.', 'shapelms' ) }
				</Notice>
			) }
			{ ! isOn && (
				<p className="shapelms-muted shapelms-pay-hint">
					{ gateway.offline
						? __( 'Turn this method on to offer it at checkout and edit its checkout details.', 'shapelms' )
						: __( 'Turn this method on to add its API keys and offer it at checkout.', 'shapelms' ) }
				</p>
			) }

			{ /* Settings stay closed while the method is off; saved values are kept. */ }
			{ isOn && modeKey && (
				<section className="shapelms-pay-group">
					<header>
						<h4>{ __( 'Environment', 'shapelms' ) }</h4>
					</header>
					<div className="shapelms-segmented" role="radiogroup" aria-label={ __( 'Environment', 'shapelms' ) }>
						<button type="button" role="radio" aria-checked={ test } className={ test ? 'is-active' : '' } onClick={ () => onChange( modeKey, true ) }>
							{ group.mode[ 1 ].option || group.mode[ 1 ].label }
						</button>
						<button type="button" role="radio" aria-checked={ ! test } className={ ! test ? 'is-active' : '' } onClick={ () => onChange( modeKey, false ) }>
							{ __( 'Live', 'shapelms' ) }
						</button>
					</div>
					{ group.mode[ 1 ].description && <p className="shapelms-muted">{ group.mode[ 1 ].description }</p> }
				</section>
			) }

			{ isOn && group.keys.length > 0 && (
				<section className="shapelms-pay-group">
					<header>
						<h4>{ __( 'API credentials', 'shapelms' ) }</h4>
						{ gateway.keys_url && (
							<a href={ gateway.keys_url } target="_blank" rel="noopener noreferrer" className="shapelms-pay-link">
								{ gateway.keys_label || __( 'Get your API keys', 'shapelms' ) } <Icon icon={ external } size={ 16 } />
							</a>
						) }
					</header>
					{ warning && (
						<p className="shapelms-pay-warning"><Icon icon={ caution } size={ 18 } /> { warning }</p>
					) }
					{ fieldsOf( group.keys ) }
					<p className="shapelms-muted">{ __( 'Secret values are stored on your server and never shown again after saving.', 'shapelms' ) }</p>
				</section>
			) }

			{ isOn && group.webhook && (
				<section className="shapelms-pay-group">
					<header>
						<h4>{ __( 'Webhook', 'shapelms' ) }</h4>
						{ gateway.webhook_doc && (
							<a href={ gateway.webhook_doc } target="_blank" rel="noopener noreferrer" className="shapelms-pay-link">
								{ sprintf( /* translators: %s: payment provider name. */ __( 'Open %s webhooks', 'shapelms' ), gateway.label ) } <Icon icon={ external } size={ 16 } />
							</a>
						) }
					</header>
					{ gateway.webhook_url && <CopyField label={ __( 'Webhook URL', 'shapelms' ) } value={ gateway.webhook_url } /> }
					{ renderField( ...group.webhook ) }
				</section>
			) }

			{ isOn && group.other.length > 0 && (
				<section className="shapelms-pay-group">
					<header>
						<h4>{ gateway.offline ? __( 'Checkout details', 'shapelms' ) : __( 'More options', 'shapelms' ) }</h4>
					</header>
					{ fieldsOf( group.other ) }
				</section>
			) }
		</div>
	);
}

export default function PaymentMethods( { section, values, onChange, renderField, engine, adminUrl } ) {
	const groups = useMemo( () => groupFields( section.fields ), [ section.fields ] );
	const gateways = useMemo(
		() => Object.entries( section.gateways || {} ).filter( ( [ id ] ) => groups[ id ] ),
		[ section.gateways, groups ]
	);
	const [ selected, setSelected ] = useState( () => {
		const fromUrl = new URL( window.location.href ).searchParams.get( 'method' );
		return groups[ fromUrl ] ? fromUrl : gateways[ 0 ]?.[ 0 ];
	} );

	const select = ( id ) => {
		setSelected( id );
		const url = new URL( window.location.href );
		url.searchParams.set( 'method', id );
		window.history.replaceState( null, '', url );
	};

	const statuses = Object.fromEntries( gateways.map( ( [ id ] ) => [ id, statusOf( groups[ id ], values ) ] ) );
	const activeCount = Object.values( statuses ).filter( ( s ) => s === 'active' ).length;
	const current = gateways.find( ( [ id ] ) => id === selected ) || gateways[ 0 ];

	if ( ! current ) {
		return null;
	}

	return (
		<>
			{ engine === 'woocommerce' && (
				<Notice status="info" isDismissible={ false } className="shapelms-pay-notice">
					{ __( 'Courses are sold through WooCommerce, so students pay with your WooCommerce payment methods. The methods below are used only by Native Checkout.', 'shapelms' ) }{ ' ' }
					{ adminUrl && <a href={ `${ adminUrl }admin.php?page=wc-settings&tab=checkout` }>{ __( 'WooCommerce payments', 'shapelms' ) }</a> }
				</Notice>
			) }
			{ engine !== 'woocommerce' && activeCount === 0 && (
				<Notice status="warning" isDismissible={ false } className="shapelms-pay-notice">
					{ __( 'No payment method is active yet. Students can enroll in free courses, but cannot buy paid ones until you set one up.', 'shapelms' ) }
				</Notice>
			) }

			<div className="shapelms-pay">
				<nav className="shapelms-pay-list" aria-label={ __( 'Payment methods', 'shapelms' ) }>
					<p className="shapelms-pay-list__summary">
						{ sprintf( /* translators: 1: active methods, 2: all methods. */ __( '%1$d of %2$d active', 'shapelms' ), activeCount, gateways.length ) }
					</p>
					{ gateways.map( ( [ id, gateway ] ) => (
						<button
							key={ id }
							type="button"
							className={ `shapelms-pay-item${ id === current[ 0 ] ? ' is-selected' : '' }` }
							aria-current={ id === current[ 0 ] ? 'true' : undefined }
							onClick={ () => select( id ) }
						>
							<MethodTile gateway={ gateway } />
							<span className="shapelms-pay-item__text">
								<strong>{ gateway.label }</strong>
								<StatusBadge status={ statuses[ id ] } />
							</span>
							<Icon icon={ chevronRight } size={ 20 } className="shapelms-pay-item__chevron" />
						</button>
					) ) }
				</nav>
				<MethodPanel
					key={ current[ 0 ] }
					id={ current[ 0 ] }
					gateway={ current[ 1 ] }
					group={ groups[ current[ 0 ] ] }
					values={ values }
					onChange={ onChange }
					renderField={ renderField }
					status={ statuses[ current[ 0 ] ] }
				/>
			</div>
		</>
	);
}
