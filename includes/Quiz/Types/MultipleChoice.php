<?php
/**
 * Multiple choice question.
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
 * Several correct options. All must be selected for full marks; partial credit optional.
 */
class MultipleChoice implements QuestionTypeInterface {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'multiple_choice';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Multiple choice', 'shapelms' );
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
	 * @return string[]|null
	 */
	public function sanitize_answer( $raw, Question $question ) {
		$ids   = array_map( 'strval', wp_list_pluck( $question->options, 'id' ) );
		$clean = array();

		foreach ( (array) $raw as $value ) {
			$value = sanitize_key( (string) $value );
			if ( in_array( $value, $ids, true ) ) {
				$clean[] = $value;
			}
		}

		$clean = array_values( array_unique( $clean ) );
		sort( $clean );

		return $clean ? $clean : null;
	}

	/**
	 * Grade.
	 *
	 * @param mixed    $answer   Answer.
	 * @param Question $question Question.
	 * @return array{is_correct: bool, points: float, max_points: float}
	 */
	public function grade( $answer, Question $question ) {
		$answer  = is_array( $answer ) ? $answer : array();
		$correct = $question->correct;
		sort( $correct );

		$exact = $answer === $correct;

		if ( $exact ) {
			return array(
				'is_correct' => true,
				'points'     => $question->points,
				'max_points' => $question->points,
			);
		}

		$partial = ! empty( $question->settings['partial_credit'] );

		if ( $partial && $correct ) {
			$hits   = count( array_intersect( $answer, $correct ) );
			$wrong  = count( array_diff( $answer, $correct ) );
			$ratio  = max( 0, ( $hits - $wrong ) / count( $correct ) );
			$points = round( $question->points * $ratio, 2 );

			return array(
				'is_correct' => false,
				'points'     => (float) $points,
				'max_points' => $question->points,
			);
		}

		return array(
			'is_correct' => false,
			'points'     => 0.0,
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
