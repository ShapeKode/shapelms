<?php
/**
 * Direct bank transfer gateway.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Settings;

/**
 * Pay by bank transfer. Off by default: without bank details nobody could act on it.
 */
class BacsGateway extends OfflineGateway {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'bacs';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		$title = trim( (string) Settings::get( 'commerce', 'bacs_title', '' ) );

		return '' !== $title ? $title : __( 'Direct bank transfer', 'shapelms' );
	}

	/**
	 * Enabled.
	 *
	 * @return bool
	 */
	public function enabled() {
		return (bool) Settings::get( 'commerce', 'bacs_enabled', false );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		$text = trim( (string) Settings::get( 'commerce', 'bacs_description', '' ) );

		return '' !== $text ? $text : __( 'Pay directly into our bank account and use your order number as the reference. You get access once the money arrives.', 'shapelms' );
	}

	/**
	 * Instructions and bank details.
	 *
	 * @return string
	 */
	public function instructions() {
		$parts = array(
			trim( (string) Settings::get( 'commerce', 'bacs_instructions', '' ) ),
			trim( (string) Settings::get( 'commerce', 'bacs_account_details', '' ) ),
		);

		return implode( "\n\n", array_filter( $parts ) );
	}
}
