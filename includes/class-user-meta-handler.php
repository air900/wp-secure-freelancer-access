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
	}
}