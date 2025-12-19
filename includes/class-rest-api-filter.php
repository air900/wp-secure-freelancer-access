<?php
/**
 * Class RPA_REST_API_Filter
 * Handles REST API protection for restricted users.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RPA_REST_API_Filter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_filters' ) );
	}

	/**
	 * Register REST API filters for all enabled post types.
	 */
	public function register_rest_filters() {
		// Filter REST query for posts/pages
		add_filter( 'rest_post_query', array( $this, 'filter_rest_query' ), 10, 2 );
		add_filter( 'rest_page_query', array( $this, 'filter_rest_query' ), 10, 2 );

		// Filter individual post/page response
		add_filter( 'rest_prepare_post', array( $this, 'filter_rest_response' ), 10, 3 );
		add_filter( 'rest_prepare_page', array( $this, 'filter_rest_response' ), 10, 3 );

		// Register filters for custom post types
		$this->register_cpt_filters();
	}

	/**
	 * Register REST filters for custom post types.
	 */
	private function register_cpt_filters() {
		$enabled_types = RPA_Settings::get_all_enabled_post_types();

		foreach ( $enabled_types as $post_type ) {
			// Skip built-in types (already registered above)
			if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
				continue;
			}

			// Check if post type supports REST API
			$type_object = get_post_type_object( $post_type );
			if ( ! $type_object || ! $type_object->show_in_rest ) {
				continue;
			}

			// Register query filter
			add_filter( "rest_{$post_type}_query", array( $this, 'filter_rest_query' ), 10, 2 );

			// Register response filter
			add_filter( "rest_prepare_{$post_type}", array( $this, 'filter_rest_response' ), 10, 3 );
		}
	}

	/**
	 * Filter REST API query to only return allowed posts.
	 *
	 * @param array           $args    Query arguments.
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function filter_rest_query( $args, $request ) {
		// Don't filter for non-restricted users
		if ( ! RPA_Settings::is_current_user_restricted() ) {
			return $args;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $args;
		}

		$post_type = isset( $args['post_type'] ) ? $args['post_type'] : 'post';

		// Check if this post type is enabled for restriction
		if ( ! RPA_Settings::is_post_type_enabled( $post_type ) ) {
			return $args;
		}

		// Get allowed IDs for this post type
		$allowed_ids = $this->get_allowed_ids_for_type( $user_id, $post_type );

		if ( empty( $allowed_ids ) ) {
			// No access - return nothing
			$args['post__in'] = array( 0 );
		} else {
			// Merge with existing post__in if present
			if ( ! empty( $args['post__in'] ) ) {
				$args['post__in'] = array_intersect( $args['post__in'], $allowed_ids );
				if ( empty( $args['post__in'] ) ) {
					$args['post__in'] = array( 0 );
				}
			} else {
				$args['post__in'] = $allowed_ids;
			}
		}

		return $args;
	}

	/**
	 * Filter individual REST API response.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param WP_Post          $post     Post object.
	 * @param WP_REST_Request  $request  Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function filter_rest_response( $response, $post, $request ) {
		// Don't filter for non-restricted users
		if ( ! RPA_Settings::is_current_user_restricted() ) {
			return $response;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $response;
		}

		// Check if this post type is enabled for restriction
		if ( ! RPA_Settings::is_post_type_enabled( $post->post_type ) ) {
			return $response;
		}

		// Check if user has access to this specific post
		$allowed_ids = $this->get_allowed_ids_for_type( $user_id, $post->post_type );

		if ( ! in_array( $post->ID, $allowed_ids, true ) ) {
			return new WP_Error(
				'rpa_forbidden',
				__( 'You do not have permission to access this content.', 'secure-freelancer-access' ),
				array( 'status' => 403 )
			);
		}

		return $response;
	}

	/**
	 * Get allowed post IDs for a specific post type.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $post_type Post type.
	 * @return array
	 */
	private function get_allowed_ids_for_type( $user_id, $post_type ) {
		$allowed_ids = array();

		switch ( $post_type ) {
			case 'page':
				$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_pages( $user_id );
				break;

			case 'post':
				$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_posts( $user_id );
				break;

			default:
				// For custom post types, use generic method
				$allowed_ids = RPA_User_Meta_Handler::get_user_allowed_content( $user_id, $post_type );
				break;
		}

		// Also include posts from allowed categories/taxonomies
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
}
