<?php
/**
 * Curriculum endpoints: topics, lessons, quizzes, ordering.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Content\Meta;
use ShapeLMS\Content\PostTypes;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Builder CRUD for the curriculum tree.
 */
class CurriculumController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'curriculum';

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'/topics',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_topic' ),
				'permission_callback' => array( $this, 'allow_course_editor' ),
				'args'                => array(
					'course_id' => $this->id_arg(),
					'title'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'summary'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'wp_kses_post',
					),
				),
			)
		);

		$this->route(
			'/topics/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_topic' ),
					'permission_callback' => array( $this, 'allow_course_editor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'allow_course_editor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
			)
		);

		$this->route(
			'/items',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'allow_course_editor' ),
				'args'                => array(
					'topic_id' => $this->id_arg(),
					'type'     => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'lesson', 'quiz' ),
					),
					'title'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		$this->route(
			'/items/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'allow_course_editor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'allow_course_editor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'allow_course_editor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
			)
		);

		$this->route(
			'/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reorder' ),
				'permission_callback' => array( $this, 'allow_course_editor' ),
				'args'                => array(
					'course_id' => $this->id_arg(),
					'tree'      => array(
						'type'     => 'array',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Creates a topic.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_topic( WP_REST_Request $request ) {
		$course_id = (int) $request->get_param( 'course_id' );

		if ( PostTypes::COURSE !== get_post_type( $course_id ) ) {
			return $this->not_found( __( 'Course not found.', 'shapelms' ) );
		}

		$id = wp_insert_post(
			array(
				'post_type'    => PostTypes::TOPIC,
				'post_title'   => $request->get_param( 'title' ),
				'post_content' => $request->get_param( 'summary' ),
				'post_status'  => 'publish',
				'post_parent'  => $course_id,
				'post_author'  => get_current_user_id(),
				'menu_order'   => count( Curriculum::tree( $course_id, true ) ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $this->error( $id );
		}

		Curriculum::flush( $course_id );

		return array(
			'id'      => (int) $id,
			'title'   => get_the_title( $id ),
			'summary' => get_post_field( 'post_content', $id ),
			'status'  => 'publish',
			'items'   => array(),
		);
	}

	/**
	 * Updates a topic.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_topic( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( PostTypes::TOPIC !== get_post_type( $id ) ) {
			return $this->not_found();
		}

		$update = array( 'ID' => $id );
		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'summary' ) ) {
			$update['post_content'] = wp_kses_post( (string) $request->get_param( 'summary' ) );
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		Curriculum::flush( Curriculum::course_id_for( $id ) );

		return array(
			'id'      => $id,
			'title'   => get_the_title( $id ),
			'summary' => get_post_field( 'post_content', $id ),
		);
	}

	/**
	 * Creates a lesson or quiz inside a topic.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$topic_id = (int) $request->get_param( 'topic_id' );

		if ( PostTypes::TOPIC !== get_post_type( $topic_id ) ) {
			return $this->not_found( __( 'Topic not found.', 'shapelms' ) );
		}

		$type      = 'quiz' === $request->get_param( 'type' ) ? PostTypes::QUIZ : PostTypes::LESSON;
		$course_id = Curriculum::course_id_for( $topic_id );
		$siblings  = get_posts(
			array(
				'post_type'     => array( PostTypes::LESSON, PostTypes::QUIZ ),
				'post_parent'   => $topic_id,
				'post_status'   => 'any',
				'numberposts'   => -1,
				'fields'        => 'ids',
				'no_found_rows' => true,
			)
		);

		$id = wp_insert_post(
			array(
				'post_type'   => $type,
				'post_title'  => $request->get_param( 'title' ),
				'post_status' => 'publish',
				'post_parent' => $topic_id,
				'post_author' => get_current_user_id(),
				'menu_order'  => count( $siblings ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $this->error( $id );
		}

		Curriculum::flush( $course_id );

		return $this->item_editor_data( (int) $id );
	}

	/**
	 * Full editor data for a lesson or quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( ! in_array( get_post_type( $id ), array( PostTypes::LESSON, PostTypes::QUIZ ), true ) ) {
			return $this->not_found();
		}

		return $this->item_editor_data( $id );
	}

	/**
	 * Updates a lesson or quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$id        = (int) $request->get_param( 'id' );
		$post_type = get_post_type( $id );

		if ( ! in_array( $post_type, array( PostTypes::LESSON, PostTypes::QUIZ ), true ) ) {
			return $this->not_found();
		}

		$update = array( 'ID' => $id );
		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'content' ) ) {
			$update['post_content'] = wp_kses_post( (string) $request->get_param( 'content' ) );
		}
		if ( null !== $request->get_param( 'status' ) && in_array( $request->get_param( 'status' ), array( 'publish', 'draft' ), true ) ) {
			$update['post_status'] = $request->get_param( 'status' );
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		$meta = $request->get_param( 'meta' );
		if ( is_array( $meta ) ) {
			$definitions = Meta::definitions()[ $post_type ];
			foreach ( $meta as $key => $value ) {
				$key = '_shapelms_' . sanitize_key( str_replace( '_shapelms_', '', (string) $key ) );
				if ( ! isset( $definitions[ $key ] ) ) {
					continue;
				}
				$field = $definitions[ $key ];
				if ( isset( $field['sanitize'] ) ) {
					$value = call_user_func( $field['sanitize'], $value );
				} elseif ( 'integer' === $field['type'] ) {
					$value = absint( $value );
				} elseif ( 'number' === $field['type'] ) {
					$value = (float) $value;
				} elseif ( 'boolean' === $field['type'] ) {
					$value = rest_sanitize_boolean( $value );
				} else {
					$value = sanitize_text_field( (string) $value );
				}
				update_post_meta( $id, $key, $value );
			}
		}

		Curriculum::flush( Curriculum::course_id_for( $id ) );
		clean_post_cache( $id );

		return $this->item_editor_data( $id );
	}

	/**
	 * Deletes a topic, lesson or quiz permanently (children cascade).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{deleted: bool}|\WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( ! in_array( get_post_type( $id ), array( PostTypes::TOPIC, PostTypes::LESSON, PostTypes::QUIZ ), true ) ) {
			return $this->not_found();
		}

		$course_id = Curriculum::course_id_for( $id );
		$deleted   = wp_delete_post( $id, true );
		Curriculum::flush( $course_id );

		return array( 'deleted' => (bool) $deleted );
	}

	/**
	 * Saves the order: tree = [ { id, items: [ id, id ] }, ... ].
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function reorder( WP_REST_Request $request ) {
		$course_id = (int) $request->get_param( 'course_id' );
		$tree      = (array) $request->get_param( 'tree' );

		foreach ( $tree as $topic_index => $topic ) {
			$topic_id = isset( $topic['id'] ) ? (int) $topic['id'] : 0;

			if ( PostTypes::TOPIC !== get_post_type( $topic_id ) || Curriculum::course_id_for( $topic_id ) !== $course_id ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'         => $topic_id,
					'menu_order' => (int) $topic_index,
				)
			);

			$items = isset( $topic['items'] ) ? (array) $topic['items'] : array();
			foreach ( $items as $item_index => $item_id ) {
				$item_id = (int) $item_id;
				if ( ! in_array( get_post_type( $item_id ), array( PostTypes::LESSON, PostTypes::QUIZ ), true ) || Curriculum::course_id_for( $item_id ) !== $course_id ) {
					continue;
				}
				wp_update_post(
					array(
						'ID'          => $item_id,
						'post_parent' => $topic_id,
						'menu_order'  => (int) $item_index,
					)
				);
			}
		}

		Curriculum::flush( $course_id );

		return Curriculum::tree( $course_id, true );
	}

	/**
	 * Editor payload for an item.
	 *
	 * @param int $id Item id.
	 * @return array<string, mixed>
	 */
	private function item_editor_data( $id ) {
		$post = get_post( $id );
		$meta = array();

		foreach ( Meta::definitions()[ $post->post_type ] as $key => $field ) {
			$meta[ str_replace( '_shapelms_', '', $key ) ] = get_post_meta( $id, $key, true );
		}

		$data = array_merge(
			Curriculum::item_to_array( $post ),
			array(
				'content'  => $post->post_content,
				'topic_id' => (int) $post->post_parent,
				'meta'     => $meta,
			)
		);

		if ( PostTypes::LESSON === $post->post_type && ! empty( $meta['attachments'] ) ) {
			$data['attachment_files'] = array();
			foreach ( (array) $meta['attachments'] as $attachment_id ) {
				$file                       = get_attached_file( (int) $attachment_id );
				$data['attachment_files'][] = array(
					'id'    => (int) $attachment_id,
					'title' => get_the_title( (int) $attachment_id ),
					'url'   => wp_get_attachment_url( (int) $attachment_id ),
					'size'  => $file && file_exists( $file ) ? size_format( (int) filesize( $file ) ) : '',
				);
			}
		}

		if ( PostTypes::QUIZ === $post->post_type ) {
			$data['questions']      = array_map(
				static function ( $question ) {
					return $question->to_editor_array();
				},
				\ShapeLMS\Services\QuizService::questions( $id )
			);
			$data['question_types'] = \ShapeLMS\Quiz\QuestionTypes::labels();
		}

		return $data;
	}
}
