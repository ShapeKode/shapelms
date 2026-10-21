<?php
/**
 * Question type registry.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Quiz;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the available question types. Pro adds more through the filter.
 */
class QuestionTypes {

	/**
	 * Types keyed by id.
	 *
	 * @var array<string, QuestionTypeInterface>|null
	 */
	private static $types = null;

	/**
	 * Returns all types.
	 *
	 * @return array<string, QuestionTypeInterface>
	 */
	public static function all() {
		if ( null !== self::$types ) {
			return self::$types;
		}

		$types = array(
			new Types\SingleChoice(),
			new Types\MultipleChoice(),
			new Types\TrueFalse(),
			new Types\ShortAnswer(),
			new Types\FillBlank(),
		);

		/**
		 * Filters the question types.
		 *
		 * @param QuestionTypeInterface[] $types Types.
		 */
		$types = apply_filters( 'shapelms_question_types', $types );

		self::$types = array();
		foreach ( $types as $type ) {
			if ( $type instanceof QuestionTypeInterface ) {
				self::$types[ $type->id() ] = $type;
			}
		}

		return self::$types;
	}

	/**
	 * Returns one type (falls back to single choice).
	 *
	 * @param string $id Type id.
	 * @return QuestionTypeInterface
	 */
	public static function get( $id ) {
		$all = self::all();

		return isset( $all[ $id ] ) ? $all[ $id ] : $all['single_choice'];
	}

	/**
	 * Id => label map for the builder.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		$labels = array();
		foreach ( self::all() as $id => $type ) {
			$labels[ $id ] = $type->label();
		}

		return $labels;
	}
}
