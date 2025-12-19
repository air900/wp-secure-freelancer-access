<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_Media_Access
 * Handles media library access restrictions.
 *
 * Allowed media for restricted users:
 * 1. Own uploads (post_author = current_user)
 * 2. Media attached to allowed pages/posts
 * 3. Media explicitly assigned by admin
 */
class RPA_Media_Access {

	public function __construct() {
		// Only run if media restriction is enabled
		if ( ! RPA_Settings::get( 'media_restriction', true ) ) {
			return;
		}

		// Filter AJAX media library queries (Grid view)
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_library' ), 10, 1 );

		// Filter list view media queries
		add_action( 'pre_get_posts', array( $this, 'filter_media_list_view' ) );

		// Filter REST API attachment queries
		add_filter( 'rest_attachment_query', array( $this, 'filter_rest_attachments' ), 10, 2 );
	}

	/**
	 * Check if current user should be filtered.
	 *
	 * @return bool
	 */
	private function should_filter_user() {
		// Admins are never filtered
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check if user's role is in restricted list
		if ( ! RPA_Settings::is_current_user_restricted() ) {
			return false;
		}

		// Check temporary access schedule
		$user_id = get_current_user_id();
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			return true; // Access expired - filter everything
		}

		return true;
	}

	/**
	 * Get all allowed media IDs for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of attachment IDs.
	 */
	private function get_allowed_media_ids( $user_id ) {
		$allowed_ids = array();

		// 1. Get user's own uploads
		$own_uploads = get_posts( array(
			'post_type'      => 'attachment',
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		) );
		$allowed_ids = array_merge( $allowed_ids, $own_uploads );

		// 2. Get media attached to allowed pages/posts
		$enabled_post_types = RPA_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );

		foreach ( $enabled_post_types as $post_type ) {
			$allowed_content = RPA_User_Meta_Handler::get_user_allowed_content( $user_id, $post_type );

			if ( ! empty( $allowed_content ) ) {
				// Get attachments attached to these posts
				$attached = get_posts( array(
					'post_type'      => 'attachment',
					'post_parent__in' => $allowed_content,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'post_status'    => 'any',
				) );
				$allowed_ids = array_merge( $allowed_ids, $attached );

				// Get featured images
				foreach ( $allowed_content as $post_id ) {
					$thumbnail_id = get_post_thumbnail_id( $post_id );
					if ( $thumbnail_id ) {
						$allowed_ids[] = $thumbnail_id;
					}
				}

				// Get images from post content (gallery, blocks)
				foreach ( $allowed_content as $post_id ) {
					$content_images = $this->get_images_from_content( $post_id );
					$allowed_ids = array_merge( $allowed_ids, $content_images );
				}
			}
		}

		// 3. Get explicitly assigned media
		$assigned_media = RPA_User_Meta_Handler::get_user_allowed_media( $user_id );
		$allowed_ids = array_merge( $allowed_ids, $assigned_media );

		// Remove duplicates and ensure integers
		$allowed_ids = array_unique( array_map( 'intval', $allowed_ids ) );
		$allowed_ids = array_filter( $allowed_ids );

		return $allowed_ids;
	}

	/**
	 * Extract image IDs from post content.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of attachment IDs.
	 */
	private function get_images_from_content( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$image_ids = array();
		$content = $post->post_content;

		// Match wp:image blocks
		if ( preg_match_all( '/wp:image\s+\{"id":(\d+)/i', $content, $matches ) ) {
			$image_ids = array_merge( $image_ids, $matches[1] );
		}

		// Match wp:gallery blocks
		if ( preg_match_all( '/wp:gallery.*?"ids":\[([\d,]+)\]/i', $content, $matches ) ) {
			foreach ( $matches[1] as $ids_string ) {
				$ids = array_map( 'intval', explode( ',', $ids_string ) );
				$image_ids = array_merge( $image_ids, $ids );
			}
		}

		// Match classic editor gallery shortcodes
		if ( preg_match_all( '/\[gallery[^\]]*ids=["\']?([\d,]+)["\']?/i', $content, $matches ) ) {
			foreach ( $matches[1] as $ids_string ) {
				$ids = array_map( 'intval', explode( ',', $ids_string ) );
				$image_ids = array_merge( $image_ids, $ids );
			}
		}

		// Match img tags with wp-image-{id} class
		if ( preg_match_all( '/class="[^"]*wp-image-(\d+)[^"]*"/i', $content, $matches ) ) {
			$image_ids = array_merge( $image_ids, $matches[1] );
		}

		return array_map( 'intval', $image_ids );
	}

	/**
	 * Filter media library AJAX queries (Grid view).
	 *
	 * @param array $query Query arguments.
	 * @return array Modified query arguments.
	 */
	public function filter_media_library( $query ) {
		if ( ! $this->should_filter_user() ) {
			return $query;
		}

		$user_id = get_current_user_id();

		// Check if access is expired
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$query['post__in'] = array( 0 );
			return $query;
		}

		$allowed_ids = $this->get_allowed_media_ids( $user_id );

		if ( empty( $allowed_ids ) ) {
			$query['post__in'] = array( 0 );
		} else {
			$query['post__in'] = $allowed_ids;
		}

		return $query;
	}

	/**
	 * Filter media list view queries.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function filter_media_list_view( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Check if we're on the media library list page
		global $pagenow;
		if ( 'upload.php' !== $pagenow ) {
			return;
		}

		if ( ! $this->should_filter_user() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if access is expired
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$allowed_ids = $this->get_allowed_media_ids( $user_id );

		if ( empty( $allowed_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
		} else {
			$query->set( 'post__in', $allowed_ids );
		}
	}

	/**
	 * Filter REST API attachment queries.
	 *
	 * @param array           $args    Query arguments.
	 * @param WP_REST_Request $request The request object.
	 * @return array Modified query arguments.
	 */
	public function filter_rest_attachments( $args, $request ) {
		if ( ! $this->should_filter_user() ) {
			return $args;
		}

		$user_id = get_current_user_id();

		// Check if access is expired
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$args['post__in'] = array( 0 );
			return $args;
		}

		$allowed_ids = $this->get_allowed_media_ids( $user_id );

		if ( empty( $allowed_ids ) ) {
			$args['post__in'] = array( 0 );
		} else {
			$args['post__in'] = $allowed_ids;
		}

		return $args;
	}
}
