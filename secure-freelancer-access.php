<?php
/**
 * Plugin Name: Secure Freelancer Access
 * Description: Securely grant freelancers access to specific pages and posts only.
 * Version: 2.0.2
 * Author: air900
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: secure-freelancer-access
 * Domain Path: /languages
 */

// Защита от прямого доступа
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Константы плагина
define( 'RPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RPA_VERSION', '2.0.2' );

// Подключение классов
require_once RPA_PLUGIN_DIR . 'includes/class-user-meta-handler.php';
require_once RPA_PLUGIN_DIR . 'includes/class-access-filter.php';
require_once RPA_PLUGIN_DIR . 'includes/class-post-access.php';
require_once RPA_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once RPA_PLUGIN_DIR . 'includes/class-settings.php';
require_once RPA_PLUGIN_DIR . 'includes/class-rest-api-filter.php';
require_once RPA_PLUGIN_DIR . 'includes/class-media-access.php';
require_once RPA_PLUGIN_DIR . 'includes/class-access-templates.php';
require_once RPA_PLUGIN_DIR . 'includes/class-dashboard-widget.php';
require_once RPA_PLUGIN_DIR . 'includes/class-export-import.php';

// WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once RPA_PLUGIN_DIR . 'includes/class-wpcli.php';
}

// Integrations (loaded conditionally)
if ( file_exists( RPA_PLUGIN_DIR . 'includes/integrations/class-woocommerce.php' ) ) {
	require_once RPA_PLUGIN_DIR . 'includes/integrations/class-woocommerce.php';
}
if ( file_exists( RPA_PLUGIN_DIR . 'includes/integrations/class-elementor.php' ) ) {
	require_once RPA_PLUGIN_DIR . 'includes/integrations/class-elementor.php';
}

// Инициализация при загрузке плагинов
add_action( 'plugins_loaded', function() {
	// Инициализируем основные классы
	// RPA_User_Meta_Handler - статический хелпер, инициализация не требуется
	// RPA_Settings - статический хелпер, инициализация не требуется
	new RPA_Admin_Page();
	new RPA_Access_Filter();
	new RPA_Post_Access();
	new RPA_REST_API_Filter();
	new RPA_Media_Access();
	new RPA_Dashboard_Widget();
	new RPA_Export_Import();

	// Integrations
	if ( class_exists( 'RPA_WooCommerce_Integration' ) ) {
		new RPA_WooCommerce_Integration();
	}
	if ( class_exists( 'RPA_Elementor_Integration' ) ) {
		new RPA_Elementor_Integration();
	}
} );

// Добавить ссылку Settings в строке плагина
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rpa_add_settings_link' );

function rpa_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=secure-freelancer-access' ) . '">' . __( 'Settings', 'secure-freelancer-access' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

// Хуки активации и деактивации
register_activation_hook( __FILE__, 'rpa_activate' );
register_deactivation_hook( __FILE__, 'rpa_deactivate' );

function rpa_activate() {
	// Здесь можно добавить создание ролей или проверку версий, если нужно
}

function rpa_deactivate() {
	// Очистка, если требуется
}