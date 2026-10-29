<?php
/**
 * Front-end account tests.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Pages;
use ShapeLMS\Frontend\Auth;

/**
 * Sign-up validation, account creation and URL helpers.
 */
class AuthTest extends ShapeLMS_TestCase {

	/**
	 * A valid submission creates a subscriber with the student role.
	 */
	public function test_create_student() {
		$fired = 0;
		add_action(
			'shapelms_student_registered',
			static function () use ( &$fired ) {
				++$fired;
			}
		);

		$id = Auth::create_student( 'ada', 'ada@example.org', 'correct-horse-9', 'correct-horse-9' );

		$this->assertIsInt( $id );
		$user = get_userdata( $id );
		$this->assertSame( 'ada', $user->user_login );
		$this->assertContains( 'subscriber', $user->roles );
		$this->assertContains( Capabilities::STUDENT, $user->roles );
		$this->assertSame( 1, $fired );
	}

	/**
	 * Bad input is rejected with one error per field.
	 */
	public function test_validation_errors() {
		Auth::create_student( 'taken', 'taken@example.org', 'password-123', 'password-123' );

		$result = Auth::create_student( '', 'not-an-email', 'short', 'short' );
		$this->assertWPError( $result );
		$this->assertSame( array( 'username', 'email', 'password' ), $result->get_error_codes() );

		$dupe_name = Auth::create_student( 'taken', 'new@example.org', 'password-123', 'password-123' );
		$this->assertWPError( $dupe_name );
		$this->assertSame( array( 'username' ), $dupe_name->get_error_codes() );

		$dupe_mail = Auth::create_student( 'fresh', 'taken@example.org', 'password-123', 'password-123' );
		$this->assertWPError( $dupe_mail );
		$this->assertSame( array( 'email' ), $dupe_mail->get_error_codes() );

		$mismatch = Auth::create_student( 'other', 'other@example.org', 'password-123', 'password-456' );
		$this->assertWPError( $mismatch );
		$this->assertSame( array( 'password_confirm' ), $mismatch->get_error_codes() );
	}

	/**
	 * The setting closes sign-up.
	 */
	public function test_is_open_follows_setting() {
		update_option( 'users_can_register', 1 );
		$this->assertTrue( Auth::is_open() );

		$this->set_setting( 'general', 'student_registration', false );
		$this->assertFalse( Auth::is_open() );
	}

	/**
	 * Sign-up stays closed when WordPress's own "Anyone can register" is off.
	 */
	public function test_is_open_respects_core_registration_setting() {
		update_option( 'users_can_register', 0 );
		$this->assertFalse( Auth::is_open() );
	}

	/**
	 * URL helpers use the plugin pages when they exist and fall back to wp-login.php otherwise.
	 */
	public function test_url_helpers() {
		delete_option( 'shapelms_pages' );
		$this->assertSame( wp_login_url( 'https://example.org/x' ), shapelms_login_url( 'https://example.org/x' ) );
		$this->assertSame( wp_lostpassword_url(), shapelms_lostpassword_url() );

		Pages::create_pages();
		$login = get_permalink( Pages::get_id( 'login' ) );

		$this->assertStringStartsWith( $login, shapelms_login_url() );
		$this->assertStringContainsString( 'redirect_to=', shapelms_login_url( 'https://example.org/x' ) );
		$this->assertStringContainsString( 'step=lost', shapelms_lostpassword_url() );
		$this->assertStringStartsWith( get_permalink( Pages::get_id( 'register' ) ), shapelms_register_url() );
		$this->assertStringContainsString( 'step=reset', Auth::reset_url( 'abc', 'ada' ) );
	}

	/**
	 * Core lost-password emails link to the plugin's reset step.
	 */
	public function test_reset_link_rewritten_in_email() {
		Pages::create_pages();
		$auth    = new Auth();
		// Exactly what core's retrieve_password() writes (wp-includes/user.php).
		$core    = network_site_url( 'wp-login.php?login=ada&key=KEY123&action=rp', 'login' ) . '&wp_lang=en_US';
		$message = "Reset here: {$core}\r\n\r\nThanks";

		$result = $auth->reset_link_in_email( $message, 'KEY123', 'ada' );

		$this->assertStringNotContainsString( 'wp-login.php', $result );
		$this->assertStringNotContainsString( 'wp_lang', $result );
		$this->assertStringContainsString( 'step=reset', $result );
		$this->assertStringContainsString( 'key=KEY123', $result );
		$this->assertStringEndsWith( "\r\n\r\nThanks", $result );
	}

	/**
	 * The reset step URL used by the form never carries the key.
	 */
	public function test_reset_step_url_has_no_key() {
		Pages::create_pages();

		$url = Auth::reset_step_url();

		$this->assertStringContainsString( 'step=reset', $url );
		$this->assertStringNotContainsString( 'key=', $url );
		$this->assertStringNotContainsString( 'login=', $url );
	}

	/**
	 * The shared form rate limit lets N submissions through, then blocks.
	 */
	public function test_allow_rate_limit() {
		$this->assertTrue( Auth::allow( 'unit_test_form', 2, MINUTE_IN_SECONDS ) );
		$this->assertTrue( Auth::allow( 'unit_test_form', 2, MINUTE_IN_SECONDS ) );
		$this->assertFalse( Auth::allow( 'unit_test_form', 2, MINUTE_IN_SECONDS ) );
	}
}
