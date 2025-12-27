<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFAccess_Post_Access
 * Checks access rights when attempting to open the post editor.
 */
class SFAccess_Post_Access {

	public function __construct() {
		// Хук срабатывает при загрузке страницы редактирования поста
		add_action( 'load-post.php', array( $this, 'check_post_access' ) );
	}

	/**
	 * Check if current user should be checked for access.
	 *
	 * @return bool
	 */
	private function should_check_user() {
		// Admins are never checked
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check if user's role is in restricted list
		return SFAccess_Settings::is_current_user_restricted();
	}

	/**
	 * Проверка доступа.
	 */
	public function check_post_access() {
		// Check if we should restrict this user
		if ( ! $this->should_check_user() ) {
			return;
		}

		// Получаем ID поста из запроса
		$post_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if access is expired (temporary access)
		if ( ! SFAccess_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$this->log_access_attempt( $user_id, $post_id );
			wp_die(
				esc_html__( 'Your access has expired.', 'secure-freelancer-access' ),
				esc_html__( 'Access Expired', 'secure-freelancer-access' ),
				array( 'response' => 403 )
			);
		}

		// Check if this post type is enabled for restriction
		if ( ! SFAccess_Settings::is_post_type_enabled( $post->post_type ) ) {
			return; // This post type is not restricted
		}

		// Check access
		$is_allowed = $this->check_user_access( $user_id, $post_id, $post->post_type );

		if ( ! $is_allowed ) {
			$this->log_access_attempt( $user_id, $post_id );
			wp_die(
				esc_html__( 'You do not have permission to edit this content.', 'secure-freelancer-access' ),
				esc_html__( 'Access Denied', 'secure-freelancer-access' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Check if user has access to a specific post.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function check_user_access( $user_id, $post_id, $post_type ) {
		// Check direct access to post
		$allowed_ids = SFAccess_User_Meta_Handler::get_user_allowed_content( $user_id, $post_type );
		if ( in_array( $post_id, $allowed_ids, true ) ) {
			return true;
		}

		// Check access via taxonomies
		$enabled_taxonomies = SFAccess_Settings::get( 'enabled_taxonomies', array() );

		foreach ( $enabled_taxonomies as $taxonomy ) {
			// Check if taxonomy applies to this post type
			$tax_object = get_taxonomy( $taxonomy );
			if ( ! $tax_object || ! in_array( $post_type, $tax_object->object_type, true ) ) {
				continue;
			}

			// Get allowed terms for this taxonomy
			$allowed_terms = SFAccess_User_Meta_Handler::get_user_allowed_taxonomy_terms( $user_id, $taxonomy );
			if ( empty( $allowed_terms ) ) {
				continue;
			}

			// Check if post is in any of the allowed terms
			$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $post_terms ) ) {
				continue;
			}

			$common_terms = array_intersect( $post_terms, $allowed_terms );
			if ( ! empty( $common_terms ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Логирование попытки доступа к запрещенной записи.
	 *
	 * @param int $user_id ID пользователя.
	 * @param int $post_id ID записи.
	 */
	private function log_access_attempt( $user_id, $post_id ) {
		$user       = get_userdata( $user_id );
		$user_login = $user ? $user->user_login : 'Unknown';
		$post       = get_post( $post_id );
		$post_title = $post ? $post->post_title : 'Unknown';
		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'Unknown';

		$message = sprintf(
			'[Secure Freelancer Access] Access Denied. User: %s (ID: %d). Post: %s (ID: %d). IP: %s',
			$user_login,
			$user_id,
			$post_title,
			$post_id,
			$ip
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for security audit
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message );
		}

		// Сохраняем в БД для вывода в админке
		$logs = get_option( 'sfaccess_access_logs', array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$new_log = array(
			'time'       => current_time( 'mysql' ),
			'user_login' => $user_login,
			'post_id'    => $post_id,
			'post_title' => $post_title,
			'ip'         => $ip,
		);

		// Добавляем в начало массива
		array_unshift( $logs, $new_log );

		// Храним только последние 50 записей, чтобы не засорять БД
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, 0, 50 );
		}

		update_option( 'sfaccess_access_logs', $logs, false );
	}
}