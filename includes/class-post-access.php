<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_Post_Access
 * Checks access rights when attempting to open the post editor.
 */
class RPA_Post_Access {

	public function __construct() {
		// Хук срабатывает при загрузке страницы редактирования поста
		add_action( 'load-post.php', array( $this, 'check_post_access' ) );
	}

	/**
	 * Проверка доступа.
	 */
	public function check_post_access() {
		// Админам можно всё
		if ( current_user_can( 'manage_options' ) ) {
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
		$is_allowed = false;

		if ( 'page' === $post->post_type ) {
			$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_pages( $user_id );
			if ( in_array( $post_id, $allowed_ids, true ) ) {
				$is_allowed = true;
			}
		} elseif ( 'post' === $post->post_type ) {
			$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_posts( $user_id );
			if ( in_array( $post_id, $allowed_ids, true ) ) {
				$is_allowed = true;
			}
		} else {
			// Для других типов постов пока разрешаем (или можно запретить по умолчанию)
			$is_allowed = true;
		}

		if ( ! $is_allowed ) {
			$this->log_access_attempt( $user_id, $post_id );
			wp_die(
				esc_html__( 'You do not have permission to edit this content.', 'restricted-pages-access' ),
				esc_html__( 'Access Denied', 'restricted-pages-access' ),
				array( 'response' => 403 )
			);
		}
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
			'[Restricted Pages Access] Access Denied. User: %s (ID: %d). Post: %s (ID: %d). IP: %s',
			$user_login,
			$user_id,
			$post_title,
			$post_id,
			$ip
		);

		error_log( $message );

		// Сохраняем в БД для вывода в админке
		$logs = get_option( 'rpa_access_logs', array() );
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

		update_option( 'rpa_access_logs', $logs, false );
	}
}