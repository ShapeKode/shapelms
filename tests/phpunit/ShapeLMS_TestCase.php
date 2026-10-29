<?php
/**
 * Base test case with course and user fixtures.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Core\Settings;
use ShapeLMS\Database\Migrator;
use ShapeLMS\Services\ImportExportService;

/**
 * Shared fixtures for service tests.
 */
abstract class ShapeLMS_TestCase extends WP_UnitTestCase {

	/**
	 * Creates the custom tables once.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		Migrator::install();
	}

	/**
	 * Resets per-request caches.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		Curriculum::flush();
		delete_option( 'shapelms_settings' );
		Settings::flush();
	}

	/**
	 * Resolves a service from the container.
	 *
	 * @param string $id Class name.
	 * @return mixed
	 */
	protected function service( $id ) {
		return shapelms()->get( $id );
	}

	/**
	 * Creates a student user.
	 *
	 * @return int
	 */
	protected function make_student() {
		return (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Creates an instructor user who authors the courses.
	 *
	 * @return int
	 */
	protected function make_instructor() {
		return (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Creates a published course with two topics, three lessons and one quiz.
	 *
	 * Structure: Topic A [ lesson 1, lesson 2 ], Topic B [ quiz, lesson 3 ].
	 *
	 * @param array<string, mixed> $overrides Course fields/meta to override.
	 * @return int Course id.
	 */
	protected function make_course( array $overrides = array() ) {
		$course = array_replace_recursive(
			array(
				'title'   => 'Test course',
				'status'  => 'publish',
				'excerpt' => 'Excerpt',
				'content' => '<p>Body</p>',
				'meta'    => array(
					'level'      => 'beginner',
					'price_type' => 'free',
					'price'      => 0,
				),
				'topics'  => array(
					array(
						'title' => 'Topic A',
						'items' => array(
							array(
								'type'    => 'lesson',
								'title'   => 'Lesson 1',
								'content' => '<p>One</p>',
								'meta'    => array( 'preview' => true ),
							),
							array(
								'type'    => 'lesson',
								'title'   => 'Lesson 2',
								'content' => '<p>Two</p>',
							),
						),
					),
					array(
						'title' => 'Topic B',
						'items' => array(
							array(
								'type'      => 'quiz',
								'title'     => 'Quiz',
								'meta'      => array(
									'pass_mark'        => 50,
									'attempts_allowed' => 0,
									'time_limit'       => 0,
									'feedback_mode'    => 'instant',
								),
								'questions' => array(
									array(
										'title'   => 'Single',
										'type'    => 'single_choice',
										'points'  => 1,
										'options' => array(
											array(
												'id'   => 'a',
												'text' => 'Right',
											),
											array(
												'id'   => 'b',
												'text' => 'Wrong',
											),
										),
										'correct' => array( 'a' ),
									),
									array(
										'title'   => 'True or false',
										'type'    => 'true_false',
										'points'  => 1,
										'options' => array(),
										'correct' => array( 'true' ),
									),
								),
							),
							array(
								'type'    => 'lesson',
								'title'   => 'Lesson 3',
								'content' => '<p>Three</p>',
							),
						),
					),
				),
			),
			$overrides
		);

		$ids = $this->service( ImportExportService::class )->import( array( 'courses' => array( $course ) ), $this->make_instructor() );

		$this->assertIsArray( $ids );
		$this->assertCount( 1, $ids );

		Curriculum::flush();

		return (int) $ids[0];
	}

	/**
	 * Ordered item ids of a course.
	 *
	 * @param int $course_id Course id.
	 * @return int[]
	 */
	protected function items( $course_id ) {
		return Curriculum::item_ids( $course_id );
	}

	/**
	 * Lesson ids of a course in order.
	 *
	 * @param int $course_id Course id.
	 * @return int[]
	 */
	protected function lessons( $course_id ) {
		$ids = array();
		foreach ( Curriculum::items( $course_id ) as $item ) {
			if ( 'lesson' === $item['type'] ) {
				$ids[] = (int) $item['id'];
			}
		}

		return $ids;
	}

	/**
	 * First quiz id of a course.
	 *
	 * @param int $course_id Course id.
	 * @return int
	 */
	protected function quiz( $course_id ) {
		foreach ( Curriculum::items( $course_id ) as $item ) {
			if ( 'quiz' === $item['type'] ) {
				return (int) $item['id'];
			}
		}

		return 0;
	}

	/**
	 * Writes one plugin setting.
	 *
	 * @param string $section Section.
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @return void
	 */
	protected function set_setting( $section, $key, $value ) {
		$settings = get_option( 'shapelms_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$settings[ $section ][ $key ] = $value;
		update_option( 'shapelms_settings', $settings );
		Settings::flush();
	}
}
