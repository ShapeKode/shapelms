<?php
/**
 * Short answer question.
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
 * Text answer compared against a list of accepted answers.
 */
class ShortAnswer implements QuestionTypeInterface {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'short_answer';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Short answer', 'shapelms' );
	}

	/**
	 * Public data.
	 *
	 * @param Question $question Question.
	 * @return array<string, mixed>
	 */
	public function public_data( Question $question ) {
		return array(
			'max_length' => isset( $question->settings['max_length'] ) ? (int) $question->settings['max_length'] : 200,
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
		$value = shapelms_sanitize_plain_text( is_array( $raw ) ? (string) reset( $raw ) : (string) $raw );
		$max   = isset( $question->settings['max_length'] ) ? (int) $question->settings['max_length'] : 200;
		$value = mb_substr( $value, 0, max( 1, $max ) );

		return '' === trim( $value ) ? null : $value;
	}

	/**
	 * Grade.
	 *
	 * @param mixed    $answer   Answer.
	 * @param Question $question Question.
	 * @return array{is_correct: bool, points: float, max_points: float}
	 */
	public function grade( $answer, Question $question ) {
		$correct = false;

		if ( null !== $answer ) {
			$case_sensitive = ! empty( $question->settings['case_sensitive'] );
			$given          = self::normalize( (string) $answer, $case_sensitive );

			foreach ( $question->correct as $accepted ) {
				if ( self::normalize( $accepted, $case_sensitive ) === $given ) {
					$correct = true;
					break;
				}
			}
		}

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

	/**
	 * Normalizes whitespace and case.
	 *
	 * @param string $value          Value.
	 * @param bool   $case_sensitive Keep case.
	 * @return string
	 */
	public static function normalize( $value, $case_sensitive = false ) {
		$value = trim( preg_replace( '/\s+/u', ' ', $value ) );

		return $case_sensitive ? $value : mb_strtolower( $value );
	}
}
