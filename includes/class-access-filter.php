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
	 * Check if current user should be filtered.
	 *
	 * @return bool
	 */
	private function should_filter_user() {
		// Admins are never filtered
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check if user's role is in restricted list
		if ( ! RPA_Settings::is_current_user_restricted() ) {
			return false;
		}

		// Check temporary access schedule
		$user_id = get_current_user_id();
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			return true; // Access expired - filter everything
		}

		return true;
	}

	/**
	 * Модифицирует WP_Query для скрытия запрещенных постов.
	 */
	public function filter_posts_query( $query ) {
		// Применяем только в админке, для основного запроса
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Check if we should filter this user
		if ( ! $this->should_filter_user() ) {
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

		// Check if this post type is enabled for restriction
		if ( ! RPA_Settings::is_post_type_enabled( $post_type ) ) {
			return;
		}

		// Check if access is expired
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		// Get allowed IDs for this post type
		$allowed_ids = $this->get_allowed_ids_for_type( $user_id, $post_type );

		// Если массив пустой, показываем "ничего" (ID = 0)
		if ( empty( $allowed_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
		} else {
			$query->set( 'post__in', $allowed_ids );
		}
	}

	/**
	 * Get allowed post IDs for a specific post type.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $post_type Post type.
	 * @return array
	 */
	private function get_allowed_ids_for_type( $user_id, $post_type ) {
		// Get directly allowed IDs
		$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_content( $user_id, $post_type );

		// Add IDs from allowed taxonomies
		$taxonomy_ids = $this->get_ids_from_allowed_taxonomies( $user_id, $post_type );
		$allowed_ids = array_unique( array_merge( $allowed_ids, $taxonomy_ids ) );

		return $allowed_ids;
	}

	/**
	 * Get post IDs from allowed taxonomies.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $post_type Post type.
	 * @return array
	 */
	private function get_ids_from_allowed_taxonomies( $user_id, $post_type ) {
		$enabled_taxonomies = RPA_Settings::get( 'enabled_taxonomies', array() );

		if ( empty( $enabled_taxonomies ) ) {
			return array();
		}

		$post_ids = array();

		foreach ( $enabled_taxonomies as $taxonomy ) {
			// Check if taxonomy applies to this post type
			$tax_object = get_taxonomy( $taxonomy );
			if ( ! $tax_object || ! in_array( $post_type, $tax_object->object_type, true ) ) {
				continue;
			}

			// Get allowed terms for this taxonomy
			$allowed_terms = RPA_User_Meta_Handler::get_user_allowed_taxonomy_terms( $user_id, $taxonomy );

			if ( empty( $allowed_terms ) ) {
				continue;
			}

			// Get posts in these terms
			$posts = get_posts( array(
				'post_type'      => $post_type,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'tax_query'      => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $allowed_terms,
					),
				),
			) );

			$post_ids = array_merge( $post_ids, $posts );
		}

		return array_unique( $post_ids );
	}

	/**
	 * Фильтрует аргументы для выпадающего списка родительских страниц.
	 */
	public function filter_dropdown_pages( $args ) {
		if ( ! $this->should_filter_user() ) {
			return $args;
		}

		$user_id = get_current_user_id();

		// Check if access is expired
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$args['include'] = array( 0 );
			return $args;
		}

		$allowed_ids = $this->get_allowed_ids_for_type( $user_id, 'page' );

		// Если прав нет, передаем 0, чтобы список был пуст
		$args['include'] = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;

		return $args;
	}
}