<?php
/**
 * Question type interface.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Quiz;

/**
 * A question type knows how to present a question safely and grade an answer.
 */
interface QuestionTypeInterface {

	/**
	 * Type id (slug).
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Data safe to send to a student (no correct answers).
	 *
	 * @param Question $question Question.
	 * @return array<string, mixed>
	 */
	public function public_data( Question $question );

	/**
	 * Sanitizes a raw submitted answer.
	 *
	 * @param mixed    $raw      Raw answer from the request.
	 * @param Question $question Question.
	 * @return mixed Clean answer, or null when empty.
	 */
	public function sanitize_answer( $raw, Question $question );

	/**
	 * Grades an answer.
	 *
	 * @param mixed    $answer   Clean answer.
	 * @param Question $question Question.
	 * @return array{is_correct: bool|null, points: float, max_points: float}
	 */
	public function grade( $answer, Question $question );

	/**
	 * Correct answer shown in feedback.
	 *
	 * @param Question $question Question.
	 * @return mixed
	 */
	public function correct_answer( Question $question );
}
