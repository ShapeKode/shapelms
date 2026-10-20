<?php
/**
 * Single choice question.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Quiz\Types;

use ShapeLMS\Quiz\Question;
use ShapeLMS\Quiz\QuestionTypeInterface;

/**
 * One correct option.
 */
class SingleChoice implements QuestionTypeInterface {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'single_choice';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Single choice', 'shapelms' );
	}

	/**
	 * Public data.
	 *
	 * @param Question $question Question.
	 * @return array<string, mixed>
	 */
	public function public_data( Question $question ) {
		return array( 'options' => $question->public_options() );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed    $raw      Raw.
	 * @param Question $question Question.
	 * @return string|null
	 */
	public function sanitize_answer( $raw, Question $question ) {
		$value = sanitize_key( is_array( $raw ) ? (string) reset( $raw ) : (string) $raw );
		$ids   = wp_list_pluck( $question->options, 'id' );

		return in_array( $value, array_map( 'strval', $ids ), true ) ? $value : null;
	}

	/**
	 * Grade.
	 *
	 * @param mixed    $answer   Answer.
	 * @param Question $question Question.
	 * @return array{is_correct: bool, points: float, max_points: float}
	 */
	public function grade( $answer, Question $question ) {
		$correct = null !== $answer && in_array( (string) $answer, $question->correct, true );

		return array(
			'is_correct' => $correct,
			'points'     => $correct ? $question->points : 0.0,
			'max_points' => $question->points,
		);
	}

	/**
	 * Correct answer.
	 *
	 * @param Question $question Question.
	 * @return string[]
	 */
	public function correct_answer( Question $question ) {
		return $question->correct;
	}
}
