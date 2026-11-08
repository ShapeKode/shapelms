<?php
/**
 * Course endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Content\Meta;
use ShapeLMS\Content\PostTypes;
use ShapeLMS\Content\Taxonomies;
use ShapeLMS\Core\Capabilities;
use ShapeLMS\Services\CourseService;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\InstructorService;
use ShapeLMS\Services\ProgressService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Public course listing, builder save, and the admin course list.
 */
class CoursesController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'courses';

	/**
	 * Courses.
	 *
	 * @var CourseService
	 */
	private $courses;

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
	 * @param CourseService     $courses    Courses.
	 * @param EnrollmentService $enrollment Enrollment.
	 * @param ProgressService   $progress   Progress.
	 */
	public function __construct( CourseService $courses, EnrollmentService $enrollment, ProgressService $progress ) {
		$this->courses    = $courses;
		$this->enrollment = $enrollment;
		$this->progress   = $progress;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_courses' ),
					'permission_callback' => array( $this, 'allow_public' ),
					'args'                => array_merge(
						$this->pagination_args(),
						array(
							'category'  => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_text_field',
							),
							'tag'       => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_text_field',
							),
							'level'     => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_key',
							),
							'price'     => array(
								'type'    => 'string',
								'default' => '',
								'enum'    => array( '', 'free', 'paid' ),
							),
							'orderby'   => array(
								'type'    => 'string',
								'default' => 'date',
								'enum'    => array( 'date', 'title', 'price', 'popular' ),
							),
							'order'     => array(
								'type'    => 'string',
								'default' => 'DESC',
								'enum'    => array( 'ASC', 'DESC' ),
							),
							'status'    => array(
								'type'              => 'string',
								'default'           => 'publish',
								'sanitize_callback' => 'sanitize_key',
							),
							'mine'      => array(
								'type'    => 'boolean',
								'default' => false,
							),
							// Inclusive publish-date range, Y-m-d in site time.
							'date_from' => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => array( __CLASS__, 'sanitize_day' ),
							),
							'date_to'   => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => array( __CLASS__, 'sanitize_day' ),
							),
						)
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_course' ),
					'permission_callback' => array( $this, 'allow_instructor' ),
					'args'                => array(
						'title' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		$this->route(
			'/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course' ),
					'permission_callback' => array( $this, 'allow_public' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_course' ),
					'permission_callback' => array( $this, 'allow_course_editor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_course' ),
					'permission_callback' => array( $this, 'allow_course_editor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
			)
		);

		$this->route(
			'/(?P<id>\d+)/builder',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'builder_data' ),
				'permission_callback' => array( $this, 'allow_course_editor' ),
				'args'                => array( 'id' => $this->id_arg() ),
			)
		);

		$this->route(
			'/(?P<id>\d+)/progress',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'course_progress' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array( 'id' => $this->id_arg() ),
			)
		);

		$this->route(
			'/filters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'filter_options' ),
				'permission_callback' => array( $this, 'allow_instructor' ),
			)
		);

		$this->route(
			'/taxonomies',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'taxonomies' ),
				'permission_callback' => array( $this, 'allow_public' ),
			)
		);
	}

	/**
	 * Lists courses.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_courses( WP_REST_Request $request ) {
		$args = array(
			'search'   => $request->get_param( 'search' ),
			'category' => $request->get_param( 'category' ),
			'tag'      => $request->get_param( 'tag' ),
			'level'    => $request->get_param( 'level' ),
			'price'    => $request->get_param( 'price' ),
			'orderby'  => $request->get_param( 'orderby' ),
			'order'    => $request->get_param( 'order' ),
			'per_page' => $request->get_param( 'per_page' ),
			'page'      => $request->get_param( 'page' ),
			'status'    => 'publish',
			'date_from' => $request->get_param( 'date_from' ),
			'date_to'   => $request->get_param( 'date_to' ),
		);

		$can_edit = current_user_can( Capabilities::EDIT_COURSES );
		$own_ids  = static function () use ( $request ) {
			return 'trash' === $request->get_param( 'status' )
				? InstructorService::course_ids( get_current_user_id(), array( 'trash' ) )
				: InstructorService::course_ids( get_current_user_id() );
		};

		if ( $can_edit && 'publish' !== $request->get_param( 'status' ) ) {
			$args['status'] = in_array( $request->get_param( 'status' ), array( 'any', 'draft', 'pending', 'private', 'future', 'publish', 'trash' ), true ) ? $request->get_param( 'status' ) : 'publish';
			if ( 'any' === $args['status'] ) {
				$args['status'] = array( 'publish', 'future', 'draft', 'pending', 'private' );
			}
		}

		if ( $request->get_param( 'mine' ) && $can_edit ) {
			$args['ids'] = $own_ids();
			if ( empty( $args['ids'] ) ) {
				return $this->paginated( array(), 0, (int) $args['per_page'] );
			}
		} elseif ( $can_edit && ! current_user_can( Capabilities::MANAGE ) && 'publish' !== $args['status'] ) {
			$args['ids'] = $own_ids();
			if ( empty( $args['ids'] ) ) {
				return $this->paginated( array(), 0, (int) $args['per_page'] );
			}
		}

		$result = $this->courses->query( $args );

		if ( $can_edit ) {
			foreach ( $result['items'] as &$item ) {
				$item['edit_url'] = get_edit_post_link( $item['id'], 'raw' );
			}
		}

		return $this->paginated( $result['items'], $result['total'], (int) $args['per_page'] );
	}

	/**
	 * Single course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_course( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || PostTypes::COURSE !== $post->post_type ) {
			return $this->not_found();
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $id ) ) {
			return $this->not_found();
		}

		$data = $this->courses->to_array( $id, true );

		if ( is_user_logged_in() ) {
			$user_id          = get_current_user_id();
			$data['enrolled'] = $this->enrollment->is_enrolled( $user_id, $id );
			$data['can_edit'] = current_user_can( 'shapelms_edit_course', $id );
			if ( $data['enrolled'] ) {
				$data['progress']    = $this->progress->summary( $user_id, $id );
				$data['resume_item'] = $this->progress->resume_item( $user_id, $id );
			}
		}

		return $data;
	}

	/**
	 * Creates a draft course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_course( WP_REST_Request $request ) {
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => PostTypes::COURSE,
					'post_title'  => $request->get_param( 'title' ),
					'post_status' => 'draft',
					'post_author' => get_current_user_id(),
				)
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $this->error( $id );
		}

		$data             = $this->courses->to_array( (int) $id, true );
		$data['edit_url'] = get_edit_post_link( (int) $id, 'raw' );

		return $data;
	}

	/**
	 * Updates course fields, meta and terms from the builder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_course( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || PostTypes::COURSE !== $post->post_type ) {
			return $this->not_found();
		}

		// Only the course's author or a manager decides who else teaches it.
		$meta = $request->get_param( 'meta' );
		if ( is_array( $meta ) && ! $this->can_manage_instructors( $post ) ) {
			foreach ( $meta as $key => $value ) {
				// Same key normalisation as the save loop below, so no spelling slips past.
				if ( '_shapelms_instructors' !== '_shapelms_' . sanitize_key( str_replace( '_shapelms_', '', (string) $key ) ) ) {
					continue;
				}
				$current = array_map( 'intval', (array) get_post_meta( $id, '_shapelms_instructors', true ) );
				$wanted  = Meta::sanitize_int_list( $value );
				sort( $current );
				sort( $wanted );
				if ( $current !== $wanted ) {
					return new \WP_Error( 'shapelms_forbidden', __( 'Only the course author can change its instructors.', 'shapelms' ), array( 'status' => 403 ) );
				}
			}
		}

		if ( is_array( $meta ) ) {
			$product_error = $this->check_product_link( $id, $meta );
			if ( is_wp_error( $product_error ) ) {
				return $product_error;
			}
		}

		$update = array( 'ID' => $id );

		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'content' ) ) {
			$update['post_content'] = wp_kses_post( (string) $request->get_param( 'content' ) );
		}
		if ( null !== $request->get_param( 'excerpt' ) ) {
			$update['post_excerpt'] = sanitize_textarea_field( (string) $request->get_param( 'excerpt' ) );
		}
		if ( null !== $request->get_param( 'slug' ) ) {
			$update['post_name'] = sanitize_title( (string) $request->get_param( 'slug' ) );
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$status  = sanitize_key( (string) $request->get_param( 'status' ) );
			$allowed = array( 'draft', 'pending', 'publish', 'private' );
			if ( in_array( $status, $allowed, true ) ) {
				$can_publish = current_user_can( Capabilities::MANAGE ) || shapelms_get_setting( 'instructor', 'can_publish', false );
				if ( in_array( $status, array( 'publish', 'private' ), true ) && ! $can_publish ) {
					$status = 'pending';
				}
				// Core's restore also brings back the slug it changed when trashing.
				if ( 'trash' === $post->post_status ) {
					if ( ! current_user_can( 'delete_post', $id ) ) {
						return $this->forbidden();
					}
					wp_untrash_post( $id );
				}
				$update['post_status'] = $status;
			}
		}
		if ( null !== $request->get_param( 'visibility' ) ) {
			$update = $this->apply_visibility( $request, $post, $update );
			if ( is_wp_error( $update ) ) {
				return $update;
			}
		}
		if ( null !== $request->get_param( 'schedule' ) ) {
			$update = $this->apply_schedule( (string) $request->get_param( 'schedule' ), $post, $update );
			if ( is_wp_error( $update ) ) {
				return $update;
			}
		}
		// Core turns "publish" with a future date back into a schedule: publishing now means now.
		if ( 'publish' === ( $update['post_status'] ?? '' ) && strtotime( $post->post_date_gmt ) > time() ) {
			$update['post_date']     = current_time( 'mysql' );
			$update['post_date_gmt'] = current_time( 'mysql', true );
			$update['edit_date']     = true;
		}
		if ( null !== $request->get_param( 'image_id' ) ) {
			$image_id = absint( $request->get_param( 'image_id' ) );
			if ( $image_id ) {
				set_post_thumbnail( $id, $image_id );
			} else {
				delete_post_thumbnail( $id );
			}
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) ) {
				return $this->error( $result );
			}
		}

		if ( is_array( $meta ) ) {
			$definitions = Meta::definitions();
			foreach ( $meta as $key => $value ) {
				$key = '_shapelms_' . sanitize_key( str_replace( '_shapelms_', '', (string) $key ) );
				if ( ! isset( $definitions[ PostTypes::COURSE ][ $key ] ) ) {
					continue;
				}
				$field = $definitions[ PostTypes::COURSE ][ $key ];
				$value = isset( $field['sanitize'] ) ? call_user_func( $field['sanitize'], $value ) : $this->cast( $value, $field['type'] );
				update_post_meta( $id, $key, wp_slash( $value ) );
			}
		}

		$categories = $request->get_param( 'categories' );
		if ( is_array( $categories ) ) {
			wp_set_object_terms( $id, array_map( 'intval', $categories ), Taxonomies::CATEGORY );
		}
		$tags = $request->get_param( 'tags' );
		if ( is_array( $tags ) ) {
			wp_set_object_terms( $id, array_map( 'sanitize_text_field', array_map( 'strval', $tags ) ), Taxonomies::TAG );
		}

		Curriculum::flush( $id );
		clean_post_cache( $id );

		// Going paid or hidden turns public off, so going back later cannot reopen the lessons by surprise.
		if ( ! \ShapeLMS\Services\CourseService::pricing( $id )['is_free'] || 'public' !== \ShapeLMS\Services\CourseService::visibility( $id ) ) {
			delete_post_meta( $id, '_shapelms_public' );
		}

		/**
		 * Fires after a course was saved with its meta and terms (the selling modules hook here).
		 *
		 * @param int $id Course id.
		 */
		do_action( 'shapelms_course_saved', $id );

		return $this->builder_data( $request );
	}

	/**
	 * Adds the post fields a visibility change needs.
	 *
	 * @param WP_REST_Request      $request Request.
	 * @param \WP_Post             $post    Course.
	 * @param array<string, mixed> $update  Post fields so far.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function apply_visibility( WP_REST_Request $request, $post, array $update ) {
		$visibility = (string) $request->get_param( 'visibility' );

		if ( 'private' === $visibility ) {
			if ( ! current_user_can( Capabilities::MANAGE ) && ! shapelms_get_setting( 'instructor', 'can_publish', false ) ) {
				return new \WP_Error( 'shapelms_forbidden', __( 'You cannot publish courses, so you cannot make this one private.', 'shapelms' ), array( 'status' => 403 ) );
			}
			$update['post_status']   = 'private';
			$update['post_password'] = '';
			return $update;
		}

		if ( 'public' === $visibility ) {
			$update['post_password'] = '';
		} elseif ( 'password' === $visibility ) {
			$password = trim( sanitize_text_field( (string) $request->get_param( 'password' ) ) );
			if ( '' === $password || mb_strlen( $password ) > 255 ) {
				return new \WP_Error( 'shapelms_invalid_password', __( 'Enter a password of up to 255 characters.', 'shapelms' ), array( 'status' => 400 ) );
			}
			$update['post_password'] = $password;
		} else {
			return new \WP_Error( 'shapelms_invalid_visibility', __( 'Unknown visibility.', 'shapelms' ), array( 'status' => 400 ) );
		}

		// Leaving private must not put the course live by itself: it waits as a draft until published.
		if ( 'private' === ( $update['post_status'] ?? $post->post_status ) ) {
			$update['post_status'] = 'draft';
		}

		return $update;
	}

	/**
	 * Adds the post fields a schedule change needs.
	 *
	 * @param string               $value  Date in the site time zone (Y-m-d\TH:i), empty to cancel.
	 * @param \WP_Post             $post   Course.
	 * @param array<string, mixed> $update Post fields so far.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function apply_schedule( $value, $post, array $update ) {
		if ( '' === $value ) {
			if ( 'future' === ( $update['post_status'] ?? $post->post_status ) ) {
				$update['post_status'] = 'draft';
			}
			return $update;
		}

		if ( ! current_user_can( Capabilities::MANAGE ) && ! shapelms_get_setting( 'instructor', 'can_publish', false ) ) {
			return new \WP_Error( 'shapelms_forbidden', __( 'You cannot publish courses, so you cannot schedule this one.', 'shapelms' ), array( 'status' => 403 ) );
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		if ( ! $date || $date->getTimestamp() <= time() ) {
			return new \WP_Error( 'shapelms_invalid_schedule', __( 'Choose a date and time in the future.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$update['post_status']   = 'future';
		$update['post_date']     = $date->format( 'Y-m-d H:i:s' );
		$update['post_date_gmt'] = $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$update['edit_date']     = true;

		return $update;
	}

	/**
	 * Casts a scalar by type.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Type.
	 * @return mixed
	 */
	private function cast( $value, $type ) {
		switch ( $type ) {
			case 'integer':
				return absint( $value );
			case 'number':
				return (float) $value;
			case 'boolean':
				return rest_sanitize_boolean( $value );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Trashes a course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{deleted: bool}|\WP_Error
	 */
	public function delete_course( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( PostTypes::COURSE !== get_post_type( $id ) ) {
			return $this->not_found();
		}

		if ( ! current_user_can( 'delete_post', $id ) ) {
			return $this->forbidden();
		}

		return array( 'deleted' => (bool) wp_trash_post( $id ) );
	}

	/**
	 * Everything the builder needs for one course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function builder_data( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || PostTypes::COURSE !== $post->post_type ) {
			return $this->not_found();
		}

		$data = $this->courses->to_array( $id, false );
		$meta = array();
		foreach ( Meta::definitions()[ PostTypes::COURSE ] as $key => $field ) {
			$meta[ str_replace( '_shapelms_', '', $key ) ] = get_post_meta( $id, $key, true );
		}

		$data['meta']         = $meta;
		$data['category_ids'] = wp_get_object_terms( $id, Taxonomies::CATEGORY, array( 'fields' => 'ids' ) );
		$data['tag_names']    = wp_get_object_terms( $id, Taxonomies::TAG, array( 'fields' => 'names' ) );
		$data['curriculum']   = Curriculum::tree( $id, true );
		$data['can_publish']  = current_user_can( Capabilities::MANAGE ) || (bool) shapelms_get_setting( 'instructor', 'can_publish', false );
		$data['preview_url']  = get_preview_post_link( $post );
		$data['password']     = $post->post_password;
		$data['schedule']     = 'future' === $post->post_status ? mysql2date( 'Y-m-d\TH:i', $post->post_date, false ) : '';
		$data['site_now']     = current_datetime()->format( 'Y-m-d\TH:i' );
		$data['timezone']     = preg_replace( '/^([+-])/', 'UTC$1', wp_timezone_string() );

		$data['can_manage_instructors'] = $this->can_manage_instructors( $post );
		$data['can_change_status']      = true;

		/**
		 * Filters the data the course builder loads for one course.
		 *
		 * @param array<string, mixed> $data      Builder data.
		 * @param int                  $course_id Course id.
		 */
		return (array) apply_filters( 'shapelms_course_builder_data', $data, $id );
	}

	/**
	 * Whether the current user may change a course's co-instructors.
	 *
	 * @param \WP_Post $post Course.
	 * @return bool
	 */
	private function can_manage_instructors( $post ) {
		return current_user_can( Capabilities::MANAGE ) || get_current_user_id() === (int) $post->post_author;
	}

	/**
	 * Checks a change to the course's WooCommerce product. Saving copies the course's name, image
	 * and price onto that product, so the user must be able to edit it, and a product sells one
	 * course only.
	 *
	 * @param int                  $id   Course id.
	 * @param array<string, mixed> $meta Meta from the request.
	 * @return true|\WP_Error
	 */
	private function check_product_link( $id, array $meta ) {
		foreach ( $meta as $key => $value ) {
			if ( '_shapelms_woo_product_id' !== '_shapelms_' . sanitize_key( str_replace( '_shapelms_', '', (string) $key ) ) ) {
				continue;
			}

			$wanted  = absint( $value );
			$current = (int) get_post_meta( $id, '_shapelms_woo_product_id', true );

			if ( $wanted === $current ) {
				continue;
			}

			// Removing the link is judged on the product it leaves.
			$product = $wanted ? $wanted : $current;

			if ( 'product' !== get_post_type( $product ) ) {
				if ( $wanted ) {
					return new \WP_Error( 'shapelms_invalid_product', __( 'Invalid product.', 'shapelms' ), array( 'status' => 400 ) );
				}
				continue;
			}

			if ( ! current_user_can( 'edit_post', $product ) ) {
				return new \WP_Error( 'shapelms_forbidden', __( 'You are not allowed to use this product.', 'shapelms' ), array( 'status' => 403 ) );
			}

			$owner = $wanted ? (int) get_post_meta( $wanted, '_shapelms_course_id', true ) : 0;

			if ( $owner && $owner !== $id ) {
				return new \WP_Error( 'shapelms_product_linked', __( 'This product is already linked to another course.', 'shapelms' ), array( 'status' => 409 ) );
			}
		}

		return true;
	}

	/**
	 * Progress summary for the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function course_progress( WP_REST_Request $request ) {
		$id      = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		if ( ! $this->enrollment->can_access( $user_id, $id ) ) {
			return $this->forbidden();
		}

		$summary                = $this->progress->summary( $user_id, $id );
		$summary['resume_item'] = $this->progress->resume_item( $user_id, $id );

		return $summary;
	}

	/**
	 * Category and month choices for the admin course filters.
	 *
	 * @return array{categories: array<int, array{id: int, name: string, parent: int, count: int}>, months: array<int, array{value: string, label: string}>}
	 */
	public function filter_options() {
		global $wpdb;

		$categories = array();
		$terms      = get_terms(
			array(
				'taxonomy'   => Taxonomies::CATEGORY,
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = array(
					'id'     => (int) $term->term_id,
					'name'   => $term->name,
					'parent' => (int) $term->parent,
					'count'  => (int) $term->count,
				);
			}
		}

		// Months that have courses, like the "All dates" menu on core list tables.
		$where = $wpdb->prepare( "post_type = %s AND post_status NOT IN ('auto-draft', 'inherit')", PostTypes::COURSE );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			$ids = array_map( 'intval', InstructorService::course_ids( get_current_user_id(), array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ) ) );
			if ( ! $ids ) {
				return array(
					'categories' => $categories,
					'months'     => array(),
				);
			}
			$where .= ' AND ID IN (' . implode( ',', $ids ) . ')';
		}
		$rows = $wpdb->get_results( "SELECT DISTINCT YEAR( post_date ) AS y, MONTH( post_date ) AS m FROM {$wpdb->posts} WHERE {$where} ORDER BY post_date DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is prepared above; ids are integers.

		$months = array();
		foreach ( (array) $rows as $row ) {
			$value = sprintf( '%04d-%02d', (int) $row->y, (int) $row->m );
			if ( ! isset( $months[ $value ] ) ) {
				$months[ $value ] = array(
					'value' => $value,
					'label' => date_i18n( 'F Y', (int) mktime( 0, 0, 0, (int) $row->m, 1, (int) $row->y ) ),
				);
			}
		}

		return array(
			'categories' => $categories,
			'months'     => array_values( $months ),
		);
	}

	/**
	 * Keeps a Y-m-d date, anything else becomes empty.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_day( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ) ) ? $value : '';
	}

	/**
	 * Categories and tags for filters and the builder.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function taxonomies() {
		$map = array(
			'categories' => Taxonomies::CATEGORY,
			'tags'       => Taxonomies::TAG,
		);
		$out = array();

		foreach ( $map as $key => $taxonomy ) {
			$terms       = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			$out[ $key ] = array();
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$out[ $key ][] = array(
					'id'     => (int) $term->term_id,
					'name'   => $term->name,
					'slug'   => $term->slug,
					'parent' => (int) $term->parent,
					'count'  => (int) $term->count,
				);
			}
		}

		return $out;
	}
}
