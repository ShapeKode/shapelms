<?php
/**
 * True / false question.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Quiz\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Quiz\Question;
use ShapeLMS\Quiz\QuestionTypeInterface;

/**
 * Correct value is "true" or "false".
 */
class TrueFalse implements QuestionTypeInterface {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'true_false';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'True / False', 'shapelms' );
	}

	/**
	 * Public data.
	 *
	 * @param Question $question Question.
	 * @return array<string, mixed>
	 */
	public function public_data( Question $question ) {
		return array(
			'options' => array(
				array(
					'id'   => 'true',
					'text' => __( 'True', 'shapelms' ),
				),
				array(
					'id'   => 'false',
					'text' => __( 'False', 'shapelms' ),
				),
			),
		);
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed    $raw      Raw.
	 * @param Question $question Question.
	 * @return string|null
	 */
	public function sanitize_answer( $raw, Question $question ) {
		$value = is_array( $raw ) ? (string) reset( $raw ) : (string) $raw;
		$value = strtolower( sanitize_key( $value ) );

		return in_array( $value, array( 'true', 'false' ), true ) ? $value : null;
	}

	/**
	 * Grade.
	 *
	 * @param mixed    $answer   Answer.
	 * @param Question $question Question.
	 * @return array{is_correct: bool, points: float, max_points: float}
	 */
	public function grade( $answer, Question $question ) {
		$expected = isset( $question->correct[0] ) ? strtolower( $question->correct[0] ) : 'true';
		$correct  = null !== $answer && $answer === $expected;

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
		return array( isset( $question->correct[0] ) ? strtolower( $question->correct[0] ) : 'true' );
	}
}
