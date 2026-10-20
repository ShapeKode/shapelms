<?php
/**
 * Fill in the blanks question.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Quiz\Types;

use ShapeLMS\Quiz\Question;
use ShapeLMS\Quiz\QuestionTypeInterface;

/**
 * The question description contains {blank} placeholders; correct holds one accepted answer per blank
 * (alternatives separated by "|").
 */
class FillBlank implements QuestionTypeInterface {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'fill_blank';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Fill in the blanks', 'shapelms' );
	}

	/**
	 * Number of blanks in the text.
	 *
	 * @param Question $question Question.
	 * @return int
	 */
	public function blank_count( Question $question ) {
		return max( 1, (int) preg_match_all( '/\{blank\}/i', $question->description() ) );
	}

	/**
	 * Public data: text split into parts around the blanks.
	 *
	 * @param Question $question Question.
	 * @return array<string, mixed>
	 */
	public function public_data( Question $question ) {
		return array(
			'parts'  => preg_split( '/\{blank\}/i', wp_kses_post( $question->description() ) ),
			'blanks' => $this->blank_count( $question ),
		);
	}

	/**
	 * Sanitize: list of strings, one per blank.
	 *
	 * @param mixed    $raw      Raw.
	 * @param Question $question Question.
	 * @return string[]|null
	 */
	public function sanitize_answer( $raw, Question $question ) {
		$count = $this->blank_count( $question );
		$raw   = is_array( $raw ) ? array_values( $raw ) : array( $raw );
		$clean = array();
		$empty = true;

		for ( $i = 0; $i < $count; $i++ ) {
			$value   = isset( $raw[ $i ] ) ? sanitize_text_field( (string) $raw[ $i ] ) : '';
			$clean[] = mb_substr( $value, 0, 200 );
			$empty   = $empty && '' === trim( $value );
		}

		return $empty ? null : $clean;
	}

	/**
	 * Grade: each blank is worth an equal share.
	 *
	 * @param mixed    $answer   Answer.
	 * @param Question $question Question.
	 * @return array{is_correct: bool, points: float, max_points: float}
	 */
	public function grade( $answer, Question $question ) {
		$count          = $this->blank_count( $question );
		$answer         = is_array( $answer ) ? $answer : array();
		$case_sensitive = ! empty( $question->settings['case_sensitive'] );
		$hits           = 0;

		for ( $i = 0; $i < $count; $i++ ) {
			$accepted = isset( $question->correct[ $i ] ) ? explode( '|', $question->correct[ $i ] ) : array();
			$given    = isset( $answer[ $i ] ) ? ShortAnswer::normalize( (string) $answer[ $i ], $case_sensitive ) : '';

			foreach ( $accepted as $option ) {
				if ( '' !== $given && ShortAnswer::normalize( $option, $case_sensitive ) === $given ) {
					++$hits;
					break;
				}
			}
		}

		$points = $count > 0 ? round( $question->points * ( $hits / $count ), 2 ) : 0;

		return array(
			'is_correct' => $hits === $count,
			'points'     => (float) $points,
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
