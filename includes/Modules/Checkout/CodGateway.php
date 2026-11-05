<?php
/**
 * Cash on delivery gateway.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Settings;

/**
 * Pay in cash. On by default, so a new site can sell before any provider is set up.
 */
class CodGateway extends OfflineGateway {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'cod';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		$title = trim( (string) Settings::get( 'commerce', 'cod_title', '' ) );

		return '' !== $title ? $title : __( 'Cash on delivery', 'shapelms' );
	}

	/**
	 * Enabled.
	 *
	 * @return bool
	 */
	public function enabled() {
		return (bool) Settings::get( 'commerce', 'cod_enabled', true );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		$text = trim( (string) Settings::get( 'commerce', 'cod_description', '' ) );

		return '' !== $text ? $text : __( 'Pay in cash. You get access to the course once we receive your payment.', 'shapelms' );
	}

	/**
	 * Instructions.
	 *
	 * @return string
	 */
	public function instructions() {
		return '';
	}
}
