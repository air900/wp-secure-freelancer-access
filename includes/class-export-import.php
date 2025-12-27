<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFAccess_Export_Import
 * Handles export and import of plugin data.
 */
class SFAccess_Export_Import {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
	}

	/**
	 * Handle export request.
	 */
	public function handle_export() {
		if ( ! isset( $_GET['sfaccess_export'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'sfaccess_export', 'sfaccess_export_nonce' );

		$export_data = $this->generate_export_data();

		$filename = 'sfaccess-export-' . gmdate( 'Y-m-d-His' ) . '.json';

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
		if ( ! isset( $_POST['sfaccess_action'] ) || 'import_data' !== $_POST['sfaccess_action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'sfaccess_import', 'sfaccess_nonce' );

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
			'version'     => SFAccess_VERSION,
			'exported_at' => current_time( 'c' ),
			'site_url'    => get_site_url(),
			'settings'    => SFAccess_Settings::get_settings(),
			'templates'   => SFAccess_Access_Templates::get_templates(),
			'user_access' => array(),
		);

		// Get all restricted users
		$restricted_roles = SFAccess_Settings::get( 'restricted_roles', array( 'editor' ) );
		$users = get_users( array( 'role__in' => $restricted_roles ) );

		foreach ( $users as $user ) {
			$user_data = array(
				'user_login' => $user->user_login,
				'user_email' => $user->user_email,
				'pages'      => SFAccess_User_Meta_Handler::get_user_allowed_pages( $user->ID ),
				'posts'      => SFAccess_User_Meta_Handler::get_user_allowed_posts( $user->ID ),
				'media'      => SFAccess_User_Meta_Handler::get_user_allowed_media( $user->ID ),
				'schedule'   => SFAccess_User_Meta_Handler::get_user_access_schedule( $user->ID ),
			);

			// Get custom post types
			$enabled_types = SFAccess_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );
			foreach ( $enabled_types as $post_type ) {
				if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
					continue;
				}
				$user_data[ $post_type ] = SFAccess_User_Meta_Handler::get_user_allowed_content( $user->ID, $post_type );
			}

			// Get taxonomies
			$enabled_taxonomies = SFAccess_Settings::get( 'enabled_taxonomies', array() );
			foreach ( $enabled_taxonomies as $taxonomy ) {
				$user_data[ 'tax_' . $taxonomy ] = SFAccess_User_Meta_Handler::get_user_allowed_taxonomy_terms( $user->ID, $taxonomy );
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
			SFAccess_Settings::save_settings( $data['settings'] );
		}

		// Import templates
		if ( isset( $data['templates'] ) && is_array( $data['templates'] ) ) {
			foreach ( $data['templates'] as $template_id => $template ) {
				SFAccess_Access_Templates::save_template( $template_id, $template );
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
					SFAccess_User_Meta_Handler::set_user_allowed_pages( $user->ID, $user_data['pages'] );
				}

				// Import posts
				if ( isset( $user_data['posts'] ) ) {
					SFAccess_User_Meta_Handler::set_user_allowed_posts( $user->ID, $user_data['posts'] );
				}

				// Import media
				if ( isset( $user_data['media'] ) ) {
					SFAccess_User_Meta_Handler::set_user_allowed_media( $user->ID, $user_data['media'] );
				}

				// Import schedule
				if ( isset( $user_data['schedule'] ) && is_array( $user_data['schedule'] ) ) {
					SFAccess_User_Meta_Handler::set_user_access_schedule(
						$user->ID,
						$user_data['schedule']['start_date'] ?? null,
						$user_data['schedule']['end_date'] ?? null
					);
				}

				// Import custom post types
				$enabled_types = SFAccess_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );
				foreach ( $enabled_types as $post_type ) {
					if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
						continue;
					}
					if ( isset( $user_data[ $post_type ] ) ) {
						SFAccess_User_Meta_Handler::set_user_allowed_content( $user->ID, $post_type, $user_data[ $post_type ] );
					}
				}

				// Import taxonomies
				$enabled_taxonomies = SFAccess_Settings::get( 'enabled_taxonomies', array() );
				foreach ( $enabled_taxonomies as $taxonomy ) {
					$key = 'tax_' . $taxonomy;
					if ( isset( $user_data[ $key ] ) ) {
						SFAccess_User_Meta_Handler::set_user_allowed_taxonomy_terms( $user->ID, $taxonomy, $user_data[ $key ] );
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
			admin_url( 'options-general.php?page=secure-freelancer-access&sfaccess_export=1' ),
			'sfaccess_export',
			'sfaccess_export_nonce'
		);
	}
}
