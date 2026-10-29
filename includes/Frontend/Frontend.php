<?php
/**
 * Front end: templates, assets, access control.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Content\PostTypes;
use ShapeLMS\Content\Taxonomies;
use ShapeLMS\Core\Pages;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\ProgressService;

/**
 * Routes LMS requests to plugin templates and guards lesson access.
 */
class Frontend {

	/**
	 * Enrollment.
	 *
	 * @var EnrollmentService
	 */
	private $enrollment;

	/**
	 * Progress.
	 *
	 * @var ProgressService
	 */
	private $progress;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentService $enrollment Enrollment.
	 * @param ProgressService   $progress   Progress.
	 */
	public function __construct( EnrollmentService $enrollment, ProgressService $progress ) {
		$this->enrollment = $enrollment;
		$this->progress   = $progress;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'template_include', array( $this, 'template_include' ), 20 );
		add_action( 'template_redirect', array( $this, 'deny_item_listings' ), 0 );
		add_action( 'template_redirect', array( $this, 'guard_items' ) );
		add_action( 'template_redirect', array( $this, 'record_item_start' ) );
		add_action( 'template_redirect', array( $this, 'remember_checkout' ) );
		add_action( 'template_redirect', array( $this, 'auto_enroll_after_login' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'pre_get_posts', array( $this, 'archive_query' ) );
		add_action( 'pre_get_posts', array( $this, 'block_item_listings' ) );
		add_filter( 'shapelms_save_settings', array( ThemeCss::class, 'regenerate' ), 20 );
	}

	/**
	 * Whether this is an LMS page.
	 *
	 * @return bool
	 */
	public static function is_lms_page() {
		if ( is_singular( array_merge( array( PostTypes::COURSE ), Curriculum::item_post_types() ) ) ) {
			return true;
		}

		if ( is_post_type_archive( PostTypes::COURSE ) || is_tax( array( Taxonomies::CATEGORY, Taxonomies::TAG ) ) ) {
			return true;
		}

		foreach ( array( 'dashboard', 'checkout', 'instructor_register', 'login', 'register' ) as $key ) {
			if ( Pages::is_page( $key ) ) {
				return true;
			}
		}

		if ( is_singular() ) {
			foreach ( array( 'shapelms/course-grid', 'shapelms/dashboard', 'shapelms/checkout', 'shapelms/instructor-registration', 'shapelms/enroll-button', 'shapelms/login', 'shapelms/register' ) as $block ) {
				if ( has_block( $block ) ) {
					return true;
				}
			}
		}

		/**
		 * Filters whether the current request is an LMS page (loads assets).
		 *
		 * @param bool $is Whether it is an LMS page.
		 */
		return (bool) apply_filters( 'shapelms_is_lms_page', false );
	}

	/**
	 * Picks plugin templates for LMS views.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public function template_include( $template ) {
		$file = '';

		if ( is_singular( PostTypes::COURSE ) ) {
			$file = 'single-course.php';
		} elseif ( is_singular( Curriculum::item_post_types() ) ) {
			$file = 'learn.php';
		} elseif ( is_post_type_archive( PostTypes::COURSE ) || is_tax( array( Taxonomies::CATEGORY, Taxonomies::TAG ) ) ) {
			$file = 'archive-course.php';
		} elseif ( ( Pages::is_page( 'login' ) || Pages::is_page( 'register' ) ) && 'theme' !== shapelms_get_setting( 'general', 'auth_layout', 'standalone' ) ) {
			$file = 'auth-page.php';
		}

		if ( ! $file ) {
			return $template;
		}

		$theme = locate_template( 'shapelms/' . $file );

		return $theme ? $theme : SHAPELMS_DIR . 'templates/' . $file;
	}

	/**
	 * Active archive filters from the query string (sanitized, empty values dropped).
	 *
	 * @return array<string, string> Keys: s, level, price, orderby, category.
	 */
	public static function active_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filters.
		$filters = array(
			's'       => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'level'   => isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '',
			'price'   => isset( $_GET['price'] ) ? sanitize_key( wp_unslash( $_GET['price'] ) ) : '',
			'orderby' => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
		);
		// phpcs:enable

		$taxonomy = get_taxonomy( Taxonomies::CATEGORY );
		if ( $taxonomy && $taxonomy->query_var ) {
			$filters['category'] = sanitize_title( (string) get_query_var( (string) $taxonomy->query_var ) );
		}

		return array_filter(
			$filters,
			static function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * Redirects users who cannot access a lesson or quiz.
	 *
	 * @return void
	 */
	public function guard_items() {
		if ( ! is_singular( Curriculum::item_post_types() ) ) {
			return;
		}

		$item_id   = get_queried_object_id();
		$course_id = Curriculum::course_id_for( $item_id );

		if ( ! $course_id ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$user_id = get_current_user_id();

		if ( $user_id && $this->enrollment->can_access( $user_id, $course_id ) ) {
			if ( $this->progress->is_locked( $user_id, $course_id, $item_id ) ) {
				$resume = $this->progress->resume_item( $user_id, $course_id );
				if ( ! $resume || $resume === $item_id || $this->progress->is_locked( $user_id, $course_id, $resume ) ) {
					$resume = $course_id;
				}
				wp_safe_redirect( add_query_arg( 'shapelms_locked', '1', (string) get_permalink( $resume ) ) );
				exit;
			}
			return;
		}

		$is_preview = is_singular( PostTypes::LESSON ) && get_post_meta( $item_id, '_shapelms_preview', true );

		if ( $is_preview && 'publish' === get_post_status( $course_id ) ) {
			return;
		}

		if ( ! $user_id ) {
			wp_safe_redirect( shapelms_login_url( (string) get_permalink( $item_id ) ) );
			exit;
		}

		wp_safe_redirect( (string) get_permalink( $course_id ) );
		exit;
	}

	/**
	 * A logged-out visitor on the checkout page: remember it, so signing in from anywhere
	 * (even a theme menu link) brings them straight back to buy the course.
	 *
	 * @return void
	 */
	public function remember_checkout() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		if ( is_user_logged_in() || ! Pages::is_page( 'checkout' ) || empty( $_GET['course'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		Auth::remember_return( add_query_arg( 'course', sanitize_text_field( wp_unslash( $_GET['course'] ) ), Pages::get_url( 'checkout' ) ) );
	}

	/**
	 * Finishes a free enrollment the visitor started before signing in ("Log in to enroll").
	 *
	 * The course page is opened with ?shapelms_enroll=1 after login. Free courses enroll
	 * straight away and open the first lesson; paid courses go to checkout.
	 *
	 * @return void
	 */
	public function auto_enroll_after_login() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the visitor asked to enroll before logging in; only free, published courses are affected.
		if ( ! is_singular( PostTypes::COURSE ) || ! is_user_logged_in() || empty( $_GET['shapelms_enroll'] ) ) {
			return;
		}

		$course_id = get_queried_object_id();
		$user_id   = get_current_user_id();
		$clean_url = (string) get_permalink( $course_id );

		if ( $this->enrollment->can_access( $user_id, $course_id ) ) {
			wp_safe_redirect( $clean_url );
			exit;
		}

		if ( ! \ShapeLMS\Services\CourseService::pricing( $course_id )['is_free'] ) {
			wp_safe_redirect( add_query_arg( 'course', $course_id, Pages::get_url( 'checkout' ) ) );
			exit;
		}

		$result = $this->enrollment->enroll_free( $user_id, $course_id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( $clean_url );
			exit;
		}

		$next = $this->progress->resume_item( $user_id, $course_id );
		wp_safe_redirect( $next ? (string) get_permalink( $next ) : $clean_url );
		exit;
	}

	/**
	 * Records that the enrolled student opened the item.
	 *
	 * @return void
	 */
	public function record_item_start() {
		if ( is_singular( Curriculum::item_post_types() ) && is_user_logged_in() ) {
			$this->progress->start( get_current_user_id(), get_queried_object_id() );
		}
	}

	/**
	 * Enqueues front-end assets on LMS pages only.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::is_lms_page() ) {
			return;
		}

		wp_enqueue_style( 'shapelms-frontend', SHAPELMS_URL . 'assets/css/frontend.css', array(), SHAPELMS_VERSION );
		wp_style_add_data( 'shapelms-frontend', 'rtl', 'replace' );

		$custom = ThemeCss::url();
		if ( $custom ) {
			wp_enqueue_style( 'shapelms-theme', $custom, array( 'shapelms-frontend' ), ThemeCss::version() );
		}

		wp_enqueue_script( 'shapelms-frontend', SHAPELMS_URL . 'assets/js/frontend.js', array( 'wp-i18n' ), SHAPELMS_VERSION, true );
		wp_set_script_translations( 'shapelms-frontend', 'shapelms', SHAPELMS_DIR . 'languages' );

		/**
		 * Fires after front-end assets are enqueued on an LMS page.
		 */
		do_action( 'shapelms_enqueue_frontend' );
	}

	/**
	 * Data attributes for a front-end JS root (escaped). Replaces wp_localize_script.
	 *
	 * @param array<string, string> $extra Extra attributes (without the data- prefix).
	 * @return string
	 */
	public static function data_attributes( array $extra = array() ) {
		$data = array_merge(
			array(
				'rest-url'   => esc_url_raw( rest_url( 'shapelms/v1/' ) ),
				'rest-nonce' => wp_create_nonce( 'wp_rest' ),
				'login-url'  => shapelms_login_url( (string) get_permalink() ),
				'logged-in'  => is_user_logged_in() ? '1' : '0',
			),
			$extra
		);

		$out = '';
		foreach ( $data as $key => $value ) {
			$out .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
		}

		return $out;
	}

	/**
	 * Body classes.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( self::is_lms_page() ) {
			$classes[] = 'shapelms';
		}
		if ( is_singular( Curriculum::item_post_types() ) ) {
			$classes[] = 'shapelms-learn';
		}
		if ( Pages::is_page( 'login' ) || Pages::is_page( 'register' ) ) {
			$classes[] = 'shapelms-auth-page';
		}

		return $classes;
	}

	/**
	 * Lessons, quizzes, topics and questions are only ever viewed one at a time through the guarded
	 * single view: archives, feeds and ?post_type= listings would leak paid content.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function block_item_listings( $query ) {
		if ( is_admin() || ! $query->is_main_query() || $query->is_singular() ) {
			return;
		}

		$types = array_filter( (array) $query->get( 'post_type' ) );

		if ( array_intersect( $types, array_merge( array( PostTypes::TOPIC, PostTypes::QUESTION ), Curriculum::item_post_types() ) ) ) {
			$query->set( 'post__in', array( 0 ) );
			$query->set_404();
		}
	}

	/**
	 * Sends a real 404 for lesson/quiz/topic/question listings and feeds (core would answer 200 with no posts).
	 *
	 * @return void
	 */
	public function deny_item_listings() {
		if ( is_admin() || is_singular() ) {
			return;
		}

		$types = array_filter( (array) get_query_var( 'post_type' ) );

		if ( ! array_intersect( $types, array_merge( array( PostTypes::TOPIC, PostTypes::QUESTION ), Curriculum::item_post_types() ) ) ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		$template = get_404_template();
		if ( $template ) {
			include $template;
		}
		exit;
	}

	/**
	 * Filters and ordering on the course archive.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function archive_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_post_type_archive( PostTypes::COURSE ) && ! $query->is_tax( array( Taxonomies::CATEGORY, Taxonomies::TAG ) ) ) {
			return;
		}

		$query->set( 'posts_per_page', (int) shapelms_get_setting( 'general', 'courses_per_page', 12 ) );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$level   = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
		$price   = isset( $_GET['price'] ) ? sanitize_key( wp_unslash( $_GET['price'] ) ) : '';
		// phpcs:enable

		if ( 'title' === $orderby ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		} elseif ( 'price' === $orderby ) {
			$query->set( 'meta_key', '_shapelms_price' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'ASC' );
		} elseif ( 'popular' === $orderby ) {
			$query->set( 'orderby', 'comment_count' );
			$query->set( 'order', 'DESC' );
		}

		$meta = array();

		if ( $level && 'all' !== $level ) {
			$meta[] = array(
				'key'   => '_shapelms_level',
				'value' => $level,
			);
		}
		if ( 'free' === $price ) {
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_shapelms_price_type',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_shapelms_price_type',
					'value' => 'free',
				),
			);
		} elseif ( 'paid' === $price ) {
			$meta[] = array(
				'key'   => '_shapelms_price_type',
				'value' => 'paid',
			);
		}
		if ( $meta ) {
			$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
	}
}
