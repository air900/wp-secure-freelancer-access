<?php
/**
 * Plugin Name: Restricted Pages Access
 * Description: Restrict editor access to specific pages and posts in WordPress admin.
 * Version: 1.1.6
 * Author: air900
 * Text Domain: restricted-pages-access
 * Domain Path: /languages
 */

// Защита от прямого доступа
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Константы плагина
define( 'RPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RPA_VERSION', '1.1.6' );

// Подключение классов
require_once RPA_PLUGIN_DIR . 'includes/class-user-meta-handler.php';
require_once RPA_PLUGIN_DIR . 'includes/class-access-filter.php';
require_once RPA_PLUGIN_DIR . 'includes/class-post-access.php';
require_once RPA_PLUGIN_DIR . 'includes/class-admin-page.php';

// Загрузка текстового домена для локализации
add_action( 'plugins_loaded', 'rpa_load_textdomain' );

function rpa_load_textdomain() {
	load_plugin_textdomain( 'restricted-pages-access', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Инициализация при загрузке плагинов
add_action( 'plugins_loaded', function() {
	// Инициализируем основные классы
	// RPA_User_Meta_Handler - статический хелпер, инициализация не требуется
	new RPA_Admin_Page();
	new RPA_Access_Filter();
	new RPA_Post_Access();
} );

// Добавить ссылку Settings в строке плагина
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rpa_add_settings_link' );

function rpa_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=restricted-pages-access' ) . '">' . __( 'Settings', 'restricted-pages-access' ) . '</a>';
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