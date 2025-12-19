<?php
/**
 * Class RPA_Settings
 * Handles plugin settings - roles, content types, and general options.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RPA_Settings {

	/**
	 * Option name for storing settings.
	 */
	const OPTION_NAME = 'rpa_settings';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private static $defaults = array(
		'restricted_roles'      => array( 'editor' ),
		'enabled_post_types'    => array( 'page', 'post' ),
		'enabled_taxonomies'    => array(),
		'media_restriction'     => true,
		'woocommerce_products'  => false,
		'woocommerce_orders'    => false,
		'woocommerce_coupons'   => false,
		'elementor_templates'   => false,
		'elementor_theme_builder' => false,
	);

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $settings, self::$defaults );
	}

	/**
	 * Get a specific setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_settings();
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		return $default !== null ? $default : ( isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : null );
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool
	 */
	public static function save_settings( $settings ) {
		$sanitized = self::sanitize_settings( $settings );
		return update_option( self::OPTION_NAME, $sanitized );
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	private static function sanitize_settings( $settings ) {
		$sanitized = array();

		// Restricted roles - array of role slugs
		if ( isset( $settings['restricted_roles'] ) && is_array( $settings['restricted_roles'] ) ) {
			$sanitized['restricted_roles'] = array_map( 'sanitize_key', $settings['restricted_roles'] );
		} else {
			$sanitized['restricted_roles'] = self::$defaults['restricted_roles'];
		}

		// Enabled post types - array of post type slugs
		if ( isset( $settings['enabled_post_types'] ) && is_array( $settings['enabled_post_types'] ) ) {
			$sanitized['enabled_post_types'] = array_map( 'sanitize_key', $settings['enabled_post_types'] );
		} else {
			$sanitized['enabled_post_types'] = self::$defaults['enabled_post_types'];
		}

		// Enabled taxonomies
		if ( isset( $settings['enabled_taxonomies'] ) && is_array( $settings['enabled_taxonomies'] ) ) {
			$sanitized['enabled_taxonomies'] = array_map( 'sanitize_key', $settings['enabled_taxonomies'] );
		} else {
			$sanitized['enabled_taxonomies'] = array();
		}

		// Boolean settings
		$sanitized['media_restriction'] = ! empty( $settings['media_restriction'] );
		$sanitized['woocommerce_products'] = ! empty( $settings['woocommerce_products'] );
		$sanitized['woocommerce_orders'] = ! empty( $settings['woocommerce_orders'] );
		$sanitized['woocommerce_coupons'] = ! empty( $settings['woocommerce_coupons'] );
		$sanitized['elementor_templates'] = ! empty( $settings['elementor_templates'] );
		$sanitized['elementor_theme_builder'] = ! empty( $settings['elementor_theme_builder'] );

		return $sanitized;
	}

	/**
	 * Get available roles for restriction (exclude administrator).
	 *
	 * @return array
	 */
	public static function get_available_roles() {
		$wp_roles = wp_roles();
		$roles = array();

		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			// Skip administrator and super admin
			if ( 'administrator' === $role_key ) {
				continue;
			}
			$roles[ $role_key ] = translate_user_role( $role_data['name'] );
		}

		return $roles;
	}

	/**
	 * Get available post types for restriction.
	 *
	 * @return array
	 */
	public static function get_available_post_types() {
		$post_types = array();

		// Built-in types
		$post_types['page'] = __( 'Pages', 'secure-freelancer-access' );
		$post_types['post'] = __( 'Posts', 'secure-freelancer-access' );

		// Custom post types (public, not built-in)
		$custom_types = get_post_types(
			array(
				'public'   => true,
				'_builtin' => false,
			),
			'objects'
		);

		// Exclude WooCommerce and Elementor types (handled separately)
		$excluded = array( 'product', 'shop_order', 'shop_coupon', 'elementor_library', 'elementor-hf', 'elementor-thhf' );

		foreach ( $custom_types as $type ) {
			if ( ! in_array( $type->name, $excluded, true ) ) {
				$post_types[ $type->name ] = $type->labels->name;
			}
		}

		return $post_types;
	}

	/**
	 * Get available taxonomies for restriction.
	 *
	 * @return array
	 */
	public static function get_available_taxonomies() {
		$taxonomies = array();

		$tax_objects = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		foreach ( $tax_objects as $tax ) {
			$taxonomies[ $tax->name ] = $tax->labels->name;
		}

		return $taxonomies;
	}

	/**
	 * Check if a role should be restricted.
	 *
	 * @param string $role Role slug.
	 * @return bool
	 */
	public static function is_role_restricted( $role ) {
		$restricted_roles = self::get( 'restricted_roles', array() );
		return in_array( $role, $restricted_roles, true );
	}

	/**
	 * Check if current user should be restricted.
	 *
	 * @return bool
	 */
	public static function is_current_user_restricted() {
		// Admins are never restricted
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return false;
		}

		$restricted_roles = self::get( 'restricted_roles', array() );

		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $restricted_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a post type restriction is enabled.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function is_post_type_enabled( $post_type ) {
		$enabled = self::get( 'enabled_post_types', array() );

		// Check standard post types
		if ( in_array( $post_type, $enabled, true ) ) {
			return true;
		}

		// Check WooCommerce types
		if ( 'product' === $post_type && self::get( 'woocommerce_products' ) ) {
			return true;
		}
		if ( 'shop_order' === $post_type && self::get( 'woocommerce_orders' ) ) {
			return true;
		}
		if ( 'shop_coupon' === $post_type && self::get( 'woocommerce_coupons' ) ) {
			return true;
		}

		// Check Elementor types
		if ( 'elementor_library' === $post_type && self::get( 'elementor_templates' ) ) {
			return true;
		}
		if ( in_array( $post_type, array( 'elementor-hf', 'elementor-thhf' ), true ) && self::get( 'elementor_theme_builder' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get all enabled post types (including integrations).
	 *
	 * @return array
	 */
	public static function get_all_enabled_post_types() {
		$enabled = self::get( 'enabled_post_types', array() );

		// Add WooCommerce types if enabled
		if ( self::get( 'woocommerce_products' ) ) {
			$enabled[] = 'product';
		}
		if ( self::get( 'woocommerce_orders' ) ) {
			$enabled[] = 'shop_order';
		}
		if ( self::get( 'woocommerce_coupons' ) ) {
			$enabled[] = 'shop_coupon';
		}

		// Add Elementor types if enabled
		if ( self::get( 'elementor_templates' ) ) {
			$enabled[] = 'elementor_library';
		}
		if ( self::get( 'elementor_theme_builder' ) ) {
			$enabled[] = 'elementor-hf';
			$enabled[] = 'elementor-thhf';
		}

		return array_unique( $enabled );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check if Elementor is active.
	 *
	 * @return bool
	 */
	public static function is_elementor_active() {
		return defined( 'ELEMENTOR_VERSION' );
	}

	/**
	 * Check if Elementor Pro is active (for Theme Builder).
	 *
	 * @return bool
	 */
	public static function is_elementor_pro_active() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}
}
