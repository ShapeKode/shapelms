<?php
/**
 * Course import / export.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Content\Meta;
use ShapeLMS\Content\PostTypes;
use ShapeLMS\Content\Taxonomies;

/**
 * Exports courses (with curriculum and questions) to arrays and imports them back.
 */
class ImportExportService {

	const FORMAT = 1;

	/**
	 * Exports courses.
	 *
	 * @param int[] $ids Course ids (empty = all).
	 * @return array<string, mixed>
	 */
	public function export( array $ids = array() ) {
		if ( empty( $ids ) ) {
			$ids = get_posts(
				array(
					'post_type'     => PostTypes::COURSE,
					'post_status'   => array( 'publish', 'draft', 'pending', 'private' ),
					'numberposts'   => -1,
					'fields'        => 'ids',
					'no_found_rows' => true,
				)
			);
		}

		$courses = array();

		foreach ( array_map( 'intval', $ids ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || PostTypes::COURSE !== $post->post_type ) {
				continue;
			}
			$courses[] = $this->export_course( $post );
		}

		return array(
			'format'   => self::FORMAT,
			'plugin'   => 'shapelms',
			'version'  => SHAPELMS_VERSION,
			'exported' => gmdate( 'c' ),
			'courses'  => $courses,
		);
	}

	/**
	 * Exports one course.
	 *
	 * @param \WP_Post $post Course.
	 * @return array<string, mixed>
	 */
	private function export_course( $post ) {
		$course = array(
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'content'    => $post->post_content,
			'excerpt'    => $post->post_excerpt,
			'status'     => $post->post_status,
			'image'      => get_the_post_thumbnail_url( $post, 'full' ),
			'meta'       => $this->export_meta( $post->ID, PostTypes::COURSE ),
			'categories' => wp_get_object_terms( $post->ID, Taxonomies::CATEGORY, array( 'fields' => 'names' ) ),
			'tags'       => wp_get_object_terms( $post->ID, Taxonomies::TAG, array( 'fields' => 'names' ) ),
			'topics'     => array(),
		);

		unset( $course['meta']['instructors'], $course['meta']['woo_product_id'] );

		foreach ( Curriculum::tree( $post->ID, true ) as $topic ) {
			$items = array();

			foreach ( $topic['items'] as $item ) {
				$item_post = get_post( $item['id'] );
				$entry     = array(
					'type'    => $item['type'],
					'title'   => $item_post->post_title,
					'content' => $item_post->post_content,
					'status'  => $item_post->post_status,
					'meta'    => $this->export_meta( $item_post->ID, $item_post->post_type ),
				);

				if ( 'lesson' === $item['type'] ) {
					$entry['meta']['attachments'] = array();
				}

				if ( 'quiz' === $item['type'] ) {
					$entry['questions'] = array();
					foreach ( QuizService::questions( $item_post->ID ) as $question ) {
						$entry['questions'][] = array(
							'title'       => $question->text(),
							'description' => $question->description(),
							'type'        => $question->type,
							'points'      => $question->points,
							'options'     => $question->options,
							'correct'     => $question->correct,
							'explanation' => $question->explanation,
							'settings'    => $question->settings,
						);
					}
				}

				$items[] = $entry;
			}

			$course['topics'][] = array(
				'title'   => $topic['title'],
				'summary' => $topic['summary'],
				'items'   => $items,
			);
		}

		return $course;
	}

	/**
	 * Exports meta without the prefix.
	 *
	 * @param int    $post_id   Post id.
	 * @param string $post_type Post type.
	 * @return array<string, mixed>
	 */
	private function export_meta( $post_id, $post_type ) {
		$meta = array();

		foreach ( Meta::definitions()[ $post_type ] as $key => $field ) {
			$meta[ str_replace( '_shapelms_', '', $key ) ] = get_post_meta( $post_id, $key, true );
		}

		return $meta;
	}

	/**
	 * Imports courses.
	 *
	 * @param array<string, mixed> $data   Export payload.
	 * @param int                  $author Author (defaults to current user).
	 * @return int[]|\WP_Error Created course ids.
	 */
	public function import( array $data, $author = 0 ) {
		if ( empty( $data['courses'] ) || ! is_array( $data['courses'] ) ) {
			return new \WP_Error( 'shapelms_import_empty', __( 'No courses found in the file.', 'shapelms' ) );
		}

		$author = $author ? $author : get_current_user_id();

		if ( ! $author ) {
			$admins = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			);
			$author = $admins ? (int) $admins[0] : 0;
		}
		$created = array();

		foreach ( $data['courses'] as $course ) {
			if ( ! is_array( $course ) || empty( $course['title'] ) ) {
				continue;
			}
			$id = $this->import_course( $course, $author );
			if ( $id ) {
				$created[] = $id;
			}
		}

		return $created;
	}

	/**
	 * Imports one course.
	 *
	 * @param array<string, mixed> $course Course data.
	 * @param int                  $author Author.
	 * @return int
	 */
	private function import_course( array $course, $author ) {
		$status = isset( $course['status'] ) && in_array( $course['status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ? $course['status'] : 'draft';

		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => PostTypes::COURSE,
					'post_title'   => sanitize_text_field( (string) $course['title'] ),
					'post_name'    => isset( $course['slug'] ) ? sanitize_title( (string) $course['slug'] ) : '',
					'post_content' => wp_kses_post( (string) ( $course['content'] ?? '' ) ),
					'post_excerpt' => sanitize_textarea_field( (string) ( $course['excerpt'] ?? '' ) ),
					'post_status'  => $status,
					'post_author'  => (int) $author,
				)
			)
		);

		if ( ! $id ) {
			return 0;
		}

		$this->import_meta( (int) $id, PostTypes::COURSE, isset( $course['meta'] ) ? (array) $course['meta'] : array() );

		if ( ! empty( $course['image'] ) && is_string( $course['image'] ) ) {
			$this->sideload_image( (int) $id, $course['image'] );
		}

		if ( ! empty( $course['categories'] ) ) {
			wp_set_object_terms( (int) $id, array_map( 'sanitize_text_field', (array) $course['categories'] ), Taxonomies::CATEGORY );
		}
		if ( ! empty( $course['tags'] ) ) {
			wp_set_object_terms( (int) $id, array_map( 'sanitize_text_field', (array) $course['tags'] ), Taxonomies::TAG );
		}

		foreach ( (array) ( $course['topics'] ?? array() ) as $topic_index => $topic ) {
			if ( ! is_array( $topic ) || empty( $topic['title'] ) ) {
				continue;
			}

			$topic_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => PostTypes::TOPIC,
						'post_title'   => sanitize_text_field( (string) $topic['title'] ),
						'post_content' => wp_kses_post( (string) ( $topic['summary'] ?? '' ) ),
						'post_status'  => 'publish',
						'post_parent'  => (int) $id,
						'post_author'  => (int) $author,
						'menu_order'   => (int) $topic_index,
					)
				)
			);

			if ( ! $topic_id ) {
				continue;
			}

			foreach ( (array) ( $topic['items'] ?? array() ) as $item_index => $item ) {
				if ( ! is_array( $item ) || empty( $item['title'] ) ) {
					continue;
				}

				$type    = isset( $item['type'] ) ? Curriculum::post_type_for( sanitize_key( (string) $item['type'] ) ) : '';
				$type    = $type ? $type : PostTypes::LESSON;
				$item_id = wp_insert_post(
					wp_slash(
						array(
							'post_type'    => $type,
							'post_title'   => sanitize_text_field( (string) $item['title'] ),
							'post_content' => wp_kses_post( (string) ( $item['content'] ?? '' ) ),
							'post_status'  => isset( $item['status'] ) && 'draft' === $item['status'] ? 'draft' : 'publish',
							'post_parent'  => (int) $topic_id,
							'post_author'  => (int) $author,
							'menu_order'   => (int) $item_index,
						)
					)
				);

				if ( ! $item_id ) {
					continue;
				}

				$this->import_meta( (int) $item_id, $type, isset( $item['meta'] ) ? (array) $item['meta'] : array() );

				if ( PostTypes::QUIZ === $type ) {
					foreach ( (array) ( $item['questions'] ?? array() ) as $q_index => $question ) {
						if ( ! is_array( $question ) || empty( $question['title'] ) ) {
							continue;
						}
						$question_id = wp_insert_post(
							wp_slash(
								array(
									'post_type'    => PostTypes::QUESTION,
									'post_title'   => sanitize_text_field( (string) $question['title'] ),
									'post_content' => wp_kses_post( (string) ( $question['description'] ?? '' ) ),
									'post_status'  => 'publish',
									'post_parent'  => (int) $item_id,
									'post_author'  => (int) $author,
									'menu_order'   => (int) $q_index,
								)
							)
						);
						if ( $question_id ) {
							$this->import_meta( (int) $question_id, PostTypes::QUESTION, $question );
						}
					}
				}
			}
		}

		Curriculum::flush( (int) $id );

		return (int) $id;
	}

	/**
	 * Imports meta through the registered sanitizers.
	 *
	 * @param int                  $post_id   Post id.
	 * @param string               $post_type Post type.
	 * @param array<string, mixed> $meta      Meta without prefix.
	 * @return void
	 */
	private function import_meta( $post_id, $post_type, array $meta ) {
		foreach ( Meta::definitions()[ $post_type ] as $key => $field ) {
			$short = str_replace( '_shapelms_', '', $key );

			if ( ! array_key_exists( $short, $meta ) ) {
				continue;
			}

			$value = $meta[ $short ];

			if ( isset( $field['sanitize'] ) ) {
				$value = call_user_func( $field['sanitize'], $value );
			} elseif ( 'integer' === $field['type'] ) {
				$value = absint( $value );
			} elseif ( 'number' === $field['type'] ) {
				$value = (float) $value;
			} elseif ( 'boolean' === $field['type'] ) {
				$value = rest_sanitize_boolean( $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}

			update_post_meta( $post_id, $key, wp_slash( $value ) );
		}
	}

	/**
	 * Downloads a remote image and sets it as the thumbnail.
	 *
	 * @param int    $post_id Post id.
	 * @param string $url     Image URL.
	 * @return void
	 */
	private function sideload_image( $post_id, $url ) {
		$url = esc_url_raw( $url );

		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );

		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, (int) $attachment_id );
		}
	}

	/**
	 * Installs the bundled demo content.
	 *
	 * @param string $pack course (one draft course) or courses (the six-course sample pack).
	 * @return int|int[]|\WP_Error Course id, or ids for the sample pack.
	 */
	public function install_demo( $pack = 'course' ) {
		$pack = 'courses' === $pack ? 'courses' : 'course';
		$file = SHAPELMS_DIR . 'data/demo-' . $pack . '.json';

		if ( ! file_exists( $file ) ) {
			return new \WP_Error( 'shapelms_demo_missing', __( 'Demo file not found.', 'shapelms' ) );
		}

		$data   = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$result = $this->import( is_array( $data ) ? $data : array() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new \WP_Error( 'shapelms_demo_failed', __( 'Could not import the demo course.', 'shapelms' ) );
		}

		return 'courses' === $pack ? $result : $result[0];
	}
}
