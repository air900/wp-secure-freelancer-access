<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFAccess_Elementor_Integration
 * Handles Elementor content access restrictions.
 *
 * Supports:
 * - Elementor Templates (elementor_library)
 * - Elementor Theme Builder parts (elementor-hf, elementor-thhf)
 */
class SFAccess_Elementor_Integration {

	/**
	 * Elementor post types and their settings keys.
	 *
	 * @var array
	 */
	private $elementor_types = array(
		'elementor_library' => 'elementor_templates',
		'elementor-hf'      => 'elementor_theme_builder',
		'elementor-thhf'    => 'elementor_theme_builder',
	);

	public function __construct() {
		// Only initialize if Elementor is active
		if ( ! SFAccess_Settings::is_elementor_active() ) {
			return;
		}

		// Filter admin post lists
		add_action( 'pre_get_posts', array( $this, 'filter_elementor_posts_query' ), 20 );

		// Block direct access to edit screens
		add_action( 'load-post.php', array( $this, 'check_elementor_post_access' ) );

		// Filter Elementor AJAX requests
		add_filter( 'elementor/finder/categories', array( $this, 'filter_elementor_finder' ), 20 );

		// Filter template library in editor
		add_filter( 'elementor/template-library/get_template', array( $this, 'filter_template_access' ), 10, 1 );
	}

	/**
	 * Check if current user should be filtered.
	 *
	 * @return bool
	 */
	private function should_filter_user() {
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! SFAccess_Settings::is_current_user_restricted() ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! SFAccess_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			return true;
		}

		return true;
	}

	/**
	 * Check if an Elementor post type is enabled for restriction.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function is_elementor_type_enabled( $post_type ) {
		if ( ! isset( $this->elementor_types[ $post_type ] ) ) {
			return false;
		}

		return SFAccess_Settings::get( $this->elementor_types[ $post_type ], false );
	}

	/**
	 * Get allowed Elementor post IDs for current user.
	 *
	 * @param string $post_type Post type slug.
	 * @return array Array of post IDs.
	 */
	private function get_allowed_elementor_ids( $post_type ) {
		$user_id = get_current_user_id();
		return SFAccess_User_Meta_Handler::get_user_allowed_content( $user_id, $post_type );
	}

	/**
	 * Filter Elementor admin post lists.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function filter_elementor_posts_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $this->should_filter_user() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );

		// Handle array of post types
		if ( is_array( $post_type ) ) {
			return;
		}

		// Check if this is an Elementor type we handle
		if ( ! isset( $this->elementor_types[ $post_type ] ) ) {
			return;
		}

		// Check if restriction is enabled for this type
		if ( ! $this->is_elementor_type_enabled( $post_type ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if access is expired
		if ( ! SFAccess_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$allowed_ids = $this->get_allowed_elementor_ids( $post_type );

		if ( empty( $allowed_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
		} else {
			$query->set( 'post__in', $allowed_ids );
		}
	}

	/**
	 * Block direct access to Elementor post edit screens.
	 */
	public function check_elementor_post_access() {
		if ( ! $this->should_filter_user() ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Check if this is an Elementor type
		if ( ! isset( $this->elementor_types[ $post->post_type ] ) ) {
			return;
		}

		// Check if restriction is enabled
		if ( ! $this->is_elementor_type_enabled( $post->post_type ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if access is expired
		if ( ! SFAccess_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			wp_die(
				esc_html__( 'Your access has expired.', 'secure-freelancer-access' ),
				esc_html__( 'Access Expired', 'secure-freelancer-access' ),
				array( 'response' => 403 )
			);
		}

		// Check if user has access to this specific item
		$allowed_ids = $this->get_allowed_elementor_ids( $post->post_type );

		if ( ! in_array( $post_id, $allowed_ids, true ) ) {
			$this->log_access_attempt( $user_id, $post_id, $post->post_type );
			wp_die(
				esc_html__( 'You do not have permission to edit this template.', 'secure-freelancer-access' ),
				esc_html__( 'Access Denied', 'secure-freelancer-access' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Filter Elementor finder results.
	 *
	 * @param array $categories Finder categories.
	 * @return array Modified finder categories.
	 */
	public function filter_elementor_finder( $categories ) {
		if ( ! $this->should_filter_user() ) {
			return $categories;
		}

		// Filter template results in finder
		if ( isset( $categories['templates'] ) && isset( $categories['templates']['items'] ) ) {
			$user_id = get_current_user_id();

			if ( ! SFAccess_User_Meta_Handler::is_user_access_active( $user_id ) ) {
				$categories['templates']['items'] = array();
				return $categories;
			}

			$allowed_ids = $this->get_allowed_elementor_ids( 'elementor_library' );

			if ( ! empty( $allowed_ids ) ) {
				$categories['templates']['items'] = array_filter(
					$categories['templates']['items'],
					function( $item ) use ( $allowed_ids ) {
						return isset( $item['id'] ) && in_array( $item['id'], $allowed_ids, true );
					}
				);
			} else {
				$categories['templates']['items'] = array();
			}
		}

		return $categories;
	}

	/**
	 * Filter template access when loading in editor.
	 *
	 * @param array $template_data Template data.
	 * @return array|WP_Error Template data or error.
	 */
	public function filter_template_access( $template_data ) {
		if ( ! $this->should_filter_user() ) {
			return $template_data;
		}

		if ( ! isset( $template_data['template_id'] ) ) {
			return $template_data;
		}

		$template_id = intval( $template_data['template_id'] );
		$post = get_post( $template_id );

		if ( ! $post || ! isset( $this->elementor_types[ $post->post_type ] ) ) {
			return $template_data;
		}

		if ( ! $this->is_elementor_type_enabled( $post->post_type ) ) {
			return $template_data;
		}

		$user_id = get_current_user_id();

		if ( ! SFAccess_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			return new WP_Error( 'access_denied', __( 'Your access has expired.', 'secure-freelancer-access' ) );
		}

		$allowed_ids = $this->get_allowed_elementor_ids( $post->post_type );

		if ( ! in_array( $template_id, $allowed_ids, true ) ) {
			return new WP_Error( 'access_denied', __( 'You do not have permission to access this template.', 'secure-freelancer-access' ) );
		}

		return $template_data;
	}

	/**
	 * Log access attempt.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 */
	private function log_access_attempt( $user_id, $post_id, $post_type ) {
		$user       = get_userdata( $user_id );
		$user_login = $user ? $user->user_login : 'Unknown';
		$post       = get_post( $post_id );
		$post_title = $post ? $post->post_title : 'Unknown';
		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'Unknown';

		$message = sprintf(
			'[Secure Freelancer Access] Elementor Access Denied. User: %s (ID: %d). %s: %s (ID: %d). IP: %s',
			$user_login,
			$user_id,
			$post_type,
			$post_title,
			$post_id,
			$ip
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for security audit
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message );
		}

		// Save to DB
		$logs = get_option( 'sfaccess_access_logs', array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$new_log = array(
			'time'       => current_time( 'mysql' ),
			'user_login' => $user_login,
			'post_id'    => $post_id,
			'post_title' => '[Elementor] ' . $post_title,
			'ip'         => $ip,
		);

		array_unshift( $logs, $new_log );

		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, 0, 50 );
		}

		update_option( 'sfaccess_access_logs', $logs, false );
	}
}
