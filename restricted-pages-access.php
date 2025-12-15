<?php
/**
 * Plugin Name: Restricted Pages Access
 * Description: Ограничение доступа редакторов к конкретным страницам и записям.
 * Version: 1.0.0
 * Author: Gemini Code Assist
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
define( 'RPA_VERSION', '1.0.0' );

// Подключение классов
require_once RPA_PLUGIN_DIR . 'includes/class-user-meta-handler.php';
require_once RPA_PLUGIN_DIR . 'includes/class-access-filter.php';
require_once RPA_PLUGIN_DIR . 'includes/class-post-access.php';
require_once RPA_PLUGIN_DIR . 'includes/class-admin-page.php';

// Инициализация при загрузке плагинов
add_action( 'plugins_loaded', function() {
	// Инициализируем основные классы
	// RPA_User_Meta_Handler - статический хелпер, инициализация не требуется
	new RPA_Admin_Page();
	new RPA_Access_Filter();
	new RPA_Post_Access();
} );

// Хуки активации и деактивации
register_activation_hook( __FILE__, 'rpa_activate' );
register_deactivation_hook( __FILE__, 'rpa_deactivate' );

function rpa_activate() {
	// Здесь можно добавить создание ролей или проверку версий, если нужно
}

function rpa_deactivate() {
	// Очистка, если требуется
}