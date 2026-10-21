<?php
/**
 * Question value object.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Quiz;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\PostTypes;

/**
 * Wraps a question post and its meta.
 */
class Question {

	/**
	 * Post.
	 *
	 * @var \WP_Post
	 */
	public $post;

	/**
	 * Type id.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Points.
	 *
	 * @var float
	 */
	public $points;

	/**
	 * Options: [ { id, text, image } ].
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $options;

	/**
	 * Correct answer(s): option ids or accepted strings.
	 *
	 * @var string[]
	 */
	public $correct;

	/**
	 * Explanation.
	 *
	 * @var string
	 */
	public $explanation;

	/**
	 * Type-specific settings.
	 *
	 * @var array<string, mixed>
	 */
	public $settings;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post Question post.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post        = $post;
		$this->type        = (string) get_post_meta( $post->ID, '_shapelms_type', true );
		$this->type        = $this->type ? $this->type : 'single_choice';
		$this->points      = (float) get_post_meta( $post->ID, '_shapelms_points', true );
		$this->points      = $this->points > 0 ? $this->points : 1.0;
		$this->options     = (array) get_post_meta( $post->ID, '_shapelms_options', true );
		$this->correct     = array_map( 'strval', (array) get_post_meta( $post->ID, '_shapelms_correct', true ) );
		$this->explanation = (string) get_post_meta( $post->ID, '_shapelms_explanation', true );
		$this->settings    = (array) get_post_meta( $post->ID, '_shapelms_settings', true );
	}

	/**
	 * Loads a question by id.
	 *
	 * @param int $id Question id.
	 * @return Question|null
	 */
	public static function load( $id ) {
		$post = get_post( $id );

		if ( ! $post || PostTypes::QUESTION !== $post->post_type ) {
			return null;
		}

		return new self( $post );
	}

	/**
	 * Id.
	 *
	 * @return int
	 */
	public function id() {
		return (int) $this->post->ID;
	}

	/**
	 * Question text (title) and optional rich description (content).
	 *
	 * @return string
	 */
	public function text() {
		return $this->post->post_title;
	}

	/**
	 * Rich description.
	 *
	 * @return string
	 */
	public function description() {
		return $this->post->post_content;
	}

	/**
	 * Options without any hint of which is correct.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function public_options() {
		$list = array();

		foreach ( $this->options as $option ) {
			$list[] = array(
				'id'    => (string) ( $option['id'] ?? '' ),
				'text'  => (string) ( $option['text'] ?? '' ),
				'image' => ! empty( $option['image'] ) ? wp_get_attachment_image_url( (int) $option['image'], 'medium' ) : '',
			);
		}

		return $list;
	}

	/**
	 * Full editor data (for instructors).
	 *
	 * @return array<string, mixed>
	 */
	public function to_editor_array() {
		return array(
			'id'          => $this->id(),
			'type'        => $this->type,
			'title'       => $this->text(),
			'description' => $this->description(),
			'points'      => $this->points,
			'options'     => $this->options,
			'correct'     => $this->correct,
			'explanation' => $this->explanation,
			'settings'    => $this->settings,
			'order'       => (int) $this->post->menu_order,
		);
	}
}
