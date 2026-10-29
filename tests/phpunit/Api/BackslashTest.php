<?php
/**
 * Backslashes in titles, content and question meta survive saving.
 *
 * WordPress unslashes post and meta data on save, so callers must slash it first.
 * LaTeX (\frac, \( … \)) and Windows paths depend on this.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Services\QuizService;

/**
 * Import and REST saves keep backslashes.
 */
class BackslashTest extends ShapeLMS_TestCase {

	/**
	 * Quiz id of a course made by make_course().
	 *
	 * @param int $course_id Course id.
	 * @return int
	 */
	private function quiz_of( $course_id ) {
		foreach ( $this->items( $course_id ) as $id ) {
			if ( 'shapelms_quiz' === get_post_type( $id ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * Import keeps backslashes in titles, content and options.
	 */
	public function test_import_keeps_backslashes() {
		$course_id = $this->make_course(
			array(
				'title'   => 'Maths \\( x^2 \\)',
				'content' => '<p>See C:\\temp and \\frac{1}{2}</p>',
			)
		);

		$this->assertSame( 'Maths \\( x^2 \\)', get_post( $course_id )->post_title );
		$this->assertStringContainsString( 'C:\\temp and \\frac{1}{2}', get_post( $course_id )->post_content );
	}

	/**
	 * Updating a question over REST keeps backslashes in the title and options.
	 */
	public function test_rest_question_update_keeps_backslashes() {
		$course_id = $this->make_course();
		$quiz_id   = $this->quiz_of( $course_id );
		$question  = QuizService::questions( $quiz_id )[0];

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'PUT', '/shapelms/v1/questions/' . $question->id() );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'title'   => 'What is \\( \\frac{3}{4} \\)?',
					'options' => array(
						array(
							'id'   => 'a',
							'text' => '\\( 0.75 \\)',
						),
						array(
							'id'   => 'b',
							'text' => '\\( 0.5 \\)',
						),
					),
				)
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		clean_post_cache( $question->id() );
		$this->assertSame( 'What is \\( \\frac{3}{4} \\)?', get_post( $question->id() )->post_title );
		$options = get_post_meta( $question->id(), '_shapelms_options', true );
		$this->assertSame( '\\( 0.75 \\)', $options[0]['text'] );
	}
}
