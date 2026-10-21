<?php
/**
 * Question endpoints (builder).
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\Meta;
use ShapeLMS\Content\PostTypes;
use ShapeLMS\Quiz\Question;
use ShapeLMS\Quiz\QuestionTypes;
use WP_REST_Request;
use WP_REST_Server;

/**
 * CRUD for questions inside a quiz.
 */
class QuestionsController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'questions';

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'allow_course_editor' ),
				'args'                => array(
					'quiz_id' => $this->id_arg(),
					'type'    => array(
						'type'              => 'string',
						'default'           => 'single_choice',
						'sanitize_callback' => 'sanitize_key',
					),
					'title'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		$this->route(
			'/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'allow_course_editor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
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
					'quiz_id' => $this->id_arg(),
					'ids'     => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		$this->route(
			'/types',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'types' ),
				'permission_callback' => array( $this, 'allow_instructor' ),
			)
		);
	}

	/**
	 * Creates a question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$quiz_id = (int) $request->get_param( 'quiz_id' );

		if ( PostTypes::QUIZ !== get_post_type( $quiz_id ) ) {
			return $this->not_found( __( 'Quiz not found.', 'shapelms' ) );
		}

		$types = QuestionTypes::all();
		$type  = $request->get_param( 'type' );
		$type  = isset( $types[ $type ] ) ? $type : 'single_choice';

		$id = wp_insert_post(
			array(
				'post_type'   => PostTypes::QUESTION,
				'post_title'  => $request->get_param( 'title' ),
				'post_status' => 'publish',
				'post_parent' => $quiz_id,
				'post_author' => get_current_user_id(),
				'menu_order'  => count( \ShapeLMS\Services\QuizService::questions( $quiz_id ) ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $this->error( $id );
		}

		update_post_meta( $id, '_shapelms_type', $type );
		update_post_meta( $id, '_shapelms_points', 1 );

		if ( 'true_false' === $type ) {
			update_post_meta( $id, '_shapelms_correct', array( 'true' ) );
		}

		return $this->apply_fields( (int) $id, $request );
	}

	/**
	 * Updates a question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( PostTypes::QUESTION !== get_post_type( $id ) ) {
			return $this->not_found();
		}

		return $this->apply_fields( $id, $request );
	}

	/**
	 * Applies title/description/meta from the request.
	 *
	 * @param int             $id      Question id.
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function apply_fields( $id, WP_REST_Request $request ) {
		$update = array( 'ID' => $id );

		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'description' ) ) {
			$update['post_content'] = wp_kses_post( (string) $request->get_param( 'description' ) );
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $this->error( $result );
			}
		}

		$definitions = Meta::definitions()[ PostTypes::QUESTION ];
		$fields      = array( 'type', 'points', 'options', 'correct', 'explanation', 'settings' );

		foreach ( $fields as $field ) {
			if ( null === $request->get_param( $field ) ) {
				continue;
			}
			$key   = '_shapelms_' . $field;
			$value = $request->get_param( $field );
			$def   = $definitions[ $key ];

			if ( 'type' === $field && ! isset( QuestionTypes::all()[ $value ] ) ) {
				continue;
			}

			if ( isset( $def['sanitize'] ) ) {
				$value = call_user_func( $def['sanitize'], $value );
			} elseif ( 'number' === $def['type'] ) {
				$value = max( 0, (float) $value );
			}

			update_post_meta( $id, $key, $value );
		}

		clean_post_cache( $id );
		$question = Question::load( $id );

		return $question ? $question->to_editor_array() : $this->not_found();
	}

	/**
	 * Deletes a question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{deleted: bool}|\WP_Error
	 */
	public function delete( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( PostTypes::QUESTION !== get_post_type( $id ) ) {
			return $this->not_found();
		}

		return array( 'deleted' => (bool) wp_delete_post( $id, true ) );
	}

	/**
	 * Reorders questions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array<string, mixed>>
	 */
	public function reorder( WP_REST_Request $request ) {
		$quiz_id = (int) $request->get_param( 'quiz_id' );

		foreach ( (array) $request->get_param( 'ids' ) as $index => $id ) {
			$id = (int) $id;
			if ( PostTypes::QUESTION === get_post_type( $id ) && (int) get_post_field( 'post_parent', $id ) === $quiz_id ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'menu_order' => (int) $index,
					)
				);
			}
		}

		return array_map(
			static function ( $question ) {
				return $question->to_editor_array();
			},
			\ShapeLMS\Services\QuizService::questions( $quiz_id )
		);
	}

	/**
	 * Type labels.
	 *
	 * @return array<string, string>
	 */
	public function types() {
		return QuestionTypes::labels();
	}
}
