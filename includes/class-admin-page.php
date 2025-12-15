<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_Admin_Page
 * Отвечает за отображение интерфейса управления правами в админке.
 */
class RPA_Admin_Page {

	private $page_slug = 'restricted-pages-access';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
	}

	/**
	 * Регистрация страницы в меню "Настройки".
	 */
	public function register_menu() {
		add_options_page(
			'Ограничение доступа',
			'Ограничение доступа',
			'manage_options',
			$this->page_slug,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Обработка сохранения настроек.
	 */
	public function save_settings() {
		if ( ! isset( $_POST['rpa_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Обработка очистки логов
		if ( 'clear_logs' === $_POST['rpa_action'] ) {
			check_admin_referer( 'rpa_clear_logs', 'rpa_nonce' );
			delete_option( 'rpa_access_logs' );
			wp_redirect( add_query_arg( array( 'page' => $this->page_slug, 'view' => 'logs', 'message' => 'logs_cleared' ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		// Обработка сохранения прав
		if ( 'save_access' === $_POST['rpa_action'] ) {
			check_admin_referer( 'rpa_save_access', 'rpa_nonce' );

			$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
			if ( ! $user_id ) {
				return;
			}

			// Сохраняем страницы
			$allowed_pages = isset( $_POST['allowed_pages'] ) ? (array) $_POST['allowed_pages'] : array();
			RPA_User_Meta_Handler::set_user_allowed_pages( $user_id, $allowed_pages );

			// Сохраняем записи
			$allowed_posts = isset( $_POST['allowed_posts'] ) ? (array) $_POST['allowed_posts'] : array();
			RPA_User_Meta_Handler::set_user_allowed_posts( $user_id, $allowed_posts );

			// Редирект с сообщением об успехе
			wp_redirect( add_query_arg( array( 'page' => $this->page_slug, 'message' => 'saved', 'edit_user' => $user_id ), admin_url( 'options-general.php' ) ) );
			exit;
		}
	}

	/**
	 * Рендеринг страницы.
	 */
	public function render_admin_page() {
		$current_view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'users';
		$edit_user_id = isset( $_GET['edit_user'] ) ? intval( $_GET['edit_user'] ) : 0;

		?>
		<div class="wrap">
			<h1>Ограничение доступа к контенту</h1>
			
			<?php
			// Навигация по вкладкам
			$tabs = array(
				'users' => 'Пользователи',
				'logs'  => 'Журнал доступа',
			);
			
			echo '<h2 class="nav-tab-wrapper">';
			foreach ( $tabs as $view => $label ) {
				$active_class = ( $current_view === $view && ! $edit_user_id ) ? ' nav-tab-active' : '';
				$url = add_query_arg( array( 'page' => $this->page_slug, 'view' => $view, 'edit_user' => false ), admin_url( 'options-general.php' ) );
				echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . $active_class . '">' . esc_html( $label ) . '</a>';
			}
			echo '</h2><br>';

			if ( isset( $_GET['message'] ) && 'saved' === $_GET['message'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>Настройки доступа сохранены.</p></div>';
			}
			if ( isset( $_GET['message'] ) && 'logs_cleared' === $_GET['message'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>Журнал очищен.</p></div>';
			}

			if ( $edit_user_id ) {
				$this->render_edit_form( $edit_user_id );
			} elseif ( 'logs' === $current_view ) {
				$this->render_logs();
			} else {
				$this->render_user_list();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Отображение списка пользователей (Editors).
	 */
	private function render_user_list() {
		// Получаем всех пользователей с ролью editor
		$editors = get_users( array( 'role' => 'editor' ) );

		if ( empty( $editors ) ) {
			echo '<p>Пользователей с ролью "Редактор" (Editor) не найдено.</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Пользователь</th><th>Email</th><th>Доступ (Страницы)</th><th>Доступ (Записи)</th><th>Действия</th></tr></thead>';
		echo '<tbody>';

		foreach ( $editors as $user ) {
			$allowed_pages = RPA_User_Meta_Handler::get_user_allowed_pages( $user->ID );
			$allowed_posts = RPA_User_Meta_Handler::get_user_allowed_posts( $user->ID );
			
			$edit_link = add_query_arg( array( 'page' => $this->page_slug, 'edit_user' => $user->ID ), admin_url( 'options-general.php' ) );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $user->display_name ) . '</strong><br><small>' . esc_html( $user->user_login ) . '</small></td>';
			echo '<td>' . esc_html( $user->user_email ) . '</td>';
			echo '<td>' . count( $allowed_pages ) . ' шт.</td>';
			echo '<td>' . count( $allowed_posts ) . ' шт.</td>';
			echo '<td><a href="' . esc_url( $edit_link ) . '" class="button button-small">Редактировать доступ</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Отображение формы редактирования прав для конкретного пользователя.
	 */
	private function render_edit_form( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<p>Пользователь не найден.</p>';
			return;
		}

		$back_link = add_query_arg( array( 'page' => $this->page_slug ), admin_url( 'options-general.php' ) );
		
		$allowed_pages = RPA_User_Meta_Handler::get_user_allowed_pages( $user_id );
		$allowed_posts = RPA_User_Meta_Handler::get_user_allowed_posts( $user_id );

		// Получаем все страницы и записи (можно оптимизировать для больших сайтов, добавив пагинацию или AJAX поиск)
		$all_pages = get_pages(); 
		$all_posts = get_posts( array( 'numberposts' => -1, 'post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending', 'future', 'private') ) );

		?>
		<p><a href="<?php echo esc_url( $back_link ); ?>">&larr; Вернуться к списку пользователей</a></p>
		<h2>Настройка доступа для: <?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_login ); ?>)</h2>

		<form method="post" action="">
			<?php wp_nonce_field( 'rpa_save_access', 'rpa_nonce' ); ?>
			<input type="hidden" name="rpa_action" value="save_access">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">

			<div style="display: flex; gap: 20px; flex-wrap: wrap;">
				
				<!-- Блок Страниц -->
				<div style="flex: 1; min-width: 300px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<h3>Страницы</h3>
					<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
						<?php if ( empty( $all_pages ) ) : ?>
							<p>Страниц не найдено.</p>
						<?php else : ?>
							<?php foreach ( $all_pages as $page ) : ?>
								<label style="display: block; margin-bottom: 5px;">
									<input type="checkbox" name="allowed_pages[]" value="<?php echo $page->ID; ?>" <?php checked( in_array( $page->ID, $allowed_pages ) ); ?>>
									[<?php echo $page->ID; ?>] <?php echo esc_html( $page->post_title ); ?> 
									<span style="color: #888; font-size: 0.9em;">(<?php echo $page->post_status; ?>)</span>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<p>
						<button type="button" class="button rpa-select-all" data-target="allowed_pages">Выбрать все</button>
						<button type="button" class="button rpa-deselect-all" data-target="allowed_pages">Снять все</button>
					</p>
				</div>

				<!-- Блок Записей -->
				<div style="flex: 1; min-width: 300px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<h3>Записи</h3>
					<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
						<?php if ( empty( $all_posts ) ) : ?>
							<p>Записей не найдено.</p>
						<?php else : ?>
							<?php foreach ( $all_posts as $post ) : ?>
								<label style="display: block; margin-bottom: 5px;">
									<input type="checkbox" name="allowed_posts[]" value="<?php echo $post->ID; ?>" <?php checked( in_array( $post->ID, $allowed_posts ) ); ?>>
									[<?php echo $post->ID; ?>] <?php echo esc_html( $post->post_title ? $post->post_title : '(Без заголовка)' ); ?>
									<span style="color: #888; font-size: 0.9em;">(<?php echo $post->post_status; ?>)</span>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<p>
						<button type="button" class="button rpa-select-all" data-target="allowed_posts">Выбрать все</button>
						<button type="button" class="button rpa-deselect-all" data-target="allowed_posts">Снять все</button>
					</p>
				</div>

			</div>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Сохранить изменения">
			</p>
		</form>

		<script>
		// Простой JS для кнопок Выбрать все / Снять все
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.rpa-select-all').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var targetName = this.getAttribute('data-target');
					document.querySelectorAll('input[name="' + targetName + '[]"]').forEach(function(cb) { cb.checked = true; });
				});
			});
			document.querySelectorAll('.rpa-deselect-all').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var targetName = this.getAttribute('data-target');
					document.querySelectorAll('input[name="' + targetName + '[]"]').forEach(function(cb) { cb.checked = false; });
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Отображение журнала доступа.
	 */
	private function render_logs() {
		$logs = get_option( 'rpa_access_logs', array() );

		if ( empty( $logs ) ) {
			echo '<p>Журнал пуст. Попыток несанкционированного доступа не зафиксировано.</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>
				<th style="width: 150px;">Время</th>
				<th>Пользователь</th>
				<th>Попытка доступа к</th>
				<th>IP адрес</th>
			  </tr></thead>';
		echo '<tbody>';

		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( $log['time'] ) . '</td>';
			echo '<td>' . esc_html( $log['user_login'] ) . '</td>';
			echo '<td>' . esc_html( $log['post_title'] ) . ' (ID: ' . intval( $log['post_id'] ) . ')</td>';
			echo '<td>' . esc_html( $log['ip'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		?>
		<form method="post" action="" style="margin-top: 20px;">
			<?php wp_nonce_field( 'rpa_clear_logs', 'rpa_nonce' ); ?>
			<input type="hidden" name="rpa_action" value="clear_logs">
			<input type="submit" class="button" value="Очистить журнал" onclick="return confirm('Вы уверены?');">
		</form>
		<?php
	}
}