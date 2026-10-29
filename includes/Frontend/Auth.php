<?php
/**
 * Front-end sign in, sign up, lost password and reset password.
 *
 * Everything runs on the plugin's own pages so visitors never see wp-login.php.
 * Forms are classic POSTs with nonces, so they work without JavaScript.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Pages;

/**
 * Handles the account forms and renders the auth views.
 */
class Auth {

	/**
	 * Transient prefix for errors and re-fill values.
	 */
	const TRANSIENT = 'shapelms_auth_';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'prepare_page' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );

		if ( shapelms_get_setting( 'general', 'front_login', true ) ) {
			add_filter( 'retrieve_password_message', array( $this, 'reset_link_in_email' ), 10, 3 );
			add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
			add_filter( 'register_url', array( $this, 'filter_register_url' ) );
			add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ) );
		}
	}

	/**
	 * Whether visitors can create a student account.
	 *
	 * @return bool
	 */
	public static function is_open() {
		/**
		 * Filters whether the student sign-up form is available.
		 *
		 * @param bool $open Whether sign-up is open.
		 */
		$core = is_multisite()
			? in_array( get_site_option( 'registration' ), array( 'user', 'all' ), true )
			: (bool) get_option( 'users_can_register' );

		return (bool) apply_filters( 'shapelms_student_registration_open', $core && (bool) shapelms_get_setting( 'general', 'student_registration', true ) );
	}

	/**
	 * Points WordPress login links at the Sign in page (except forced re-auth, used by wp-admin).
	 *
	 * @param string $url          Login URL.
	 * @param string $redirect     Redirect.
	 * @param bool   $force_reauth Force re-auth.
	 * @return string
	 */
	public function filter_login_url( $url, $redirect, $force_reauth ) {
		if ( $force_reauth || ! self::page_ready( 'login' ) ) {
			return $url;
		}

		return shapelms_login_url( (string) $redirect );
	}

	/**
	 * Points registration links at the Sign up page.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function filter_register_url( $url ) {
		return self::page_ready( 'register' ) && self::is_open() ? shapelms_register_url() : $url;
	}

	/**
	 * Points lost-password links at the Sign in page.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function filter_lostpassword_url( $url ) {
		return self::page_ready( 'login' ) ? shapelms_lostpassword_url() : $url;
	}

	/**
	 * Whether one of the auth pages exists and is published.
	 *
	 * @param string $key login|register.
	 * @return bool
	 */
	private static function page_ready( $key ) {
		$id = Pages::get_id( $key );

		return $id > 0 && 'publish' === get_post_status( $id );
	}

	/**
	 * Rewrites the reset link in the password email so it opens on the Sign in page.
	 *
	 * @param string $message    Email body.
	 * @param string $key        Reset key.
	 * @param string $user_login User login.
	 * @return string
	 */
	public function reset_link_in_email( $message, $key, $user_login ) {
		if ( ! self::page_ready( 'login' ) ) {
			return $message;
		}

		// Core builds: wp-login.php?login=X&key=K&action=rp&wp_lang=xx (wp-includes/user.php).
		$core = network_site_url( 'wp-login.php?login=' . rawurlencode( $user_login ) . '&key=' . $key . '&action=rp', 'login' );
		$ours = self::reset_url( $key, $user_login );

		return (string) preg_replace( '#' . preg_quote( $core, '#' ) . '(&wp_lang=[A-Za-z0-9_-]+)?#', str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $ours ), $message );
	}

	/**
	 * Reset-password URL on the Sign in page.
	 *
	 * @param string $key   Key.
	 * @param string $login Login.
	 * @return string
	 */
	public static function reset_url( $key, $login ) {
		return add_query_arg(
			array(
				'step'  => 'reset',
				'key'   => $key,
				'login' => rawurlencode( $login ),
			),
			(string) get_permalink( Pages::get_id( 'login' ) )
		);
	}

	/**
	 * Key-less reset step URL (the key lives in an HttpOnly cookie once the email link is opened).
	 *
	 * @return string
	 */
	public static function reset_step_url() {
		return add_query_arg( 'step', 'reset', (string) get_permalink( Pages::get_id( 'login' ) ) );
	}

	/**
	 * Runs before anything renders on the Sign in / Sign up pages.
	 *
	 * - Marks them no-cache so full-page caches never serve stale nonces or the reset step.
	 * - Moves the reset key from the email link into an HttpOnly cookie and redirects to a clean
	 *   URL, so analytics, history and referrers never see it (same approach as wp-login.php).
	 *
	 * @return void
	 */
	public function prepare_page() {
		if ( ! Pages::is_page( 'login' ) && ! Pages::is_page( 'register' ) ) {
			return;
		}

		nocache_headers();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only; the reset key is verified with check_password_reset_key().
		if ( isset( $_GET['redirect_to'] ) && ! is_user_logged_in() ) {
			// Keep the destination even if the visitor switches to Sign up or comes back later.
			self::remember_return( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in remember_return().
		}

		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';

		if ( 'reset' !== $step ) {
			return;
		}

		header( 'Referrer-Policy: no-referrer' );

		if ( isset( $_GET['key'], $_GET['login'] ) ) {
			$login = sanitize_text_field( wp_unslash( $_GET['login'] ) );
			$key   = sanitize_text_field( wp_unslash( $_GET['key'] ) );
			self::set_cookie( self::reset_cookie_name(), $login . ':' . $key, 0 );
			wp_safe_redirect( self::reset_step_url() );
			exit;
		}
		// phpcs:enable
	}

	/**
	 * Reset cookie name (per site).
	 *
	 * @return string
	 */
	private static function reset_cookie_name() {
		return 'shapelms_resetpass_' . COOKIEHASH;
	}

	/**
	 * Login and key from the reset cookie.
	 *
	 * @return array{login: string, key: string}
	 */
	private static function reset_from_cookie() {
		$name  = self::reset_cookie_name();
		$raw   = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
		$parts = explode( ':', $raw, 2 );

		return array(
			'login' => isset( $parts[1] ) ? $parts[0] : '',
			'key'   => isset( $parts[1] ) ? $parts[1] : '',
		);
	}

	/**
	 * Sets (or clears, with an expiry in the past) an HttpOnly cookie on the site path.
	 *
	 * @param string $name    Name.
	 * @param string $value   Value.
	 * @param int    $expires Unix time, 0 for a session cookie.
	 * @return void
	 */
	private static function set_cookie( $name, $value, $expires ) {
		if ( headers_sent() ) {
			return;
		}

		setcookie( $name, $value, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		if ( $expires && $expires < time() ) {
			unset( $_COOKIE[ $name ] );
		} else {
			$_COOKIE[ $name ] = $value;
		}
	}

	/**
	 * Routes the POSTed forms.
	 *
	 * @return void
	 */
	public function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- routing only; every handler verifies its own nonce first.
		if ( isset( $_POST['shapelms_login'] ) ) {
			$this->handle_login();
		} elseif ( isset( $_POST['shapelms_student_register'] ) ) {
			$this->handle_register();
		} elseif ( isset( $_POST['shapelms_lostpassword'] ) ) {
			$this->handle_lostpassword();
		} elseif ( isset( $_POST['shapelms_resetpass'] ) ) {
			$this->handle_reset();
		}
		// phpcs:enable
	}

	/**
	 * Safe post-login destination.
	 *
	 * @return string
	 */
	private function redirect_target() {
		$fallback = Pages::get_url( 'dashboard' );
		// phpcs:ignore WordPress.Security.NonceVerification -- only used as a redirect target and validated with wp_validate_redirect().
		$requested = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';

		if ( ! $requested ) {
			// The page the visitor was on when they were sent to sign in (Buy now, Enroll, a locked lesson, checkout).
			$requested = self::remembered_return();
		}

		return $requested ? wp_validate_redirect( $requested, $fallback ) : $fallback;
	}

	/**
	 * Cookie that remembers where to send the visitor after they sign in or sign up.
	 *
	 * @return string
	 */
	private static function return_cookie_name() {
		return 'shapelms_return_' . COOKIEHASH;
	}

	/**
	 * Remembers a return URL for 30 minutes (same-site URLs only).
	 *
	 * @param string $url URL.
	 * @return void
	 */
	public static function remember_return( $url ) {
		$url = wp_validate_redirect( esc_url_raw( (string) $url ), '' );

		if ( $url ) {
			self::set_cookie( self::return_cookie_name(), $url, time() + 30 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Reads and clears the remembered return URL.
	 *
	 * @return string
	 */
	private static function remembered_return() {
		$name = self::return_cookie_name();
		$url  = isset( $_COOKIE[ $name ] ) ? esc_url_raw( wp_unslash( $_COOKIE[ $name ] ) ) : '';

		if ( $url ) {
			self::set_cookie( $name, ' ', time() - YEAR_IN_SECONDS );
		}

		return $url;
	}

	/**
	 * Logs in with wp_signon().
	 *
	 * @return void
	 */
	private function handle_login() {
		$this->verify( 'shapelms_login' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in verify() above.

		if ( is_user_logged_in() ) {
			wp_safe_redirect( $this->redirect_target() );
			exit;
		}

		$login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$pass  = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.

		// Brute-force guard: failed attempts per visitor IP and per account.
		$ip_bucket   = 'login_ip_' . self::ip_hash();
		$user_bucket = 'login_u_' . md5( strtolower( $login ) );
		if ( self::throttled( $ip_bucket, 20 ) || self::throttled( $user_bucket, 10 ) ) {
			$this->remember(
				array(
					'errors' => array( __( 'Too many failed attempts. Please wait 15 minutes and try again.', 'shapelms' ) ),
					'login'  => $login,
				)
			);
			$this->back( shapelms_login_url( $this->redirect_target() ), array( 'login' => 'failed' ) );
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => ! empty( $_POST['rememberme'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			self::hit( $ip_bucket, 15 * MINUTE_IN_SECONDS );
			self::hit( $user_bucket, 15 * MINUTE_IN_SECONDS );
			$this->remember(
				array(
					'errors' => array( __( 'The username, email or password is incorrect.', 'shapelms' ) ),
					'login'  => $login,
				)
			);
			$this->back( shapelms_login_url( $this->redirect_target() ), array( 'login' => 'failed' ) );
		}

		self::clear( $user_bucket );
		wp_set_current_user( $user->ID );
		wp_safe_redirect( $this->redirect_target() );
		exit;
		// phpcs:enable
	}

	/**
	 * Creates a student account.
	 *
	 * @return void
	 */
	private function handle_register() {
		$this->verify( 'shapelms_student_register' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in verify() above.

		if ( is_user_logged_in() ) {
			wp_safe_redirect( $this->redirect_target() );
			exit;
		}

		if ( ! self::is_open() ) {
			wp_die( esc_html__( 'Registration is closed.', 'shapelms' ) );
		}

		// Honeypot: real browsers leave this empty.
		if ( ! empty( $_POST['shapelms_website'] ) ) {
			wp_safe_redirect( $this->redirect_target() );
			exit;
		}

		// Sign-up guard: 10 attempts per hour per visitor IP (slows down account enumeration and spam).
		$bucket = 'register_ip_' . self::ip_hash();
		if ( self::throttled( $bucket, 10 ) ) {
			$this->remember( array( 'errors' => array( __( 'Too many sign-up attempts. Please try again later.', 'shapelms' ) ) ) );
			$this->back( shapelms_register_url( $this->redirect_target() ), array( 'signup' => 'error' ) );
		}
		self::hit( $bucket, HOUR_IN_SECONDS );

		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.
		$confirm  = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.

		$result = self::create_student( $username, $email, $password, $confirm );

		if ( is_wp_error( $result ) ) {
			$this->remember(
				array(
					'errors'   => $result->get_error_messages(),
					'username' => $username,
					'email'    => $email,
				)
			);
			$this->back( shapelms_register_url( $this->redirect_target() ), array( 'signup' => 'error' ) );
		}

		wp_set_current_user( $result );
		wp_set_auth_cookie( $result, true, is_ssl() );

		wp_safe_redirect( add_query_arg( 'signup', '1', $this->redirect_target() ) );
		exit;
		// phpcs:enable
	}

	/**
	 * Sends the password reset email.
	 *
	 * @return void
	 */
	private function handle_lostpassword() {
		$this->verify( 'shapelms_lostpassword' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in verify() above.

		$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';

		if ( '' === $login ) {
			$this->remember( array( 'errors' => array( __( 'Please enter your username or email address.', 'shapelms' ) ) ) );
			$this->back( shapelms_lostpassword_url() );
		}

		// Throttle per visitor IP (5 per 10 minutes) and per account (3 per hour, limits email-bombing one person).
		$ip_bucket   = 'lost_ip_' . self::ip_hash();
		$user_bucket = 'lost_u_' . md5( strtolower( $login ) );
		if ( self::throttled( $ip_bucket, 5 ) ) {
			$this->remember( array( 'errors' => array( __( 'Too many requests. Please try again in a few minutes.', 'shapelms' ) ) ) );
			$this->back( shapelms_lostpassword_url() );
		}
		self::hit( $ip_bucket, 10 * MINUTE_IN_SECONDS );

		// A throttled account gets the same "check your email" answer, so the limit reveals nothing.
		if ( self::throttled( $user_bucket, 3 ) ) {
			$this->back( shapelms_lostpassword_url(), array( 'checkemail' => 'confirm' ) );
		}
		self::hit( $user_bucket, HOUR_IN_SECONDS );

		// Requests from our own form always get a reset link back to our page, whatever the site-wide setting.
		$rewrite = array( $this, 'reset_link_in_email' );
		$added   = ! has_filter( 'retrieve_password_message', $rewrite );
		if ( $added ) {
			add_filter( 'retrieve_password_message', $rewrite, 10, 3 );
		}

		$result = retrieve_password( $login );

		if ( $added ) {
			remove_filter( 'retrieve_password_message', $rewrite, 10 );
		}

		/*
		 * Never reveal whether the account exists. These codes only happen for real accounts
		 * (or are configuration problems), so they get the same neutral answer as success.
		 */
		$silent = array( 'invalid_email', 'invalidcombo', 'empty_username', 'no_password_reset', 'retrieve_password_email_failure', 'no_password_key_update' );
		if ( is_wp_error( $result ) && ! in_array( $result->get_error_code(), $silent, true ) ) {
			$this->remember( array( 'errors' => array( __( 'The reset email could not be sent. Please try again later.', 'shapelms' ) ) ) );
			$this->back( shapelms_lostpassword_url() );
		}

		$this->back( shapelms_lostpassword_url(), array( 'checkemail' => 'confirm' ) );
		// phpcs:enable
	}

	/**
	 * Sets a new password from a valid reset link.
	 *
	 * @return void
	 */
	private function handle_reset() {
		$this->verify( 'shapelms_resetpass' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in verify() above.

		$cookie = self::reset_from_cookie();
		$key    = $cookie['key'];
		$login  = $cookie['login'];
		$pass1  = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.
		$pass2  = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.

		$user = check_password_reset_key( $key, $login );

		if ( is_wp_error( $user ) ) {
			self::set_cookie( self::reset_cookie_name(), ' ', time() - YEAR_IN_SECONDS );
			$this->remember( array( 'errors' => array( __( 'This reset link is invalid or has expired. Please request a new one.', 'shapelms' ) ) ) );
			$this->back( shapelms_lostpassword_url() );
		}

		$errors = array();
		if ( strlen( $pass1 ) < 8 ) {
			$errors[] = __( 'Password must be at least 8 characters.', 'shapelms' );
		} elseif ( $pass1 !== $pass2 ) {
			$errors[] = __( 'The passwords do not match.', 'shapelms' );
		}

		// Let password-policy plugins add their rules, exactly as wp-login.php does.
		$policy = new \WP_Error();
		do_action( 'validate_password_reset', $policy, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		$errors = array_merge( $errors, $policy->get_error_messages() );

		if ( $errors ) {
			$this->remember( array( 'errors' => $errors ) );
			$this->back( self::reset_step_url() );
		}

		reset_password( $user, $pass1 );
		self::set_cookie( self::reset_cookie_name(), ' ', time() - YEAR_IN_SECONDS );

		$this->back( shapelms_login_url(), array( 'reset' => '1' ) );
		// phpcs:enable
	}

	/**
	 * Validates and creates a student account.
	 *
	 * @param string      $username Username.
	 * @param string      $email    Email.
	 * @param string      $password Password.
	 * @param string|null $confirm  Password confirmation (null skips the check).
	 * @return int|\WP_Error User id.
	 */
	public static function create_student( $username, $email, $password, $confirm = null ) {
		$errors   = new \WP_Error();
		$username = sanitize_user( (string) $username, true );

		if ( '' === $username ) {
			$errors->add( 'username', __( 'Please choose a username (letters and numbers only).', 'shapelms' ) );
		} elseif ( username_exists( $username ) ) {
			$errors->add( 'username', __( 'That username is already taken.', 'shapelms' ) );
		} elseif ( ! validate_username( $username ) ) {
			$errors->add( 'username', __( 'That username is not allowed.', 'shapelms' ) );
		}
		if ( ! is_email( $email ) ) {
			$errors->add( 'email', __( 'Please enter a valid email address.', 'shapelms' ) );
		} elseif ( email_exists( $email ) ) {
			$errors->add( 'email', __( 'An account with this email already exists. Please sign in instead.', 'shapelms' ) );
		}
		if ( strlen( (string) $password ) < 8 ) {
			$errors->add( 'password', __( 'Password must be at least 8 characters.', 'shapelms' ) );
		} elseif ( null !== $confirm && $confirm !== $password ) {
			$errors->add( 'password_confirm', __( 'The passwords do not match.', 'shapelms' ) );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		/** This action is documented in wp-login.php */
		do_action( 'register_post', $username, $email, $errors ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.

		/** This filter is documented in wp-includes/user.php */
		$errors = apply_filters( 'registration_errors', $errors, $username, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $username,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->add_role( Capabilities::STUDENT );
		}

		wp_new_user_notification( $user_id, null, 'admin' );

		/**
		 * Fires after a student signs up on the front end.
		 *
		 * @param int $user_id New user id.
		 */
		do_action( 'shapelms_student_registered', $user_id );

		return (int) $user_id;
	}

	/**
	 * Renders an auth view: login (with lost/reset steps) or register.
	 *
	 * @param string $view login|register.
	 * @return string
	 */
	public static function render( $view ) {
		if ( is_user_logged_in() ) {
			return shapelms_get_template( 'auth/logged-in.php', array(), false );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view selection and status flags.
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		$args = array(
			'redirect_to' => isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '',
			'attempt'     => self::last_attempt(),
			'flags'       => array(
				'login'      => isset( $_GET['login'] ) ? sanitize_key( wp_unslash( $_GET['login'] ) ) : '',
				'signup'     => isset( $_GET['signup'] ) ? sanitize_key( wp_unslash( $_GET['signup'] ) ) : '',
				'checkemail' => isset( $_GET['checkemail'] ) ? sanitize_key( wp_unslash( $_GET['checkemail'] ) ) : '',
				'reset'      => isset( $_GET['reset'] ) ? sanitize_key( wp_unslash( $_GET['reset'] ) ) : '',
			),
		);

		if ( 'login' === $view && 'reset' === $step ) {
			$cookie        = self::reset_from_cookie();
			$args['valid'] = '' !== $cookie['key'] && ! is_wp_error( check_password_reset_key( $cookie['key'], $cookie['login'] ) );
			$template      = 'auth/reset.php';
		} elseif ( 'login' === $view && 'lost' === $step ) {
			$template = 'auth/lost-password.php';
		} elseif ( 'register' === $view ) {
			$template = 'auth/register.php';
		} else {
			$template = 'auth/login.php';
		}
		// phpcs:enable

		return shapelms_get_template( $template, $args, false );
	}

	/**
	 * Errors and submitted values from the last attempt (read once).
	 *
	 * @return array{errors: string[], login: string, username: string, email: string}
	 */
	public static function last_attempt() {
		$key   = self::state_key();
		$state = $key ? get_transient( $key ) : false;
		if ( $key ) {
			delete_transient( $key );
		}

		return array(
			'errors'   => isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : array(),
			'login'    => isset( $state['login'] ) ? (string) $state['login'] : '',
			'username' => isset( $state['username'] ) ? (string) $state['username'] : '',
			'email'    => isset( $state['email'] ) ? (string) $state['email'] : '',
		);
	}

	/**
	 * Verifies the form nonce or dies.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function verify( $action ) {
		if ( ! isset( $_POST['shapelms_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shapelms_nonce'] ) ), $action ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'shapelms' ) );
		}
	}

	/**
	 * Stores errors and values for the next page load.
	 *
	 * @param array<string, mixed> $state State.
	 * @return void
	 */
	private function remember( array $state ) {
		$key = self::state_key( true );

		if ( $key ) {
			set_transient( $key, $state, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Transient key for this browser's form state.
	 *
	 * Keyed on a random HttpOnly cookie, never on the IP, so people sharing a network
	 * (schools, offices, mobile carriers) never see each other's typed values or errors.
	 *
	 * @param bool $create Create the cookie when missing (POST handlers only, before output).
	 * @return string Empty when there is no cookie and $create is false.
	 */
	private static function state_key( $create = false ) {
		$name = 'shapelms_auth_sid_' . COOKIEHASH;
		$sid  = isset( $_COOKIE[ $name ] ) ? sanitize_key( wp_unslash( $_COOKIE[ $name ] ) ) : '';

		if ( 32 !== strlen( $sid ) ) {
			if ( ! $create ) {
				return '';
			}
			$sid = strtolower( wp_generate_password( 32, false ) );
			self::set_cookie( $name, $sid, time() + 10 * MINUTE_IN_SECONDS );
		}

		return self::TRANSIENT . 'state_' . md5( $sid . '|' . wp_salt( 'nonce' ) );
	}

	/**
	 * Rate limit for other front-end forms: records a hit and returns false when the bucket is full.
	 *
	 * @param string $name   Form name.
	 * @param int    $limit  Max submissions per visitor IP in the window.
	 * @param int    $window Window in seconds.
	 * @return bool True when the submission may go ahead.
	 */
	public static function allow( $name, $limit, $window ) {
		$bucket = sanitize_key( $name ) . '_ip_' . self::ip_hash();

		if ( self::throttled( $bucket, $limit ) ) {
			return false;
		}

		self::hit( $bucket, $window );

		return true;
	}

	/**
	 * Whether a rate-limit bucket is full.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $limit  Max hits in the window.
	 * @return bool
	 */
	private static function throttled( $bucket, $limit ) {
		return (int) get_transient( self::TRANSIENT . 'rl_' . $bucket ) >= $limit;
	}

	/**
	 * Adds a hit to a rate-limit bucket.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $window Window in seconds (starts at the first hit).
	 * @return void
	 */
	private static function hit( $bucket, $window ) {
		$key = self::TRANSIENT . 'rl_' . $bucket;
		set_transient( $key, (int) get_transient( $key ) + 1, $window );
	}

	/**
	 * Empties a rate-limit bucket.
	 *
	 * @param string $bucket Bucket name.
	 * @return void
	 */
	private static function clear( $bucket ) {
		delete_transient( self::TRANSIENT . 'rl_' . $bucket );
	}

	/**
	 * Redirects back to an auth page with flags.
	 *
	 * @param string                $url  URL.
	 * @param array<string, string> $args Query args.
	 * @return void
	 */
	private function back( $url, array $args = array() ) {
		wp_safe_redirect( $args ? add_query_arg( $args, $url ) : $url );
		exit;
	}

	/**
	 * Hashed visitor IP, used only for rate limits (never for per-user state).
	 *
	 * @return string
	 */
	private static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters the visitor IP used for login and sign-up rate limits (for sites behind a trusted proxy).
		 *
		 * @param string $ip IP address.
		 */
		$ip = (string) apply_filters( 'shapelms_rate_limit_ip', $ip );

		return md5( $ip . '|' . wp_salt( 'nonce' ) );
	}
}
