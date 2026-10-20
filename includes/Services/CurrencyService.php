<?php
/**
 * Currency formatting.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

use ShapeLMS\Core\Settings;

/**
 * Formats prices and lists supported currencies.
 */
class CurrencyService {

	/**
	 * Supported currencies: code => [ name, symbol ].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function currencies() {
		return array(
			'USD' => array( __( 'US Dollar', 'shapelms' ), '$' ),
			'EUR' => array( __( 'Euro', 'shapelms' ), '€' ),
			'GBP' => array( __( 'Pound Sterling', 'shapelms' ), '£' ),
			'AUD' => array( __( 'Australian Dollar', 'shapelms' ), 'A$' ),
			'CAD' => array( __( 'Canadian Dollar', 'shapelms' ), 'C$' ),
			'INR' => array( __( 'Indian Rupee', 'shapelms' ), '₹' ),
			'BDT' => array( __( 'Bangladeshi Taka', 'shapelms' ), '৳' ),
			'JPY' => array( __( 'Japanese Yen', 'shapelms' ), '¥' ),
			'CNY' => array( __( 'Chinese Yuan', 'shapelms' ), '¥' ),
			'BRL' => array( __( 'Brazilian Real', 'shapelms' ), 'R$' ),
			'MXN' => array( __( 'Mexican Peso', 'shapelms' ), 'MX$' ),
			'NGN' => array( __( 'Nigerian Naira', 'shapelms' ), '₦' ),
			'ZAR' => array( __( 'South African Rand', 'shapelms' ), 'R' ),
			'SGD' => array( __( 'Singapore Dollar', 'shapelms' ), 'S$' ),
			'CHF' => array( __( 'Swiss Franc', 'shapelms' ), 'CHF' ),
			'SEK' => array( __( 'Swedish Krona', 'shapelms' ), 'kr' ),
			'NOK' => array( __( 'Norwegian Krone', 'shapelms' ), 'kr' ),
			'DKK' => array( __( 'Danish Krone', 'shapelms' ), 'kr' ),
			'PLN' => array( __( 'Polish Zloty', 'shapelms' ), 'zł' ),
			'TRY' => array( __( 'Turkish Lira', 'shapelms' ), '₺' ),
			'AED' => array( __( 'UAE Dirham', 'shapelms' ), 'د.إ' ),
			'SAR' => array( __( 'Saudi Riyal', 'shapelms' ), '﷼' ),
			'PKR' => array( __( 'Pakistani Rupee', 'shapelms' ), '₨' ),
			'IDR' => array( __( 'Indonesian Rupiah', 'shapelms' ), 'Rp' ),
			'PHP' => array( __( 'Philippine Peso', 'shapelms' ), '₱' ),
			'MYR' => array( __( 'Malaysian Ringgit', 'shapelms' ), 'RM' ),
			'THB' => array( __( 'Thai Baht', 'shapelms' ), '฿' ),
			'VND' => array( __( 'Vietnamese Dong', 'shapelms' ), '₫' ),
			'KRW' => array( __( 'South Korean Won', 'shapelms' ), '₩' ),
			'NZD' => array( __( 'New Zealand Dollar', 'shapelms' ), 'NZ$' ),
		);
	}

	/**
	 * Options for the settings select.
	 *
	 * @return array<string, string>
	 */
	public static function currency_options() {
		$options = array();

		foreach ( self::currencies() as $code => $data ) {
			$options[ $code ] = sprintf( '%s (%s)', $data[0], $code );
		}

		return $options;
	}

	/**
	 * Current currency code.
	 *
	 * @return string
	 */
	public static function code() {
		return (string) Settings::get( 'commerce', 'currency', 'USD' );
	}

	/**
	 * Symbol for the current (or given) currency.
	 *
	 * @param string $code Currency code.
	 * @return string
	 */
	public static function symbol( $code = '' ) {
		$code       = $code ? $code : self::code();
		$currencies = self::currencies();

		return isset( $currencies[ $code ] ) ? $currencies[ $code ][1] : $code;
	}

	/**
	 * Formats an amount.
	 *
	 * @param float  $amount Amount.
	 * @param string $code   Currency code (defaults to the setting).
	 * @return string
	 */
	public static function format( $amount, $code = '' ) {
		$decimals = (int) Settings::get( 'commerce', 'decimals', 2 );
		$position = (string) Settings::get( 'commerce', 'currency_position', 'left' );
		$symbol   = self::symbol( $code );
		$number   = number_format_i18n( (float) $amount, $decimals );

		switch ( $position ) {
			case 'right':
				$formatted = $number . $symbol;
				break;
			case 'left_space':
				$formatted = $symbol . ' ' . $number;
				break;
			case 'right_space':
				$formatted = $number . ' ' . $symbol;
				break;
			case 'left':
			default:
				$formatted = $symbol . $number;
		}

		/**
		 * Filters a formatted price.
		 *
		 * @param string $formatted Formatted price.
		 * @param float  $amount    Amount.
		 * @param string $code      Currency code.
		 */
		return apply_filters( 'shapelms_format_price', $formatted, (float) $amount, $code ? $code : self::code() );
	}
}
