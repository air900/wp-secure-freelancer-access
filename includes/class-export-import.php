<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_Export_Import
 * Handles export and import of plugin data.
 */
class RPA_Export_Import {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
	}

	/**
	 * Handle export request.
	 */
	public function handle_export() {
		if ( ! isset( $_GET['rpa_export'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'rpa_export', 'rpa_export_nonce' );

		$export_data = $this->generate_export_data();

		$filename = 'rpa-export-' . gmdate( 'Y-m-d-His' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Handle import request.
	 */
	public function handle_import() {
		if ( ! isset( $_POST['rpa_action'] ) || 'import_data' !== $_POST['rpa_action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'rpa_import', 'rpa_nonce' );

		// Validate file upload
		if ( ! isset( $_FILES['import_file'] ) ||
			 ! isset( $_FILES['import_file']['error'] ) ||
			 ! isset( $_FILES['import_file']['tmp_name'] ) ||
			 $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_safe_redirect( add_query_arg( array(
				'page' => 'secure-freelancer-access',
				'view' => 'settings',
				'message' => 'import_error',
			), admin_url( 'options-general.php' ) ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File path from PHP, used with file_get_contents
		$tmp_name = sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) );
		$file_content = file_get_contents( $tmp_name );
		$import_data = json_decode( $file_content, true );

		if ( ! $import_data || ! isset( $import_data['version'] ) ) {
			wp_safe_redirect( add_query_arg( array(
				'page' => 'secure-freelancer-access',
				'view' => 'settings',
				'message' => 'import_invalid',
			), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$this->import_data( $import_data );

		wp_safe_redirect( add_query_arg( array(
			'page' => 'secure-freelancer-access',
			'view' => 'settings',
			'message' => 'import_success',
		), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Generate export data.
	 *
	 * @return array Export data.
	 */
	public function generate_export_data() {
		$data = array(
			'version'     => RPA_VERSION,
			'exported_at' => current_time( 'c' ),
			'site_url'    => get_site_url(),
			'settings'    => RPA_Settings::get_settings(),
			'templates'   => RPA_Access_Templates::get_templates(),
			'user_access' => array(),
		);

		// Get all restricted users
		$restricted_roles = RPA_Settings::get( 'restricted_roles', array( 'editor' ) );
		$users = get_users( array( 'role__in' => $restricted_roles ) );

		foreach ( $users as $user ) {
			$user_data = array(
				'user_login' => $user->user_login,
				'user_email' => $user->user_email,
				'pages'      => RPA_User_Meta_Handler::get_user_allowed_pages( $user->ID ),
				'posts'      => RPA_User_Meta_Handler::get_user_allowed_posts( $user->ID ),
				'media'      => RPA_User_Meta_Handler::get_user_allowed_media( $user->ID ),
				'schedule'   => RPA_User_Meta_Handler::get_user_access_schedule( $user->ID ),
			);

			// Get custom post types
			$enabled_types = RPA_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );
			foreach ( $enabled_types as $post_type ) {
				if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
					continue;
				}
				$user_data[ $post_type ] = RPA_User_Meta_Handler::get_user_allowed_content( $user->ID, $post_type );
			}

			// Get taxonomies
			$enabled_taxonomies = RPA_Settings::get( 'enabled_taxonomies', array() );
			foreach ( $enabled_taxonomies as $taxonomy ) {
				$user_data[ 'tax_' . $taxonomy ] = RPA_User_Meta_Handler::get_user_allowed_taxonomy_terms( $user->ID, $taxonomy );
			}

			$data['user_access'][ $user->user_login ] = $user_data;
		}

		return $data;
	}

	/**
	 * Import data.
	 *
	 * @param array $data Import data.
	 * @return bool True on success.
	 */
	public function import_data( $data ) {
		// Import settings
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			RPA_Settings::save_settings( $data['settings'] );
		}

		// Import templates
		if ( isset( $data['templates'] ) && is_array( $data['templates'] ) ) {
			foreach ( $data['templates'] as $template_id => $template ) {
				RPA_Access_Templates::save_template( $template_id, $template );
			}
		}

		// Import user access
		if ( isset( $data['user_access'] ) && is_array( $data['user_access'] ) ) {
			foreach ( $data['user_access'] as $user_login => $user_data ) {
				$user = get_user_by( 'login', $user_login );
				if ( ! $user ) {
					// Try by email
					if ( isset( $user_data['user_email'] ) ) {
						$user = get_user_by( 'email', $user_data['user_email'] );
					}
				}

				if ( ! $user ) {
					continue; // User not found, skip
				}

				// Import pages
				if ( isset( $user_data['pages'] ) ) {
					RPA_User_Meta_Handler::set_user_allowed_pages( $user->ID, $user_data['pages'] );
				}

				// Import posts
				if ( isset( $user_data['posts'] ) ) {
					RPA_User_Meta_Handler::set_user_allowed_posts( $user->ID, $user_data['posts'] );
				}

				// Import media
				if ( isset( $user_data['media'] ) ) {
					RPA_User_Meta_Handler::set_user_allowed_media( $user->ID, $user_data['media'] );
				}

				// Import schedule
				if ( isset( $user_data['schedule'] ) && is_array( $user_data['schedule'] ) ) {
					RPA_User_Meta_Handler::set_user_access_schedule(
						$user->ID,
						$user_data['schedule']['start_date'] ?? null,
						$user_data['schedule']['end_date'] ?? null
					);
				}

				// Import custom post types
				$enabled_types = RPA_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );
				foreach ( $enabled_types as $post_type ) {
					if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
						continue;
					}
					if ( isset( $user_data[ $post_type ] ) ) {
						RPA_User_Meta_Handler::set_user_allowed_content( $user->ID, $post_type, $user_data[ $post_type ] );
					}
				}

				// Import taxonomies
				$enabled_taxonomies = RPA_Settings::get( 'enabled_taxonomies', array() );
				foreach ( $enabled_taxonomies as $taxonomy ) {
					$key = 'tax_' . $taxonomy;
					if ( isset( $user_data[ $key ] ) ) {
						RPA_User_Meta_Handler::set_user_allowed_taxonomy_terms( $user->ID, $taxonomy, $user_data[ $key ] );
					}
				}
			}
		}

		return true;
	}

	/**
	 * Get export URL.
	 *
	 * @return string Export URL.
	 */
	public static function get_export_url() {
		return wp_nonce_url(
			admin_url( 'options-general.php?page=secure-freelancer-access&rpa_export=1' ),
			'rpa_export',
			'rpa_export_nonce'
		);
	}
}
