<?php
/**
 * Curriculum item types registered by add-ons.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\ProgressService;

/**
 * An add-on item type appears in the curriculum, counts toward completion and can be completed
 * only through complete_item().
 */
class ItemTypesTest extends ShapeLMS_TestCase {

	/**
	 * Registers a throwaway item type.
	 *
	 * @param array<string, mixed> $types Types.
	 * @return array<string, mixed>
	 */
	public static function add_type( $types ) {
		$types['exercise'] = array(
			'post_type' => 'unit_exercise',
			'label'     => 'Exercise',
			'icon'      => 'E',
		);
		// Built-ins cannot be overridden.
		$types['lesson'] = array( 'post_type' => 'unit_exercise' );

		return $types;
	}

	/**
	 * Registers the post type and the filter.
	 */
	public function set_up() {
		parent::set_up();
		register_post_type( 'unit_exercise', array( 'public' => false ) );
		add_filter( 'shapelms_curriculum_item_types', array( __CLASS__, 'add_type' ) );
		$this->reset_types();
	}

	/**
	 * Removes the filter.
	 */
	public function tear_down() {
		remove_filter( 'shapelms_curriculum_item_types', array( __CLASS__, 'add_type' ) );
		$this->reset_types();
		parent::tear_down();
	}

	/**
	 * Clears the registry cache.
	 *
	 * @return void
	 */
	private function reset_types() {
		$prop = new ReflectionProperty( Curriculum::class, 'types' );
		if ( PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true );
		}
		$prop->setValue( null, null );
	}

	/**
	 * Registry, curriculum listing, completion.
	 */
	public function test_addon_item_type() {
		$types = Curriculum::item_types();
		$this->assertSame( 'shapelms_lesson', $types['lesson']['post_type'] );
		$this->assertSame( 'unit_exercise', Curriculum::post_type_for( 'exercise' ) );
		$this->assertSame( 'exercise', Curriculum::type_of( 'unit_exercise' ) );

		$course  = $this->make_course();
		$topic   = Curriculum::tree( $course )[0]['id'];
		$item    = self::factory()->post->create(
			array(
				'post_type'   => 'unit_exercise',
				'post_parent' => $topic,
				'menu_order'  => 99,
			)
		);
		Curriculum::flush( $course );
		$student = $this->make_student();
		$this->service( EnrollmentService::class )->enroll_free( $student, $course );

		$listed = wp_list_pluck( Curriculum::items( $course ), 'type', 'id' );
		$this->assertSame( 'exercise', $listed[ $item ] );
		$this->assertSame( 3, Curriculum::counts( $course )['lessons'], 'Add-on items are not counted as lessons.' );

		$progress = $this->service( ProgressService::class );
		$this->assertWPError( $progress->complete_item( $student, $this->lessons( $course )[0] ), 'Lessons cannot be completed through complete_item().' );

		$before = $progress->summary( $student, $course )['total'];
		$result = $progress->complete_item( $student, $item );
		$this->assertIsArray( $result );
		$this->assertSame( 'completed', $progress->summary( $student, $course )['statuses'][ $item ] );
		$this->assertSame( $before, $progress->summary( $student, $course )['total'], 'The add-on item counts toward course completion.' );

		$progress->reopen_item( $student, $item );
		$this->assertNotSame( 'completed', $progress->summary( $student, $course )['statuses'][ $item ] ?? '' );

		$this->assertWPError( $progress->complete_item( $this->make_student(), $item ), 'Only enrolled students.' );
	}
}
