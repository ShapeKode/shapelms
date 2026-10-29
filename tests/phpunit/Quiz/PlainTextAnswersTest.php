<?php
/**
 * Quiz options and text answers are plain text, so literal markup like "<h1>" survives.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Content\Meta;
use ShapeLMS\Quiz\Question;
use ShapeLMS\Quiz\QuestionTypes;

/**
 * Plain-text quiz content.
 */
class PlainTextAnswersTest extends ShapeLMS_TestCase {

	/**
	 * Literal tags in option text are kept; control characters are removed.
	 */
	public function test_option_text_keeps_literal_markup() {
		$clean = Meta::sanitize_options(
			array(
				array(
					'id'   => 'a',
					'text' => '<h1>',
				),
				array(
					'id'   => 'b',
					'text' => "Tom & Jerry\x00\n<script>alert(1)</script>",
				),
			)
		);

		$this->assertSame( '<h1>', $clean[0]['text'] );
		$this->assertSame( 'Tom & Jerry <script>alert(1)</script>', $clean[1]['text'] );
	}

	/**
	 * Accepted answers keep literal markup and drop empty or non-scalar items.
	 */
	public function test_answer_list_keeps_literal_markup() {
		$this->assertSame( array( '<h1>', 'a < b' ), Meta::sanitize_answer_list( array( '<h1>', ' ', array( 'x' ), 'a < b' ) ) );
	}

	/**
	 * A short-answer question about markup can be answered and graded.
	 */
	public function test_short_answer_with_markup_is_graded() {
		$id = self::factory()->post->create(
			array(
				'post_type'  => 'shapelms_question',
				'post_title' => 'Which tag is the largest heading?',
			)
		);
		update_post_meta( $id, '_shapelms_type', 'short_answer' );
		update_post_meta( $id, '_shapelms_points', 1 );
		update_post_meta( $id, '_shapelms_correct', Meta::sanitize_answer_list( array( '<h1>' ) ) );

		$question = new Question( get_post( $id ) );
		$type     = QuestionTypes::get( 'short_answer' );

		$this->assertSame( array( '<h1>' ), $question->correct );

		$answer = $type->sanitize_answer( '<h1>', $question );
		$this->assertSame( '<h1>', $answer );
		$this->assertTrue( $type->grade( $answer, $question )['is_correct'] );

		$this->assertNull( $type->sanitize_answer( '   ', $question ) );
		$this->assertFalse( $type->grade( $type->sanitize_answer( 'h1', $question ), $question )['is_correct'] );
	}
}
