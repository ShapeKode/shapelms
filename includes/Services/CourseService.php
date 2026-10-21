<?php
/**
 * Course service.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Content\PostTypes;
use ShapeLMS\Content\Taxonomies;
use ShapeLMS\Database\EnrollmentRepository;

/**
 * Reads course data for templates and the API.
 */
class CourseService {

	/**
	 * Enrollments.
	 *
	 * @var EnrollmentRepository
	 */
	private $enrollments;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentRepository $enrollments Enrollments.
	 */
	public function __construct( EnrollmentRepository $enrollments ) {
		$this->enrollments = $enrollments;
	}

	/**
	 * Whether a post is a course.
	 *
	 * @param int|\WP_Post $course Course.
	 * @return bool
	 */
	public static function is_course( $course ) {
		$post = get_post( $course );

		return $post && PostTypes::COURSE === $post->post_type;
	}

	/**
	 * Price info.
	 *
	 * @param int $course_id Course id.
	 * @return array{type: string, price: float, sale_price: float, current: float, is_free: bool, on_sale: bool}
	 */
	public static function pricing( $course_id ) {
		$type  = (string) get_post_meta( $course_id, '_shapelms_price_type', true );
		$price = (float) get_post_meta( $course_id, '_shapelms_price', true );
		$sale  = (float) get_post_meta( $course_id, '_shapelms_sale_price', true );

		$is_free = 'paid' !== $type || $price <= 0;
		$on_sale = ! $is_free && $sale > 0 && $sale < $price;

		return array(
			'type'       => $is_free ? 'free' : 'paid',
			'price'      => $price,
			'sale_price' => $sale,
			'current'    => $is_free ? 0 : ( $on_sale ? $sale : $price ),
			'is_free'    => $is_free,
			'on_sale'    => $on_sale,
		);
	}

	/**
	 * Instructor user ids (course author plus co-instructors).
	 *
	 * @param int $course_id Course id.
	 * @return int[]
	 */
	public static function instructor_ids( $course_id ) {
		$author = (int) get_post_field( 'post_author', $course_id );
		$extra  = get_post_meta( $course_id, '_shapelms_instructors', true );
		$extra  = is_array( $extra ) ? array_map( 'intval', $extra ) : array();

		return array_values( array_unique( array_filter( array_merge( array( $author ), $extra ) ) ) );
	}

	/**
	 * Student count for a course.
	 *
	 * @param int $course_id Course id.
	 * @return int
	 */
	public function student_count( $course_id ) {
		$counts = $this->enrollments->counts_for_courses( array( $course_id ) );

		return isset( $counts[ $course_id ] ) ? $counts[ $course_id ] : 0;
	}

	/**
	 * Whether the course has reached its enrollment cap.
	 *
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public function is_full( $course_id ) {
		$max = (int) get_post_meta( $course_id, '_shapelms_max_students', true );

		return $max > 0 && $this->student_count( $course_id ) >= $max;
	}

	/**
	 * Average rating and count (from the reviews module, if enabled).
	 *
	 * @param int $course_id Course id.
	 * @return array{average: float, count: int}
	 */
	public static function rating( $course_id ) {
		/**
		 * Filters course rating data. The reviews module fills it in.
		 *
		 * @param array $rating    { average, count }.
		 * @param int   $course_id Course id.
		 */
		return apply_filters(
			'shapelms_course_rating',
			array(
				'average' => 0.0,
				'count'   => 0,
			),
			$course_id
		);
	}

	/**
	 * Full course data for templates and the public API.
	 *
	 * @param int  $course_id Course id.
	 * @param bool $with_tree Include the curriculum tree.
	 * @return array<string, mixed>|null
	 */
	public function to_array( $course_id, $with_tree = true ) {
		$post = get_post( $course_id );

		if ( ! $post || PostTypes::COURSE !== $post->post_type ) {
			return null;
		}

		$counts  = Curriculum::counts( $post->ID );
		$pricing = self::pricing( $post->ID );

		$data = array(
			'id'             => (int) $post->ID,
			'title'          => $post->post_title,
			'slug'           => $post->post_name,
			'status'         => $post->post_status,
			'excerpt'        => $post->post_excerpt,
			'content'        => $post->post_content,
			'url'            => get_permalink( $post ),
			'image'          => get_the_post_thumbnail_url( $post, 'large' ),
			'image_id'       => (int) get_post_thumbnail_id( $post ),
			'level'          => (string) get_post_meta( $post->ID, '_shapelms_level', true ),
			'duration'       => Curriculum::duration( $post->ID ),
			'requirements'   => (array) get_post_meta( $post->ID, '_shapelms_requirements', true ),
			'outcomes'       => (array) get_post_meta( $post->ID, '_shapelms_outcomes', true ),
			'audience'       => (array) get_post_meta( $post->ID, '_shapelms_audience', true ),
			'intro_video'    => (string) get_post_meta( $post->ID, '_shapelms_intro_video', true ),
			'featured'       => (bool) get_post_meta( $post->ID, '_shapelms_featured', true ),
			'max_students'   => (int) get_post_meta( $post->ID, '_shapelms_max_students', true ),
			'pricing'        => $pricing,
			'price_html'     => $pricing['is_free'] ? __( 'Free', 'shapelms' ) : shapelms_format_price( $pricing['current'] ),
			'lessons'        => $counts['lessons'],
			'quizzes'        => $counts['quizzes'],
			'topics'         => $counts['topics'],
			'students'       => $this->student_count( $post->ID ),
			'rating'         => self::rating( $post->ID ),
			'instructors'    => array_map( array( InstructorService::class, 'public_profile' ), self::instructor_ids( $post->ID ) ),
			'categories'     => $this->terms( $post->ID, Taxonomies::CATEGORY ),
			'tags'           => $this->terms( $post->ID, Taxonomies::TAG ),
			'date'           => get_post_time( 'c', true, $post ),
			'modified'       => get_post_modified_time( 'c', true, $post ),
			'woo_product_id' => (int) get_post_meta( $post->ID, '_shapelms_woo_product_id', true ),
		);

		if ( $with_tree ) {
			$data['curriculum'] = Curriculum::tree( $post->ID );
		}

		/**
		 * Filters the course array.
		 *
		 * @param array    $data Course data.
		 * @param \WP_Post $post Course post.
		 */
		return apply_filters( 'shapelms_course_data', $data, $post );
	}

	/**
	 * Term list for a course.
	 *
	 * @param int    $course_id Course id.
	 * @param string $taxonomy  Taxonomy.
	 * @return array<int, array{id: int, name: string, slug: string, url: string}>
	 */
	private function terms( $course_id, $taxonomy ) {
		$terms = get_the_terms( $course_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			static function ( $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
					'url'  => (string) get_term_link( $term ),
				);
			},
			$terms
		);
	}

	/**
	 * Queries published courses for the archive, blocks and API.
	 *
	 * @param array<string, mixed> $args { search, category, tag, level, price, orderby, per_page, page, instructor, ids }.
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int}
	 */
	public function query( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'search'     => '',
				'category'   => '',
				'tag'        => '',
				'level'      => '',
				'price'      => '',
				'orderby'    => 'date',
				'order'      => 'DESC',
				'per_page'   => (int) shapelms_get_setting( 'general', 'courses_per_page', 12 ),
				'page'       => 1,
				'instructor' => 0,
				'ids'        => array(),
				'status'     => 'publish',
			)
		);

		$query_args = array(
			'post_type'      => PostTypes::COURSE,
			'post_status'    => $args['status'],
			'posts_per_page' => max( 1, min( 100, (int) $args['per_page'] ) ),
			'paged'          => max( 1, (int) $args['page'] ),
			's'              => $args['search'],
			'order'          => 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC',
		);

		switch ( $args['orderby'] ) {
			case 'title':
				$query_args['orderby'] = 'title';
				break;
			case 'price':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_shapelms_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;
			case 'popular':
				$query_args['orderby'] = 'comment_count';
				break;
			case 'date':
			default:
				$query_args['orderby'] = 'date';
		}

		$tax_query = array();
		if ( $args['category'] ) {
			$tax_query[] = array(
				'taxonomy' => Taxonomies::CATEGORY,
				'field'    => is_numeric( $args['category'] ) ? 'term_id' : 'slug',
				'terms'    => $args['category'],
			);
		}
		if ( $args['tag'] ) {
			$tax_query[] = array(
				'taxonomy' => Taxonomies::TAG,
				'field'    => is_numeric( $args['tag'] ) ? 'term_id' : 'slug',
				'terms'    => $args['tag'],
			);
		}
		if ( $tax_query ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$meta_query = array();
		if ( $args['level'] ) {
			$meta_query[] = array(
				'key'   => '_shapelms_level',
				'value' => sanitize_key( $args['level'] ),
			);
		}
		if ( 'free' === $args['price'] ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_shapelms_price_type',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_shapelms_price_type',
					'value' => 'free',
				),
			);
		} elseif ( 'paid' === $args['price'] ) {
			$meta_query[] = array(
				'key'   => '_shapelms_price_type',
				'value' => 'paid',
			);
		}
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( $args['instructor'] ) {
			$query_args['author'] = (int) $args['instructor'];
		}
		if ( ! empty( $args['ids'] ) ) {
			$query_args['post__in'] = array_map( 'intval', (array) $args['ids'] );
			$query_args['orderby']  = 'post__in';
		}

		$query = new \WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = $this->to_array( $post->ID, false );
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}
}
