<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_Admin_Page
 * Handles the admin interface for managing user permissions.
 */
class RPA_Admin_Page {

	private $page_slug = 'secure-freelancer-access';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register menu page under Settings.
	 */
	public function register_menu() {
		add_options_page(
			__( 'Content Access Restriction', 'secure-freelancer-access' ),
			__( 'Content Access Restriction', 'secure-freelancer-access' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our settings page
		if ( 'settings_page_' . $this->page_slug !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'rpa-admin-style',
			RPA_PLUGIN_URL . 'assets/css/admin-style.css',
			array(),
			RPA_VERSION
		);

		wp_enqueue_script(
			'rpa-admin-script',
			RPA_PLUGIN_URL . 'assets/js/admin-script.js',
			array(),
			RPA_VERSION,
			true
		);
	}

	/**
	 * Handle form submissions.
	 */
	public function save_settings() {
		if ( ! isset( $_POST['rpa_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Clear logs action
		if ( 'clear_logs' === $_POST['rpa_action'] ) {
			check_admin_referer( 'rpa_clear_logs', 'rpa_nonce' );
			delete_option( 'rpa_access_logs' );
			wp_redirect( add_query_arg( array( 'page' => $this->page_slug, 'view' => 'logs', 'message' => 'logs_cleared' ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		// Save access rights action
		if ( 'save_access' === $_POST['rpa_action'] ) {
			check_admin_referer( 'rpa_save_access', 'rpa_nonce' );

			$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
			if ( ! $user_id ) {
				return;
			}

			// Save pages
			$allowed_pages = isset( $_POST['allowed_pages'] ) ? (array) $_POST['allowed_pages'] : array();
			RPA_User_Meta_Handler::set_user_allowed_pages( $user_id, $allowed_pages );

			// Save posts
			$allowed_posts = isset( $_POST['allowed_posts'] ) ? (array) $_POST['allowed_posts'] : array();
			RPA_User_Meta_Handler::set_user_allowed_posts( $user_id, $allowed_posts );

			// Redirect with success message
			wp_redirect( add_query_arg( array( 'page' => $this->page_slug, 'message' => 'saved', 'edit_user' => $user_id ), admin_url( 'options-general.php' ) ) );
			exit;
		}
	}

	/**
	 * Render main admin page.
	 */
	public function render_admin_page() {
		$current_view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'users';
		$edit_user_id = isset( $_GET['edit_user'] ) ? intval( $_GET['edit_user'] ) : 0;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Content Access Restriction', 'secure-freelancer-access' ); ?></h1>

			<?php
			// Tab navigation
			$tabs = array(
				'users' => __( 'Users', 'secure-freelancer-access' ),
				'logs'  => __( 'Access Log', 'secure-freelancer-access' ),
			);

			echo '<h2 class="nav-tab-wrapper">';
			foreach ( $tabs as $view => $label ) {
				$active_class = ( $current_view === $view && ! $edit_user_id ) ? ' nav-tab-active' : '';
				$url = add_query_arg( array( 'page' => $this->page_slug, 'view' => $view, 'edit_user' => false ), admin_url( 'options-general.php' ) );
				echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html( $label ) . '</a>';
			}
			echo '</h2><br>';

			// Success messages
			if ( isset( $_GET['message'] ) && 'saved' === $_GET['message'] ) {
				echo '<div class="notice notice-success is-dismissible rpa-notice"><p>' . esc_html__( 'Access settings saved successfully.', 'secure-freelancer-access' ) . '</p></div>';
			}
			if ( isset( $_GET['message'] ) && 'logs_cleared' === $_GET['message'] ) {
				echo '<div class="notice notice-success is-dismissible rpa-notice"><p>' . esc_html__( 'Access log cleared.', 'secure-freelancer-access' ) . '</p></div>';
			}

			// Display appropriate view
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
	 * Display list of editor users.
	 */
	private function render_user_list() {
		$editors = get_users( array( 'role' => 'editor' ) );

		if ( empty( $editors ) ) {
			echo '<div class="rpa-empty-state">';
			echo '<p>' . esc_html__( 'No users with "Editor" role found.', 'secure-freelancer-access' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'User', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Access (Pages)', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Access (Posts)', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'secure-freelancer-access' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $editors as $user ) {
			$allowed_pages = RPA_User_Meta_Handler::get_user_allowed_pages( $user->ID );
			$allowed_posts = RPA_User_Meta_Handler::get_user_allowed_posts( $user->ID );

			$edit_link = add_query_arg( array( 'page' => $this->page_slug, 'edit_user' => $user->ID ), admin_url( 'options-general.php' ) );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $user->display_name ) . '</strong><br><small>' . esc_html( $user->user_login ) . '</small></td>';
			echo '<td>' . esc_html( $user->user_email ) . '</td>';
			echo '<td><span class="rpa-user-count">' . count( $allowed_pages ) . '</span></td>';
			echo '<td><span class="rpa-user-count">' . count( $allowed_posts ) . '</span></td>';
			echo '<td><a href="' . esc_url( $edit_link ) . '" class="button button-small">' . esc_html__( 'Edit Access', 'secure-freelancer-access' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Display edit form for specific user.
	 */
	private function render_edit_form( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'User not found.', 'secure-freelancer-access' ) . '</p></div>';
			return;
		}

		$back_link = add_query_arg( array( 'page' => $this->page_slug ), admin_url( 'options-general.php' ) );

		$allowed_pages = RPA_User_Meta_Handler::get_user_allowed_pages( $user_id );
		$allowed_posts = RPA_User_Meta_Handler::get_user_allowed_posts( $user_id );

		// Get all pages and posts
		$all_pages = get_pages( array(
			'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' )
		) );
		$all_posts = get_posts( array(
			'numberposts' => -1,
			'post_type' => 'post',
			'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' )
		) );

		?>
		<p>
			<a href="<?php echo esc_url( $back_link ); ?>" class="rpa-back-link">
				&larr; <?php esc_html_e( 'Back to user list', 'secure-freelancer-access' ); ?>
			</a>
		</p>

		<h2>
			<?php
			echo esc_html( sprintf(
				/* translators: %1$s: user display name, %2$s: user login */
				__( 'Edit access for: %1$s (%2$s)', 'secure-freelancer-access' ),
				$user->display_name,
				$user->user_login
			) );
			?>
		</h2>

		<!-- Unsaved Changes Indicator -->
		<div class="rpa-unsaved-indicator">
			<?php esc_html_e( 'You have unsaved changes', 'secure-freelancer-access' ); ?>
		</div>

		<!-- Selected Content Summary (Badges) -->
		<div id="rpa-selected-summary" style="display: none; margin-bottom: 20px;">
			<h3>
				<?php esc_html_e( 'Currently Selected', 'secure-freelancer-access' ); ?>
				(<span id="rpa-selected-total">0</span>)
			</h3>

			<!-- Pages Badges -->
			<div class="rpa-badge-group">
				<strong><?php esc_html_e( 'Pages', 'secure-freelancer-access' ); ?> (<span id="rpa-badge-pages-count">0</span>):</strong>
				<div id="rpa-badges-pages" class="rpa-badges-container">
					<!-- Badges added by JavaScript -->
				</div>
			</div>

			<!-- Posts Badges -->
			<div class="rpa-badge-group">
				<strong><?php esc_html_e( 'Posts', 'secure-freelancer-access' ); ?> (<span id="rpa-badge-posts-count">0</span>):</strong>
				<div id="rpa-badges-posts" class="rpa-badges-container">
					<!-- Badges added by JavaScript -->
				</div>
			</div>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'rpa_save_access', 'rpa_nonce' ); ?>
			<input type="hidden" name="rpa_action" value="save_access">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">

			<div class="rpa-edit-form-container">

				<!-- Pages Block -->
				<div class="rpa-content-block">
					<h3>
						<?php esc_html_e( 'Pages', 'secure-freelancer-access' ); ?>
						<span class="rpa-counter" data-target="allowed_pages">0 / 0</span>
					</h3>

					<!-- Search and Filters -->
					<div class="rpa-controls">
						<!-- Search -->
						<div class="rpa-search-wrapper">
							<input type="search" class="rpa-search-input" data-target="allowed_pages" placeholder="<?php esc_attr_e( 'Search by title or ID...', 'secure-freelancer-access' ); ?>">
						</div>

						<!-- Filters Row -->
						<div class="rpa-filters-row">
							<!-- Status Filter -->
							<div class="rpa-filter-group">
								<label><?php esc_html_e( 'Status', 'secure-freelancer-access' ); ?></label>
								<select class="rpa-status-filter" data-target="allowed_pages">
									<option value="all"><?php esc_html_e( 'All Statuses', 'secure-freelancer-access' ); ?></option>
									<option value="publish"><?php esc_html_e( 'Published', 'secure-freelancer-access' ); ?></option>
									<option value="draft"><?php esc_html_e( 'Draft', 'secure-freelancer-access' ); ?></option>
									<option value="pending"><?php esc_html_e( 'Pending', 'secure-freelancer-access' ); ?></option>
								</select>
							</div>

							<!-- Sort -->
							<div class="rpa-filter-group">
								<label><?php esc_html_e( 'Sort by', 'secure-freelancer-access' ); ?></label>
								<select class="rpa-sort-select" data-target="allowed_pages">
									<option value="date-created" selected><?php esc_html_e( 'Date Created (newest first)', 'secure-freelancer-access' ); ?></option>
									<option value="date-modified"><?php esc_html_e( 'Date Modified (newest first)', 'secure-freelancer-access' ); ?></option>
									<option value="title"><?php esc_html_e( 'Title (A-Z)', 'secure-freelancer-access' ); ?></option>
									<option value="id"><?php esc_html_e( 'ID', 'secure-freelancer-access' ); ?></option>
								</select>
							</div>

							<!-- Show -->
							<div class="rpa-filter-group">
								<label><?php esc_html_e( 'Show', 'secure-freelancer-access' ); ?></label>
								<select class="rpa-visibility-filter" data-target="allowed_pages">
									<option value="all"><?php esc_html_e( 'All', 'secure-freelancer-access' ); ?></option>
									<option value="selected"><?php esc_html_e( 'Selected Only', 'secure-freelancer-access' ); ?></option>
									<option value="unselected"><?php esc_html_e( 'Unselected Only', 'secure-freelancer-access' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- Content List -->
					<div class="rpa-content-list" data-content-type="allowed_pages">
						<?php if ( empty( $all_pages ) ) : ?>
							<p class="rpa-empty-state"><?php esc_html_e( 'No pages found.', 'secure-freelancer-access' ); ?></p>
						<?php else : ?>
							<?php foreach ( $all_pages as $page ) : ?>
								<label data-title="<?php echo esc_attr( mb_strtolower( $page->post_title ) ); ?>"
									   data-slug="<?php echo esc_attr( mb_strtolower( $page->post_name ) ); ?>"
									   data-status="<?php echo esc_attr( $page->post_status ); ?>"
									   data-date-created="<?php echo esc_attr( strtotime( $page->post_date ) ); ?>"
									   data-date-modified="<?php echo esc_attr( strtotime( $page->post_modified ) ); ?>">
									<input type="checkbox" name="allowed_pages[]" value="<?php echo esc_attr( $page->ID ); ?>" <?php checked( in_array( $page->ID, $allowed_pages ) ); ?>>
									<span>[<?php echo esc_html( $page->ID ); ?>] <?php echo esc_html( $page->post_title ); ?></span>
									<span class="rpa-content-status">(<?php echo esc_html( $page->post_status ); ?>)</span>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<!-- Action Buttons -->
					<div class="rpa-button-group">
						<button type="button" class="button rpa-select-all" data-target="allowed_pages">
							<?php esc_html_e( 'Select All', 'secure-freelancer-access' ); ?>
						</button>
						<button type="button" class="button rpa-select-published" data-target="allowed_pages">
							<?php esc_html_e( 'Select Published', 'secure-freelancer-access' ); ?>
						</button>
						<button type="button" class="button rpa-deselect-all" data-target="allowed_pages">
							<?php esc_html_e( 'Deselect All', 'secure-freelancer-access' ); ?>
						</button>
					</div>
				</div>

				<!-- Posts Block -->
				<div class="rpa-content-block">
					<h3>
						<?php esc_html_e( 'Posts', 'secure-freelancer-access' ); ?>
						<span class="rpa-counter" data-target="allowed_posts">0 / 0</span>
					</h3>

					<!-- Search and Filters -->
					<div class="rpa-controls">
						<!-- Search -->
						<div class="rpa-search-wrapper">
							<input type="search" class="rpa-search-input" data-target="allowed_posts" placeholder="<?php esc_attr_e( 'Search by title or ID...', 'secure-freelancer-access' ); ?>">
						</div>

						<!-- Filters Row -->
						<div class="rpa-filters-row">
							<!-- Status Filter -->
							<div class="rpa-filter-group">
								<label><?php esc_html_e( 'Status', 'secure-freelancer-access' ); ?></label>
								<select class="rpa-status-filter" data-target="allowed_posts">
									<option value="all"><?php esc_html_e( 'All Statuses', 'secure-freelancer-access' ); ?></option>
									<option value="publish"><?php esc_html_e( 'Published', 'secure-freelancer-access' ); ?></option>
									<option value="draft"><?php esc_html_e( 'Draft', 'secure-freelancer-access' ); ?></option>
									<option value="pending"><?php esc_html_e( 'Pending', 'secure-freelancer-access' ); ?></option>
								</select>
							</div>

							<!-- Sort -->
							<div class="rpa-filter-group">
								<label><?php esc_html_e( 'Sort by', 'secure-freelancer-access' ); ?></label>
								<select class="rpa-sort-select" data-target="allowed_posts">
									<option value="date-created" selected><?php esc_html_e( 'Date Created (newest first)', 'secure-freelancer-access' ); ?></option>
									<option value="date-modified"><?php esc_html_e( 'Date Modified (newest first)', 'secure-freelancer-access' ); ?></option>
									<option value="title"><?php esc_html_e( 'Title (A-Z)', 'secure-freelancer-access' ); ?></option>
									<option value="id"><?php esc_html_e( 'ID', 'secure-freelancer-access' ); ?></option>
								</select>
							</div>

							<!-- Show -->
							<div class="rpa-filter-group">
								<label><?php esc_html_e( 'Show', 'secure-freelancer-access' ); ?></label>
								<select class="rpa-visibility-filter" data-target="allowed_posts">
									<option value="all"><?php esc_html_e( 'All', 'secure-freelancer-access' ); ?></option>
									<option value="selected"><?php esc_html_e( 'Selected Only', 'secure-freelancer-access' ); ?></option>
									<option value="unselected"><?php esc_html_e( 'Unselected Only', 'secure-freelancer-access' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- Content List -->
					<div class="rpa-content-list" data-content-type="allowed_posts">
						<?php if ( empty( $all_posts ) ) : ?>
							<p class="rpa-empty-state"><?php esc_html_e( 'No posts found.', 'secure-freelancer-access' ); ?></p>
						<?php else : ?>
							<?php foreach ( $all_posts as $post ) : ?>
								<label data-title="<?php echo esc_attr( mb_strtolower( $post->post_title ? $post->post_title : '(No title)' ) ); ?>"
									   data-slug="<?php echo esc_attr( mb_strtolower( $post->post_name ) ); ?>"
									   data-status="<?php echo esc_attr( $post->post_status ); ?>"
									   data-date-created="<?php echo esc_attr( strtotime( $post->post_date ) ); ?>"
									   data-date-modified="<?php echo esc_attr( strtotime( $post->post_modified ) ); ?>">
									<input type="checkbox" name="allowed_posts[]" value="<?php echo esc_attr( $post->ID ); ?>" <?php checked( in_array( $post->ID, $allowed_posts ) ); ?>>
									<span>[<?php echo esc_html( $post->ID ); ?>] <?php echo esc_html( $post->post_title ? $post->post_title : __( '(No title)', 'secure-freelancer-access' ) ); ?></span>
									<span class="rpa-content-status">(<?php echo esc_html( $post->post_status ); ?>)</span>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<!-- Action Buttons -->
					<div class="rpa-button-group">
						<button type="button" class="button rpa-select-all" data-target="allowed_posts">
							<?php esc_html_e( 'Select All', 'secure-freelancer-access' ); ?>
						</button>
						<button type="button" class="button rpa-select-published" data-target="allowed_posts">
							<?php esc_html_e( 'Select Published', 'secure-freelancer-access' ); ?>
						</button>
						<button type="button" class="button rpa-deselect-all" data-target="allowed_posts">
							<?php esc_html_e( 'Deselect All', 'secure-freelancer-access' ); ?>
						</button>
					</div>
				</div>

			</div>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'secure-freelancer-access' ); ?>">
			</p>
		</form>

		<p class="rpa-version-info" style="margin-top: 20px; color: #646970; font-size: 12px;">
			<?php
			echo esc_html(
				sprintf(
					__( 'Secure Freelancer Access Plugin - Version %s', 'secure-freelancer-access' ),
					RPA_VERSION
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Display access log.
	 */
	private function render_logs() {
		$logs = get_option( 'rpa_access_logs', array() );

		if ( empty( $logs ) ) {
			echo '<div class="rpa-empty-state">';
			echo '<p>' . esc_html__( 'Access log is empty. No unauthorized access attempts recorded.', 'secure-freelancer-access' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped rpa-logs-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Time', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Attempted Access To', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'IP Address', 'secure-freelancer-access' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td class="rpa-log-time">' . esc_html( $log['time'] ) . '</td>';
			echo '<td class="rpa-log-user">' . esc_html( $log['user_login'] ) . '</td>';
			echo '<td class="rpa-log-post">' . esc_html( $log['post_title'] ) . ' <span class="rpa-content-status">(ID: ' . intval( $log['post_id'] ) . ')</span></td>';
			echo '<td class="rpa-log-ip">' . esc_html( $log['ip'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		?>
		<form method="post" action="" style="margin-top: 20px;">
			<?php wp_nonce_field( 'rpa_clear_logs', 'rpa_nonce' ); ?>
			<input type="hidden" name="rpa_action" value="clear_logs">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Clear Log', 'secure-freelancer-access' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'secure-freelancer-access' ); ?>');">
		</form>
		<?php
	}
}
