<?php
/**
 * Initial schema.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database\Migrations;

/**
 * Creates the core tables.
 */
class Migration_0001_Initial extends Migration {

	/**
	 * Creates the tables.
	 *
	 * @return void
	 */
	public function up() {
		$this->create_table(
			'enrollments',
			'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT \'active\',
			source VARCHAR(20) NOT NULL DEFAULT \'manual\',
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
			started_at DATETIME NOT NULL,
			completed_at DATETIME NULL DEFAULT NULL,
			expires_at DATETIME NULL DEFAULT NULL,
			last_activity_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_id (course_id),
			KEY status (status),
			KEY started_at (started_at)'
		);

		$this->create_table(
			'progress',
			'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NOT NULL,
			item_type VARCHAR(20) NOT NULL DEFAULT \'lesson\',
			status VARCHAR(20) NOT NULL DEFAULT \'started\',
			time_spent INT UNSIGNED NOT NULL DEFAULT 0,
			started_at DATETIME NOT NULL,
			completed_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_item (user_id, item_id),
			KEY user_course (user_id, course_id),
			KEY item_id (item_id),
			KEY completed_at (completed_at)'
		);

		$this->create_table(
			'quiz_attempts',
			'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			quiz_id BIGINT UNSIGNED NOT NULL,
			attempt_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT \'in_progress\',
			score DECIMAL(6,2) NOT NULL DEFAULT 0,
			max_score DECIMAL(6,2) NOT NULL DEFAULT 0,
			percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
			passed TINYINT(1) NOT NULL DEFAULT 0,
			question_order TEXT NULL,
			started_at DATETIME NOT NULL,
			expires_at DATETIME NULL DEFAULT NULL,
			finished_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_quiz (user_id, quiz_id),
			KEY quiz_id (quiz_id),
			KEY course_id (course_id),
			KEY status (status)'
		);

		$this->create_table(
			'attempt_answers',
			'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attempt_id BIGINT UNSIGNED NOT NULL,
			question_id BIGINT UNSIGNED NOT NULL,
			answer LONGTEXT NULL,
			is_correct TINYINT(1) NULL DEFAULT NULL,
			points DECIMAL(6,2) NOT NULL DEFAULT 0,
			max_points DECIMAL(6,2) NOT NULL DEFAULT 0,
			answered_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attempt_question (attempt_id, question_id),
			KEY question_id (question_id)'
		);

		$this->create_table(
			'orders',
			'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_key VARCHAR(40) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT \'pending\',
			currency CHAR(3) NOT NULL DEFAULT \'USD\',
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
			discount DECIMAL(12,2) NOT NULL DEFAULT 0,
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			gateway VARCHAR(30) NOT NULL DEFAULT \'\',
			transaction_id VARCHAR(191) NOT NULL DEFAULT \'\',
			billing_email VARCHAR(191) NOT NULL DEFAULT \'\',
			billing_name VARCHAR(191) NOT NULL DEFAULT \'\',
			ip_address VARCHAR(45) NOT NULL DEFAULT \'\',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			paid_at DATETIME NULL DEFAULT NULL,
			refunded_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_key (order_key),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY transaction_id (transaction_id)'
		);

		$this->create_table(
			'order_items',
			'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT \'\',
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY course_id (course_id)'
		);

		$this->create_table(
			'activity_log',
			'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event VARCHAR(60) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			course_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			value DECIMAL(12,2) NULL DEFAULT NULL,
			context TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event_created (event, created_at),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY created_at (created_at)'
		);
	}

	/**
	 * Drops the tables.
	 *
	 * @return void
	 */
	public function down() {
		foreach ( array( 'activity_log', 'order_items', 'orders', 'attempt_answers', 'quiz_attempts', 'progress', 'enrollments' ) as $table ) {
			$this->drop_table( $table );
		}
	}
}
