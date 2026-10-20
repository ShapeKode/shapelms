<?php
/**
 * Mailer.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Emails;

use ShapeLMS\Core\Settings;

/**
 * Queues and sends branded HTML emails. Uses Action Scheduler when available, otherwise WP-Cron.
 */
class Mailer {

	/**
	 * Queues an email (sent in the background).
	 *
	 * @param string                $email_id Email id.
	 * @param string                $to       Recipient.
	 * @param array<string, string> $tags     Merge tags.
	 * @return void
	 */
	public static function queue( $email_id, $to, array $tags ) {
		if ( ! is_email( $to ) ) {
			return;
		}

		if ( ! self::enabled( $email_id ) ) {
			return;
		}

		$args = array( $email_id, $to, $tags, array() );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'shapelms_send_queued_email', $args, 'shapelms' );
			return;
		}

		wp_schedule_single_event( time(), 'shapelms_send_queued_email', $args );
		spawn_cron();
	}

	/**
	 * Whether an email is switched on.
	 *
	 * @param string $email_id Email id.
	 * @return bool
	 */
	public static function enabled( $email_id ) {
		$definitions = Module::definitions();

		if ( ! isset( $definitions[ $email_id ] ) ) {
			return false;
		}

		return (bool) Settings::get( 'emails', $email_id . '_enabled', true );
	}

	/**
	 * Sends an email now.
	 *
	 * @param string                $email_id Email id.
	 * @param string                $to       Recipient.
	 * @param array<string, string> $tags     Merge tags.
	 * @param array<string, mixed>  $extra    Extra { attachments }.
	 * @return bool
	 */
	public static function send( $email_id, $to, $tags = array(), $extra = array() ) {
		$definitions = Module::definitions();

		if ( ! isset( $definitions[ $email_id ] ) || ! is_email( $to ) ) {
			return false;
		}

		$subject = (string) Settings::get( 'emails', $email_id . '_subject', $definitions[ $email_id ]['subject'] );
		$body    = (string) Settings::get( 'emails', $email_id . '_body', $definitions[ $email_id ]['body'] );

		$subject = self::merge( $subject, $tags );
		$body    = self::merge( $body, $tags );

		/**
		 * Filters the email before sending.
		 *
		 * @param array  $email    { to, subject, body, headers }.
		 * @param string $email_id Email id.
		 * @param array  $tags     Tags.
		 */
		$email = apply_filters(
			'shapelms_email',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => self::headers(),
			),
			$email_id,
			$tags
		);

		$html = self::wrap( $email['subject'], $email['body'] );

		return wp_mail( $email['to'], $email['subject'], $html, $email['headers'], isset( $extra['attachments'] ) ? $extra['attachments'] : array() );
	}

	/**
	 * Replaces {tags}.
	 *
	 * @param string                $text Text.
	 * @param array<string, string> $tags Tags.
	 * @return string
	 */
	public static function merge( $text, array $tags ) {
		$search  = array();
		$replace = array();

		foreach ( $tags as $key => $value ) {
			$search[]  = '{' . $key . '}';
			$replace[] = (string) $value;
		}

		return str_replace( $search, $replace, $text );
	}

	/**
	 * Headers.
	 *
	 * @return string[]
	 */
	private static function headers() {
		$name  = (string) Settings::get( 'emails', 'from_name', get_bloginfo( 'name' ) );
		$email = (string) Settings::get( 'emails', 'from_email', get_bloginfo( 'admin_email' ) );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( is_email( $email ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $name, $email );
		}

		return $headers;
	}

	/**
	 * Wraps plain-text body in the branded HTML template. URLs become links, newlines become paragraphs.
	 *
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 * @return string
	 */
	private static function wrap( $subject, $body ) {
		$body = esc_html( $body );
		$body = make_clickable( $body );
		$body = wpautop( $body );

		return (string) shapelms_get_template(
			'emails/base.php',
			array(
				'subject' => $subject,
				'body'    => $body,
				'footer'  => (string) Settings::get( 'emails', 'footer_text', get_bloginfo( 'name' ) ),
				'primary' => (string) Settings::get( 'design', 'primary_color', '#3b5bdb' ),
			),
			false
		);
	}
}
