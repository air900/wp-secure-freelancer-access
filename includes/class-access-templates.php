<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_Access_Templates
 * Handles access templates for quick permission assignment.
 *
 * Templates are stored in wp_options and can be applied to users manually.
 */
class RPA_Access_Templates {

	const OPTION_NAME = 'rpa_templates';

	/**
	 * Get all templates.
	 *
	 * @return array Array of templates.
	 */
	public static function get_templates() {
		$templates = get_option( self::OPTION_NAME, array() );
		return is_array( $templates ) ? $templates : array();
	}

	/**
	 * Get a single template by ID.
	 *
	 * @param string $template_id Template ID.
	 * @return array|null Template data or null if not found.
	 */
	public static function get_template( $template_id ) {
		$templates = self::get_templates();
		return isset( $templates[ $template_id ] ) ? $templates[ $template_id ] : null;
	}

	/**
	 * Save a template.
	 *
	 * @param string $template_id Template ID (generated if empty).
	 * @param array  $data        Template data.
	 * @return string Template ID.
	 */
	public static function save_template( $template_id, $data ) {
		$templates = self::get_templates();

		if ( empty( $template_id ) ) {
			$template_id = 'tpl_' . wp_generate_uuid4();
		}

		$templates[ $template_id ] = array(
			'id'          => $template_id,
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'created'     => isset( $templates[ $template_id ]['created'] ) ? $templates[ $template_id ]['created'] : current_time( 'mysql' ),
			'modified'    => current_time( 'mysql' ),
			'content'     => self::sanitize_template_content( $data['content'] ?? array() ),
		);

		update_option( self::OPTION_NAME, $templates );

		return $template_id;
	}

	/**
	 * Delete a template.
	 *
	 * @param string $template_id Template ID.
	 * @return bool True on success.
	 */
	public static function delete_template( $template_id ) {
		$templates = self::get_templates();

		if ( isset( $templates[ $template_id ] ) ) {
			unset( $templates[ $template_id ] );
			update_option( self::OPTION_NAME, $templates );
			return true;
		}

		return false;
	}

	/**
	 * Apply a template to a user.
	 *
	 * @param string $template_id Template ID.
	 * @param int    $user_id     User ID.
	 * @param bool   $merge       Whether to merge with existing access (true) or replace (false).
	 * @return bool True on success.
	 */
	public static function apply_to_user( $template_id, $user_id, $merge = false ) {
		$template = self::get_template( $template_id );
		if ( ! $template ) {
			return false;
		}

		$content = $template['content'];

		// Apply pages
		if ( isset( $content['page'] ) ) {
			if ( $merge ) {
				$existing = RPA_User_Meta_Handler::get_user_allowed_pages( $user_id );
				$content['page'] = array_unique( array_merge( $existing, $content['page'] ) );
			}
			RPA_User_Meta_Handler::set_user_allowed_pages( $user_id, $content['page'] );
		}

		// Apply posts
		if ( isset( $content['post'] ) ) {
			if ( $merge ) {
				$existing = RPA_User_Meta_Handler::get_user_allowed_posts( $user_id );
				$content['post'] = array_unique( array_merge( $existing, $content['post'] ) );
			}
			RPA_User_Meta_Handler::set_user_allowed_posts( $user_id, $content['post'] );
		}

		// Apply custom post types
		$enabled_types = RPA_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );
		foreach ( $enabled_types as $post_type ) {
			if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
				continue; // Already handled above
			}
			if ( isset( $content[ $post_type ] ) ) {
				if ( $merge ) {
					$existing = RPA_User_Meta_Handler::get_user_allowed_content( $user_id, $post_type );
					$content[ $post_type ] = array_unique( array_merge( $existing, $content[ $post_type ] ) );
				}
				RPA_User_Meta_Handler::set_user_allowed_content( $user_id, $post_type, $content[ $post_type ] );
			}
		}

		// Apply taxonomies
		$enabled_taxonomies = RPA_Settings::get( 'enabled_taxonomies', array() );
		foreach ( $enabled_taxonomies as $taxonomy ) {
			$key = 'tax_' . $taxonomy;
			if ( isset( $content[ $key ] ) ) {
				if ( $merge ) {
					$existing = RPA_User_Meta_Handler::get_user_allowed_taxonomy_terms( $user_id, $taxonomy );
					$content[ $key ] = array_unique( array_merge( $existing, $content[ $key ] ) );
				}
				RPA_User_Meta_Handler::set_user_allowed_taxonomy_terms( $user_id, $taxonomy, $content[ $key ] );
			}
		}

		// Apply media
		if ( isset( $content['media'] ) ) {
			if ( $merge ) {
				$existing = RPA_User_Meta_Handler::get_user_allowed_media( $user_id );
				$content['media'] = array_unique( array_merge( $existing, $content['media'] ) );
			}
			RPA_User_Meta_Handler::set_user_allowed_media( $user_id, $content['media'] );
		}

		return true;
	}

	/**
	 * Create a template from user's current access.
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Template name.
	 * @param string $description Template description.
	 * @return string Template ID.
	 */
	public static function create_from_user( $user_id, $name, $description = '' ) {
		$content = array();

		// Get pages
		$pages = RPA_User_Meta_Handler::get_user_allowed_pages( $user_id );
		if ( ! empty( $pages ) ) {
			$content['page'] = $pages;
		}

		// Get posts
		$posts = RPA_User_Meta_Handler::get_user_allowed_posts( $user_id );
		if ( ! empty( $posts ) ) {
			$content['post'] = $posts;
		}

		// Get custom post types
		$enabled_types = RPA_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );
		foreach ( $enabled_types as $post_type ) {
			if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
				continue;
			}
			$cpt_content = RPA_User_Meta_Handler::get_user_allowed_content( $user_id, $post_type );
			if ( ! empty( $cpt_content ) ) {
				$content[ $post_type ] = $cpt_content;
			}
		}

		// Get taxonomies
		$enabled_taxonomies = RPA_Settings::get( 'enabled_taxonomies', array() );
		foreach ( $enabled_taxonomies as $taxonomy ) {
			$terms = RPA_User_Meta_Handler::get_user_allowed_taxonomy_terms( $user_id, $taxonomy );
			if ( ! empty( $terms ) ) {
				$content[ 'tax_' . $taxonomy ] = $terms;
			}
		}

		// Get media
		$media = RPA_User_Meta_Handler::get_user_allowed_media( $user_id );
		if ( ! empty( $media ) ) {
			$content['media'] = $media;
		}

		return self::save_template( '', array(
			'name'        => $name,
			'description' => $description,
			'content'     => $content,
		) );
	}

	/**
	 * Sanitize template content.
	 *
	 * @param array $content Template content.
	 * @return array Sanitized content.
	 */
	private static function sanitize_template_content( $content ) {
		if ( ! is_array( $content ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $content as $key => $ids ) {
			$key = sanitize_key( $key );
			if ( is_array( $ids ) ) {
				$sanitized[ $key ] = array_map( 'intval', $ids );
				$sanitized[ $key ] = array_filter( $sanitized[ $key ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Get template summary (counts of items).
	 *
	 * @param string $template_id Template ID.
	 * @return array Summary with counts.
	 */
	public static function get_template_summary( $template_id ) {
		$template = self::get_template( $template_id );
		if ( ! $template ) {
			return array();
		}

		$summary = array();
		$content = $template['content'];

		if ( isset( $content['page'] ) ) {
			$summary['pages'] = count( $content['page'] );
		}
		if ( isset( $content['post'] ) ) {
			$summary['posts'] = count( $content['post'] );
		}
		if ( isset( $content['media'] ) ) {
			$summary['media'] = count( $content['media'] );
		}

		// Count other post types
		foreach ( $content as $key => $ids ) {
			if ( ! in_array( $key, array( 'page', 'post', 'media' ), true ) && strpos( $key, 'tax_' ) !== 0 ) {
				$post_type = get_post_type_object( $key );
				if ( $post_type ) {
					$summary[ $post_type->labels->name ] = count( $ids );
				}
			}
		}

		// Count taxonomies
		foreach ( $content as $key => $ids ) {
			if ( strpos( $key, 'tax_' ) === 0 ) {
				$taxonomy = substr( $key, 4 );
				$tax_object = get_taxonomy( $taxonomy );
				if ( $tax_object ) {
					$summary[ $tax_object->labels->name ] = count( $ids );
				}
			}
		}

		return $summary;
	}
}
