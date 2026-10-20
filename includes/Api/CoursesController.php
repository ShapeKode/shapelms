<?php
/**
 * Course endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

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
							'category' => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_text_field',
							),
							'tag'      => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_text_field',
							),
							'level'    => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_key',
							),
							'price'    => array(
								'type'    => 'string',
								'default' => '',
								'enum'    => array( '', 'free', 'paid' ),
							),
							'orderby'  => array(
								'type'    => 'string',
								'default' => 'date',
								'enum'    => array( 'date', 'title', 'price', 'popular' ),
							),
							'order'    => array(
								'type'    => 'string',
								'default' => 'DESC',
								'enum'    => array( 'ASC', 'DESC' ),
							),
							'status'   => array(
								'type'              => 'string',
								'default'           => 'publish',
								'sanitize_callback' => 'sanitize_key',
							),
							'mine'     => array(
								'type'    => 'boolean',
								'default' => false,
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
			'page'     => $request->get_param( 'page' ),
			'status'   => 'publish',
		);

		$can_edit = current_user_can( Capabilities::EDIT_COURSES );

		if ( $can_edit && 'publish' !== $request->get_param( 'status' ) ) {
			$args['status'] = in_array( $request->get_param( 'status' ), array( 'any', 'draft', 'pending', 'private', 'publish' ), true ) ? $request->get_param( 'status' ) : 'publish';
			if ( 'any' === $args['status'] ) {
				$args['status'] = array( 'publish', 'draft', 'pending', 'private' );
			}
		}

		if ( $request->get_param( 'mine' ) && $can_edit ) {
			$args['ids'] = InstructorService::course_ids( get_current_user_id() );
			if ( empty( $args['ids'] ) ) {
				return $this->paginated( array(), 0, (int) $args['per_page'] );
			}
		} elseif ( $can_edit && ! current_user_can( Capabilities::MANAGE ) && 'publish' !== $args['status'] ) {
			$args['ids'] = InstructorService::course_ids( get_current_user_id() );
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

		if ( 'publish' !== $post->post_status && ! current_user_can( 'shapelms_edit_course', $id ) ) {
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
			array(
				'post_type'   => PostTypes::COURSE,
				'post_title'  => $request->get_param( 'title' ),
				'post_status' => 'draft',
				'post_author' => get_current_user_id(),
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
				$update['post_status'] = $status;
			}
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
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $this->error( $result );
			}
		}

		$meta = $request->get_param( 'meta' );
		if ( is_array( $meta ) ) {
			$definitions = Meta::definitions();
			foreach ( $meta as $key => $value ) {
				$key = '_shapelms_' . sanitize_key( str_replace( '_shapelms_', '', (string) $key ) );
				if ( ! isset( $definitions[ PostTypes::COURSE ][ $key ] ) ) {
					continue;
				}
				$field = $definitions[ PostTypes::COURSE ][ $key ];
				$value = isset( $field['sanitize'] ) ? call_user_func( $field['sanitize'], $value ) : $this->cast( $value, $field['type'] );
				update_post_meta( $id, $key, $value );
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

		return $this->builder_data( $request );
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

		return $data;
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
