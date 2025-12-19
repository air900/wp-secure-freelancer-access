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

		// Save plugin settings
		if ( 'save_settings' === $_POST['rpa_action'] ) {
			check_admin_referer( 'rpa_save_settings', 'rpa_nonce' );

			$settings = array(
				'restricted_roles'        => isset( $_POST['restricted_roles'] ) ? (array) $_POST['restricted_roles'] : array(),
				'enabled_post_types'      => isset( $_POST['enabled_post_types'] ) ? (array) $_POST['enabled_post_types'] : array(),
				'enabled_taxonomies'      => isset( $_POST['enabled_taxonomies'] ) ? (array) $_POST['enabled_taxonomies'] : array(),
				'media_restriction'       => ! empty( $_POST['media_restriction'] ),
				'woocommerce_products'    => ! empty( $_POST['woocommerce_products'] ),
				'woocommerce_orders'      => ! empty( $_POST['woocommerce_orders'] ),
				'woocommerce_coupons'     => ! empty( $_POST['woocommerce_coupons'] ),
				'elementor_templates'     => ! empty( $_POST['elementor_templates'] ),
				'elementor_theme_builder' => ! empty( $_POST['elementor_theme_builder'] ),
			);

			RPA_Settings::save_settings( $settings );

			wp_redirect( add_query_arg( array( 'page' => $this->page_slug, 'view' => 'settings', 'message' => 'settings_saved' ), admin_url( 'options-general.php' ) ) );
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
				'users'    => __( 'Users', 'secure-freelancer-access' ),
				'logs'     => __( 'Access Log', 'secure-freelancer-access' ),
				'settings' => __( 'Settings', 'secure-freelancer-access' ),
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
			if ( isset( $_GET['message'] ) && 'settings_saved' === $_GET['message'] ) {
				echo '<div class="notice notice-success is-dismissible rpa-notice"><p>' . esc_html__( 'Settings saved successfully.', 'secure-freelancer-access' ) . '</p></div>';
			}

			// Display appropriate view
			if ( $edit_user_id ) {
				$this->render_edit_form( $edit_user_id );
			} elseif ( 'logs' === $current_view ) {
				$this->render_logs();
			} elseif ( 'settings' === $current_view ) {
				$this->render_settings();
			} else {
				$this->render_user_list();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Display list of restricted users.
	 */
	private function render_user_list() {
		$restricted_roles = RPA_Settings::get( 'restricted_roles', array( 'editor' ) );

		if ( empty( $restricted_roles ) ) {
			echo '<div class="rpa-empty-state">';
			echo '<p>' . esc_html__( 'No roles selected for restriction. Go to Settings tab to configure.', 'secure-freelancer-access' ) . '</p>';
			echo '</div>';
			return;
		}

		// Get users with any of the restricted roles
		$users = get_users( array( 'role__in' => $restricted_roles ) );

		if ( empty( $users ) ) {
			$role_names = array_map( function( $role ) {
				$wp_roles = wp_roles();
				return isset( $wp_roles->roles[ $role ] ) ? translate_user_role( $wp_roles->roles[ $role ]['name'] ) : $role;
			}, $restricted_roles );

			echo '<div class="rpa-empty-state">';
			echo '<p>' . esc_html( sprintf(
				/* translators: %s: list of role names */
				__( 'No users found with roles: %s', 'secure-freelancer-access' ),
				implode( ', ', $role_names )
			) ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'User', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Role', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Access (Pages)', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Access (Posts)', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Schedule', 'secure-freelancer-access' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'secure-freelancer-access' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $users as $user ) {
			$allowed_pages = RPA_User_Meta_Handler::get_user_allowed_pages( $user->ID );
			$allowed_posts = RPA_User_Meta_Handler::get_user_allowed_posts( $user->ID );
			$schedule = RPA_User_Meta_Handler::get_user_access_schedule( $user->ID );
			$is_active = RPA_User_Meta_Handler::is_user_access_active( $user->ID );

			// Get user role name
			$user_roles = array_intersect( $user->roles, $restricted_roles );
			$role_name = '';
			if ( ! empty( $user_roles ) ) {
				$wp_roles = wp_roles();
				$role_key = reset( $user_roles );
				$role_name = isset( $wp_roles->roles[ $role_key ] ) ? translate_user_role( $wp_roles->roles[ $role_key ]['name'] ) : $role_key;
			}

			$edit_link = add_query_arg( array( 'page' => $this->page_slug, 'edit_user' => $user->ID ), admin_url( 'options-general.php' ) );

			$schedule_text = '-';
			$schedule_class = '';
			if ( $schedule ) {
				if ( ! $is_active ) {
					$schedule_text = __( 'Expired', 'secure-freelancer-access' );
					$schedule_class = 'rpa-schedule-expired';
				} elseif ( ! empty( $schedule['end_date'] ) ) {
					$schedule_text = sprintf(
						/* translators: %s: end date */
						__( 'Until %s', 'secure-freelancer-access' ),
						date_i18n( get_option( 'date_format' ), strtotime( $schedule['end_date'] ) )
					);
					$schedule_class = 'rpa-schedule-active';
				}
			}

			echo '<tr' . ( ! $is_active ? ' class="rpa-row-expired"' : '' ) . '>';
			echo '<td><strong>' . esc_html( $user->display_name ) . '</strong><br><small>' . esc_html( $user->user_login ) . '</small></td>';
			echo '<td><span class="rpa-role-badge">' . esc_html( $role_name ) . '</span></td>';
			echo '<td><span class="rpa-user-count">' . count( $allowed_pages ) . '</span></td>';
			echo '<td><span class="rpa-user-count">' . count( $allowed_posts ) . '</span></td>';
			echo '<td><span class="' . esc_attr( $schedule_class ) . '">' . esc_html( $schedule_text ) . '</span></td>';
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

	/**
	 * Display plugin settings.
	 */
	private function render_settings() {
		$settings = RPA_Settings::get_settings();
		$available_roles = RPA_Settings::get_available_roles();
		$available_post_types = RPA_Settings::get_available_post_types();
		$available_taxonomies = RPA_Settings::get_available_taxonomies();

		$is_woocommerce_active = RPA_Settings::is_woocommerce_active();
		$is_elementor_active = RPA_Settings::is_elementor_active();
		$is_elementor_pro_active = RPA_Settings::is_elementor_pro_active();

		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'rpa_save_settings', 'rpa_nonce' ); ?>
			<input type="hidden" name="rpa_action" value="save_settings">

			<div class="rpa-settings-container">

				<!-- Restricted Roles Section -->
				<div class="rpa-settings-section">
					<h3><?php esc_html_e( 'Restricted Roles', 'secure-freelancer-access' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Select which user roles should have restricted content access. Administrators are never restricted.', 'secure-freelancer-access' ); ?></p>

					<div class="rpa-checkbox-grid">
						<?php foreach ( $available_roles as $role_key => $role_name ) : ?>
							<label class="rpa-checkbox-item">
								<input type="checkbox" name="restricted_roles[]" value="<?php echo esc_attr( $role_key ); ?>"
									<?php checked( in_array( $role_key, $settings['restricted_roles'], true ) ); ?>>
								<span><?php echo esc_html( $role_name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Content Types Section -->
				<div class="rpa-settings-section">
					<h3><?php esc_html_e( 'Content Types', 'secure-freelancer-access' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Select which content types should be restricted for the selected roles.', 'secure-freelancer-access' ); ?></p>

					<div class="rpa-checkbox-grid">
						<?php foreach ( $available_post_types as $type_key => $type_name ) : ?>
							<label class="rpa-checkbox-item">
								<input type="checkbox" name="enabled_post_types[]" value="<?php echo esc_attr( $type_key ); ?>"
									<?php checked( in_array( $type_key, $settings['enabled_post_types'], true ) ); ?>>
								<span><?php echo esc_html( $type_name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Taxonomies Section -->
				<div class="rpa-settings-section">
					<h3><?php esc_html_e( 'Taxonomies (Category-based Access)', 'secure-freelancer-access' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Enable access control by taxonomies (categories, tags). Users can be granted access to all content within specific categories.', 'secure-freelancer-access' ); ?></p>

					<div class="rpa-checkbox-grid">
						<?php foreach ( $available_taxonomies as $tax_key => $tax_name ) : ?>
							<label class="rpa-checkbox-item">
								<input type="checkbox" name="enabled_taxonomies[]" value="<?php echo esc_attr( $tax_key ); ?>"
									<?php checked( in_array( $tax_key, $settings['enabled_taxonomies'], true ) ); ?>>
								<span><?php echo esc_html( $tax_name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Media Library Section -->
				<div class="rpa-settings-section">
					<h3><?php esc_html_e( 'Media Library', 'secure-freelancer-access' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Control access to media files in the Media Library.', 'secure-freelancer-access' ); ?></p>

					<label class="rpa-checkbox-item">
						<input type="checkbox" name="media_restriction" value="1"
							<?php checked( $settings['media_restriction'] ); ?>>
						<span><?php esc_html_e( 'Restrict media library access', 'secure-freelancer-access' ); ?></span>
					</label>
					<p class="description" style="margin-left: 24px;"><?php esc_html_e( 'Users will only see: their own uploads, media attached to allowed pages/posts, and additionally assigned media files.', 'secure-freelancer-access' ); ?></p>
				</div>

				<!-- WooCommerce Integration -->
				<div class="rpa-settings-section <?php echo ! $is_woocommerce_active ? 'rpa-section-disabled' : ''; ?>">
					<h3>
						<?php esc_html_e( 'WooCommerce Integration', 'secure-freelancer-access' ); ?>
						<?php if ( ! $is_woocommerce_active ) : ?>
							<span class="rpa-plugin-status rpa-inactive"><?php esc_html_e( 'Not installed', 'secure-freelancer-access' ); ?></span>
						<?php else : ?>
							<span class="rpa-plugin-status rpa-active"><?php esc_html_e( 'Active', 'secure-freelancer-access' ); ?></span>
						<?php endif; ?>
					</h3>

					<?php if ( ! $is_woocommerce_active ) : ?>
						<p class="description"><?php esc_html_e( 'Install and activate WooCommerce to enable these options.', 'secure-freelancer-access' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Control access to WooCommerce content.', 'secure-freelancer-access' ); ?></p>
					<?php endif; ?>

					<div class="rpa-checkbox-grid">
						<label class="rpa-checkbox-item">
							<input type="checkbox" name="woocommerce_products" value="1"
								<?php checked( $settings['woocommerce_products'] ); ?>
								<?php disabled( ! $is_woocommerce_active ); ?>>
							<span><?php esc_html_e( 'Products', 'secure-freelancer-access' ); ?></span>
						</label>
						<label class="rpa-checkbox-item">
							<input type="checkbox" name="woocommerce_orders" value="1"
								<?php checked( $settings['woocommerce_orders'] ); ?>
								<?php disabled( ! $is_woocommerce_active ); ?>>
							<span><?php esc_html_e( 'Orders', 'secure-freelancer-access' ); ?></span>
						</label>
						<label class="rpa-checkbox-item">
							<input type="checkbox" name="woocommerce_coupons" value="1"
								<?php checked( $settings['woocommerce_coupons'] ); ?>
								<?php disabled( ! $is_woocommerce_active ); ?>>
							<span><?php esc_html_e( 'Coupons', 'secure-freelancer-access' ); ?></span>
						</label>
					</div>
				</div>

				<!-- Elementor Integration -->
				<div class="rpa-settings-section <?php echo ! $is_elementor_active ? 'rpa-section-disabled' : ''; ?>">
					<h3>
						<?php esc_html_e( 'Elementor Integration', 'secure-freelancer-access' ); ?>
						<?php if ( ! $is_elementor_active ) : ?>
							<span class="rpa-plugin-status rpa-inactive"><?php esc_html_e( 'Not installed', 'secure-freelancer-access' ); ?></span>
						<?php else : ?>
							<span class="rpa-plugin-status rpa-active"><?php esc_html_e( 'Active', 'secure-freelancer-access' ); ?></span>
						<?php endif; ?>
					</h3>

					<?php if ( ! $is_elementor_active ) : ?>
						<p class="description"><?php esc_html_e( 'Install and activate Elementor to enable these options.', 'secure-freelancer-access' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Control access to Elementor templates and theme builder elements.', 'secure-freelancer-access' ); ?></p>
					<?php endif; ?>

					<div class="rpa-checkbox-grid">
						<label class="rpa-checkbox-item">
							<input type="checkbox" name="elementor_templates" value="1"
								<?php checked( $settings['elementor_templates'] ); ?>
								<?php disabled( ! $is_elementor_active ); ?>>
							<span><?php esc_html_e( 'Saved Templates', 'secure-freelancer-access' ); ?></span>
						</label>
						<label class="rpa-checkbox-item">
							<input type="checkbox" name="elementor_theme_builder" value="1"
								<?php checked( $settings['elementor_theme_builder'] ); ?>
								<?php disabled( ! $is_elementor_pro_active ); ?>>
							<span>
								<?php esc_html_e( 'Theme Builder', 'secure-freelancer-access' ); ?>
								<?php if ( $is_elementor_active && ! $is_elementor_pro_active ) : ?>
									<em class="rpa-requires-pro">(<?php esc_html_e( 'Requires Elementor Pro', 'secure-freelancer-access' ); ?>)</em>
								<?php endif; ?>
							</span>
						</label>
					</div>
				</div>

			</div>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'secure-freelancer-access' ); ?>">
			</p>
		</form>
		<?php
	}
}
