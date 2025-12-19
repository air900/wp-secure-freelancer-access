<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_User_Meta_Handler
 * Отвечает за работу с метаданными пользователей (хранение прав доступа).
 */
class RPA_User_Meta_Handler {

	const META_KEY_PAGES = 'rpa_allowed_pages';
	const META_KEY_POSTS = 'rpa_allowed_posts';

	/**
	 * Получить массив разрешенных страниц для пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return array Массив ID страниц.
	 */
	public static function get_user_allowed_pages( $user_id ) {
		$allowed = get_user_meta( $user_id, self::META_KEY_PAGES, true );
		return is_array( $allowed ) ? array_map( 'intval', $allowed ) : array();
	}

	/**
	 * Получить массив разрешенных записей для пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return array Массив ID записей.
	 */
	public static function get_user_allowed_posts( $user_id ) {
		$allowed = get_user_meta( $user_id, self::META_KEY_POSTS, true );
		return is_array( $allowed ) ? array_map( 'intval', $allowed ) : array();
	}

	/**
	 * Сохранить разрешенные страницы для пользователя.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param array $page_ids Массив ID страниц.
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_user_allowed_pages( $user_id, $page_ids ) {
		// Валидация: оставляем только числа
		$page_ids = array_map( 'intval', $page_ids );
		$page_ids = array_filter( $page_ids ); // Убираем 0 и пустые
		return update_user_meta( $user_id, self::META_KEY_PAGES, $page_ids );
	}

	/**
	 * Сохранить разрешенные записи для пользователя.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param array $post_ids Массив ID записей.
	 * @return int|bool
	 */
	public static function set_user_allowed_posts( $user_id, $post_ids ) {
		$post_ids = array_map( 'intval', $post_ids );
		$post_ids = array_filter( $post_ids );
		return update_user_meta( $user_id, self::META_KEY_POSTS, $post_ids );
	}

	/**
	 * Очистить права доступа пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 */
	public static function clear_user_access( $user_id ) {
		delete_user_meta( $user_id, self::META_KEY_PAGES );
		delete_user_meta( $user_id, self::META_KEY_POSTS );

		// Clear all custom post type access
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
				$user_id,
				'rpa_allowed_%'
			)
		);
	}

	/**
	 * Get allowed content IDs for any post type.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $post_type Post type slug.
	 * @return array Array of post IDs.
	 */
	public static function get_user_allowed_content( $user_id, $post_type ) {
		// Use specific methods for built-in types
		if ( 'page' === $post_type ) {
			return self::get_user_allowed_pages( $user_id );
		}
		if ( 'post' === $post_type ) {
			return self::get_user_allowed_posts( $user_id );
		}

		// Generic method for custom post types
		$meta_key = 'rpa_allowed_' . sanitize_key( $post_type );
		$allowed = get_user_meta( $user_id, $meta_key, true );
		return is_array( $allowed ) ? array_map( 'intval', $allowed ) : array();
	}

	/**
	 * Set allowed content IDs for any post type.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $post_type Post type slug.
	 * @param array  $ids       Array of post IDs.
	 * @return int|bool
	 */
	public static function set_user_allowed_content( $user_id, $post_type, $ids ) {
		// Use specific methods for built-in types
		if ( 'page' === $post_type ) {
			return self::set_user_allowed_pages( $user_id, $ids );
		}
		if ( 'post' === $post_type ) {
			return self::set_user_allowed_posts( $user_id, $ids );
		}

		// Generic method for custom post types
		$meta_key = 'rpa_allowed_' . sanitize_key( $post_type );
		$ids = array_map( 'intval', $ids );
		$ids = array_filter( $ids );
		return update_user_meta( $user_id, $meta_key, $ids );
	}

	/**
	 * Get allowed taxonomy terms for a user.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return array Array of term IDs.
	 */
	public static function get_user_allowed_taxonomy_terms( $user_id, $taxonomy ) {
		$meta_key = 'rpa_allowed_tax_' . sanitize_key( $taxonomy );
		$allowed = get_user_meta( $user_id, $meta_key, true );
		return is_array( $allowed ) ? array_map( 'intval', $allowed ) : array();
	}

	/**
	 * Set allowed taxonomy terms for a user.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $term_ids Array of term IDs.
	 * @return int|bool
	 */
	public static function set_user_allowed_taxonomy_terms( $user_id, $taxonomy, $term_ids ) {
		$meta_key = 'rpa_allowed_tax_' . sanitize_key( $taxonomy );
		$term_ids = array_map( 'intval', $term_ids );
		$term_ids = array_filter( $term_ids );
		return update_user_meta( $user_id, $meta_key, $term_ids );
	}

	/**
	 * Get allowed media IDs for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of attachment IDs.
	 */
	public static function get_user_allowed_media( $user_id ) {
		$allowed = get_user_meta( $user_id, 'rpa_allowed_media', true );
		return is_array( $allowed ) ? array_map( 'intval', $allowed ) : array();
	}

	/**
	 * Set allowed media IDs for a user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $ids     Array of attachment IDs.
	 * @return int|bool
	 */
	public static function set_user_allowed_media( $user_id, $ids ) {
		$ids = array_map( 'intval', $ids );
		$ids = array_filter( $ids );
		return update_user_meta( $user_id, 'rpa_allowed_media', $ids );
	}

	/**
	 * Get user access schedule (for temporary access).
	 *
	 * @param int $user_id User ID.
	 * @return array|null Array with start_date, end_date, or null if no schedule.
	 */
	public static function get_user_access_schedule( $user_id ) {
		$schedule = get_user_meta( $user_id, 'rpa_access_schedule', true );
		if ( ! is_array( $schedule ) || empty( $schedule ) ) {
			return null;
		}
		return $schedule;
	}

	/**
	 * Set user access schedule.
	 *
	 * @param int         $user_id    User ID.
	 * @param string|null $start_date Start date (Y-m-d) or null to clear.
	 * @param string|null $end_date   End date (Y-m-d) or null for no end.
	 * @return int|bool
	 */
	public static function set_user_access_schedule( $user_id, $start_date = null, $end_date = null ) {
		if ( null === $start_date && null === $end_date ) {
			return delete_user_meta( $user_id, 'rpa_access_schedule' );
		}

		$schedule = array(
			'start_date' => $start_date ? sanitize_text_field( $start_date ) : null,
			'end_date'   => $end_date ? sanitize_text_field( $end_date ) : null,
		);

		return update_user_meta( $user_id, 'rpa_access_schedule', $schedule );
	}

	/**
	 * Clear user access schedule.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function clear_user_access_schedule( $user_id ) {
		return delete_user_meta( $user_id, 'rpa_access_schedule' );
	}

	/**
	 * Check if user access is currently active (based on schedule).
	 *
	 * @param int $user_id User ID.
	 * @return bool True if access is active, false otherwise.
	 */
	public static function is_user_access_active( $user_id ) {
		$schedule = self::get_user_access_schedule( $user_id );

		// No schedule = always active
		if ( null === $schedule ) {
			return true;
		}

		$now = current_time( 'Y-m-d' );

		// Check start date
		if ( ! empty( $schedule['start_date'] ) && $now < $schedule['start_date'] ) {
			return false;
		}

		// Check end date
		if ( ! empty( $schedule['end_date'] ) && $now > $schedule['end_date'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Copy all access rights from one user to another.
	 *
	 * @param int  $source_user_id Source user ID.
	 * @param int  $target_user_id Target user ID.
	 * @param bool $include_schedule Whether to copy schedule as well.
	 * @return bool True on success.
	 */
	public static function copy_user_access( $source_user_id, $target_user_id, $include_schedule = false ) {
		// Copy pages
		$pages = self::get_user_allowed_pages( $source_user_id );
		self::set_user_allowed_pages( $target_user_id, $pages );

		// Copy posts
		$posts = self::get_user_allowed_posts( $source_user_id );
		self::set_user_allowed_posts( $target_user_id, $posts );

		// Copy media
		$media = self::get_user_allowed_media( $source_user_id );
		self::set_user_allowed_media( $target_user_id, $media );

		// Copy custom post types
		$enabled_types = RPA_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );
		foreach ( $enabled_types as $post_type ) {
			if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
				continue;
			}
			$content = self::get_user_allowed_content( $source_user_id, $post_type );
			self::set_user_allowed_content( $target_user_id, $post_type, $content );
		}

		// Copy taxonomies
		$enabled_taxonomies = RPA_Settings::get( 'enabled_taxonomies', array() );
		foreach ( $enabled_taxonomies as $taxonomy ) {
			$terms = self::get_user_allowed_taxonomy_terms( $source_user_id, $taxonomy );
			self::set_user_allowed_taxonomy_terms( $target_user_id, $taxonomy, $terms );
		}

		// Optionally copy schedule
		if ( $include_schedule ) {
			$schedule = self::get_user_access_schedule( $source_user_id );
			if ( $schedule ) {
				self::set_user_access_schedule(
					$target_user_id,
					$schedule['start_date'] ?? null,
					$schedule['end_date'] ?? null
				);
			} else {
				self::clear_user_access_schedule( $target_user_id );
			}
		}

		return true;
	}
}