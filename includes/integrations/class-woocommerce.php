<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RPA_WooCommerce_Integration
 * Handles WooCommerce content access restrictions.
 *
 * Supports:
 * - Products (product)
 * - Orders (shop_order)
 * - Coupons (shop_coupon)
 */
class RPA_WooCommerce_Integration {

	/**
	 * WooCommerce post types and their settings keys.
	 *
	 * @var array
	 */
	private $wc_types = array(
		'product'     => 'woocommerce_products',
		'shop_order'  => 'woocommerce_orders',
		'shop_coupon' => 'woocommerce_coupons',
	);

	public function __construct() {
		// Only initialize if WooCommerce is active
		if ( ! RPA_Settings::is_woocommerce_active() ) {
			return;
		}

		// Filter admin post lists
		add_action( 'pre_get_posts', array( $this, 'filter_wc_posts_query' ), 20 );

		// Block direct access to edit screens
		add_action( 'load-post.php', array( $this, 'check_wc_post_access' ) );

		// Filter WooCommerce REST API
		add_filter( 'woocommerce_rest_product_object_query', array( $this, 'filter_wc_rest_query' ), 10, 2 );
		add_filter( 'woocommerce_rest_shop_order_object_query', array( $this, 'filter_wc_rest_query' ), 10, 2 );
		add_filter( 'woocommerce_rest_shop_coupon_object_query', array( $this, 'filter_wc_rest_query' ), 10, 2 );

		// Filter WC Admin (HPOS for orders)
		add_filter( 'woocommerce_order_query_args', array( $this, 'filter_wc_order_query' ), 10, 1 );
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

		if ( ! RPA_Settings::is_current_user_restricted() ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			return true;
		}

		return true;
	}

	/**
	 * Check if a WooCommerce post type is enabled for restriction.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function is_wc_type_enabled( $post_type ) {
		if ( ! isset( $this->wc_types[ $post_type ] ) ) {
			return false;
		}

		return RPA_Settings::get( $this->wc_types[ $post_type ], false );
	}

	/**
	 * Get allowed WC post IDs for current user.
	 *
	 * @param string $post_type Post type slug.
	 * @return array Array of post IDs.
	 */
	private function get_allowed_wc_ids( $post_type ) {
		$user_id = get_current_user_id();
		return RPA_User_Meta_Handler::get_user_allowed_content( $user_id, $post_type );
	}

	/**
	 * Filter WooCommerce admin post lists.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function filter_wc_posts_query( $query ) {
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

		// Check if this is a WC type we handle
		if ( ! isset( $this->wc_types[ $post_type ] ) ) {
			return;
		}

		// Check if restriction is enabled for this type
		if ( ! $this->is_wc_type_enabled( $post_type ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if access is expired
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$allowed_ids = $this->get_allowed_wc_ids( $post_type );

		if ( empty( $allowed_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
		} else {
			$query->set( 'post__in', $allowed_ids );
		}
	}

	/**
	 * Block direct access to WC post edit screens.
	 */
	public function check_wc_post_access() {
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

		// Check if this is a WC type
		if ( ! isset( $this->wc_types[ $post->post_type ] ) ) {
			return;
		}

		// Check if restriction is enabled
		if ( ! $this->is_wc_type_enabled( $post->post_type ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if access is expired
		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			wp_die(
				esc_html__( 'Your access has expired.', 'secure-freelancer-access' ),
				esc_html__( 'Access Expired', 'secure-freelancer-access' ),
				array( 'response' => 403 )
			);
		}

		// Check if user has access to this specific item
		$allowed_ids = $this->get_allowed_wc_ids( $post->post_type );

		if ( ! in_array( $post_id, $allowed_ids, true ) ) {
			$this->log_access_attempt( $user_id, $post_id, $post->post_type );
			wp_die(
				esc_html__( 'You do not have permission to edit this content.', 'secure-freelancer-access' ),
				esc_html__( 'Access Denied', 'secure-freelancer-access' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Filter WooCommerce REST API queries.
	 *
	 * @param array           $args    Query arguments.
	 * @param WP_REST_Request $request The request object.
	 * @return array Modified query arguments.
	 */
	public function filter_wc_rest_query( $args, $request ) {
		if ( ! $this->should_filter_user() ) {
			return $args;
		}

		// Determine post type from filter
		$post_type = 'product';
		$current_filter = current_filter();
		if ( strpos( $current_filter, 'shop_order' ) !== false ) {
			$post_type = 'shop_order';
		} elseif ( strpos( $current_filter, 'shop_coupon' ) !== false ) {
			$post_type = 'shop_coupon';
		}

		if ( ! $this->is_wc_type_enabled( $post_type ) ) {
			return $args;
		}

		$user_id = get_current_user_id();

		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$args['include'] = array( 0 );
			return $args;
		}

		$allowed_ids = $this->get_allowed_wc_ids( $post_type );

		if ( empty( $allowed_ids ) ) {
			$args['include'] = array( 0 );
		} else {
			$args['include'] = $allowed_ids;
		}

		return $args;
	}

	/**
	 * Filter WooCommerce order queries (HPOS compatible).
	 *
	 * @param array $args Query arguments.
	 * @return array Modified query arguments.
	 */
	public function filter_wc_order_query( $args ) {
		if ( ! $this->should_filter_user() ) {
			return $args;
		}

		if ( ! $this->is_wc_type_enabled( 'shop_order' ) ) {
			return $args;
		}

		$user_id = get_current_user_id();

		if ( ! RPA_User_Meta_Handler::is_user_access_active( $user_id ) ) {
			$args['include'] = array( 0 );
			return $args;
		}

		$allowed_ids = $this->get_allowed_wc_ids( 'shop_order' );

		if ( empty( $allowed_ids ) ) {
			$args['include'] = array( 0 );
		} else {
			$args['include'] = $allowed_ids;
		}

		return $args;
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
			'[Secure Freelancer Access] WC Access Denied. User: %s (ID: %d). %s: %s (ID: %d). IP: %s',
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
		$logs = get_option( 'rpa_access_logs', array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$new_log = array(
			'time'       => current_time( 'mysql' ),
			'user_login' => $user_login,
			'post_id'    => $post_id,
			'post_title' => '[' . $post_type . '] ' . $post_title,
			'ip'         => $ip,
		);

		array_unshift( $logs, $new_log );

		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, 0, 50 );
		}

		update_option( 'rpa_access_logs', $logs, false );
	}
}
