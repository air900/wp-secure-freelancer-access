<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_Access_Filter
 * Фильтрует списки записей и страниц в админке, скрывая недоступные элементы.
 */
class RPA_Access_Filter {

	public function __construct() {
		// Фильтрация основного запроса (списки постов)
		add_action( 'pre_get_posts', array( $this, 'filter_posts_query' ) );
		
		// Фильтрация выпадающего списка родительских страниц (Page Attributes)
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'filter_dropdown_pages' ) );
	}

	/**
	 * Модифицирует WP_Query для скрытия запрещенных постов.
	 */
	public function filter_posts_query( $query ) {
		// Применяем только в админке, для основного запроса и не для AJAX (если не нужно специально)
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Если пользователь админ - ничего не скрываем
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$post_type = $query->get( 'post_type' );

		// Защита от ошибок, если post_type передан как массив (например, при поиске)
		if ( is_array( $post_type ) ) {
			return;
		}

		// Если тип поста не указан, часто это 'post'
		if ( empty( $post_type ) ) {
			$post_type = 'post';
		}

		$allowed_ids = array();

		if ( 'page' === $post_type ) {
			$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_pages( $user_id );
		} elseif ( 'post' === $post_type ) {
			$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_posts( $user_id );
		} else {
			// Для других типов записей пока не применяем фильтр
			return;
		}

		// Если массив пустой, показываем "ничего" (ID = 0)
		if ( empty( $allowed_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
		} else {
			$query->set( 'post__in', $allowed_ids );
		}
	}

	/**
	 * Фильтрует аргументы для выпадающего списка родительских страниц.
	 */
	public function filter_dropdown_pages( $args ) {
		if ( current_user_can( 'manage_options' ) ) {
			return $args;
		}

		$user_id = get_current_user_id();
		$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_pages( $user_id );

		// Если прав нет, передаем 0, чтобы список был пуст
		$args['include'] = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;

		return $args;
	}
}