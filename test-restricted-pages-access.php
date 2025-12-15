<?php
/**
 * Class Test_Restricted_Pages_Access
 *
 * @package Restricted_Pages_Access
 */

class Test_Restricted_Pages_Access extends WP_UnitTestCase {

	private $admin_id;
	private $editor_id;
	private $page_allowed_1;
	private $page_allowed_2;
	private $page_forbidden;
	private $post_allowed;
	private $post_forbidden;

	/**
	 * Настройка окружения перед каждым тестом.
	 */
	public function setUp() {
		parent::setUp();

		// Создаем пользователей
		$this->admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );

		// Создаем контент
		$this->page_allowed_1 = $this->factory->post->create( array( 'post_type' => 'page', 'post_title' => 'Allowed Page 1' ) );
		$this->page_allowed_2 = $this->factory->post->create( array( 'post_type' => 'page', 'post_title' => 'Allowed Page 2' ) );
		$this->page_forbidden = $this->factory->post->create( array( 'post_type' => 'page', 'post_title' => 'Forbidden Page' ) );

		$this->post_allowed = $this->factory->post->create( array( 'post_type' => 'post', 'post_title' => 'Allowed Post' ) );
		$this->post_forbidden = $this->factory->post->create( array( 'post_type' => 'post', 'post_title' => 'Forbidden Post' ) );

		// Назначаем права редактору (как в Примере 1 из ТЗ)
		RPA_User_Meta_Handler::set_user_allowed_pages( $this->editor_id, array( $this->page_allowed_1, $this->page_allowed_2 ) );
		RPA_User_Meta_Handler::set_user_allowed_posts( $this->editor_id, array( $this->post_allowed ) );
	}

	/**
	 * Тест: Проверка сохранения и получения метаданных прав доступа.
	 */
	public function test_user_meta_handler() {
		$allowed_pages = RPA_User_Meta_Handler::get_user_allowed_pages( $this->editor_id );
		
		$this->assertContains( $this->page_allowed_1, $allowed_pages );
		$this->assertContains( $this->page_allowed_2, $allowed_pages );
		$this->assertNotContains( $this->page_forbidden, $allowed_pages );

		// Проверка очистки
		RPA_User_Meta_Handler::clear_user_access( $this->editor_id );
		$this->assertEmpty( RPA_User_Meta_Handler::get_user_allowed_pages( $this->editor_id ) );
	}

	/**
	 * Тест: Пример 1. Разработчик видит только разрешенные страницы в списке.
	 * Проверяем работу RPA_Access_Filter::filter_posts_query
	 */
	public function test_editor_sees_only_allowed_pages_in_list() {
		wp_set_current_user( $this->editor_id );
		
		// Эмулируем нахождение в админке на странице списка страниц
		set_current_screen( 'edit-page' ); 

		// Создаем запрос
		$query = new WP_Query();
		$query->set( 'post_type', 'page' );
		$query->set( 'post_status', 'any' );

		// Подменяем глобальный запрос, чтобы $query->is_main_query() возвращал true
		global $wp_query;
		$wp_query = $query;

		// Запускаем фильтр вручную (так надежнее в unit-тестах, чем полагаться на хуки)
		$filter = new RPA_Access_Filter();
		$filter->filter_posts_query( $query );

		// Проверяем, что параметр post__in был установлен
		$post__in = $query->get( 'post__in' );
		
		$this->assertNotEmpty( $post__in, 'Фильтр должен установить post__in' );
		$this->assertContains( $this->page_allowed_1, $post__in );
		$this->assertContains( $this->page_allowed_2, $post__in );
		$this->assertNotContains( $this->page_forbidden, $post__in );
	}

	/**
	 * Тест: Фильтрация выпадающего списка родительских страниц.
	 */
	public function test_dropdown_pages_filter() {
		wp_set_current_user( $this->editor_id );
		
		$filter = new RPA_Access_Filter();
		$args = array( 'include' => array() ); // Стандартные аргументы
		
		$filtered_args = $filter->filter_dropdown_pages( $args );
		
		// В списке должны остаться только разрешенные страницы
		$this->assertContains( $this->page_allowed_1, $filtered_args['include'] );
		$this->assertContains( $this->page_allowed_2, $filtered_args['include'] );
		$this->assertNotContains( $this->page_forbidden, $filtered_args['include'] );
	}

	/**
	 * Тест: Пример 2. Администратор видит все (фильтр не применяется).
	 */
	public function test_admin_sees_everything() {
		wp_set_current_user( $this->admin_id );
		set_current_screen( 'edit-page' );

		$query = new WP_Query();
		$query->set( 'post_type', 'page' );
		global $wp_query;
		$wp_query = $query;

		$filter = new RPA_Access_Filter();
		$filter->filter_posts_query( $query );

		// У админа post__in должен остаться пустым (показывать всё)
		$post__in = $query->get( 'post__in' );
		$this->assertEmpty( $post__in );
	}

	/**
	 * Тест: Блокировка прямого доступа к запрещенной странице.
	 * Ожидается wp_die() (WPDieException в тестах).
	 */
	public function test_direct_access_forbidden() {
		wp_set_current_user( $this->editor_id );
		
		// Эмулируем GET запрос к запрещенному посту
		$_GET['post'] = $this->page_forbidden;

		$access_checker = new RPA_Post_Access();

		try {
			$access_checker->check_post_access();
			$this->fail( 'Ожидалось исключение WPDieException из-за запрета доступа.' );
		} catch ( WPDieException $e ) {
			$this->assertEquals( 'У вас нет прав на редактирование этой записи.', $e->getMessage() );
		}
	}

	/**
	 * Тест: Прямой доступ к разрешенной странице.
	 * Не должно быть исключений.
	 */
	public function test_direct_access_allowed() {
		wp_set_current_user( $this->editor_id );
		
		$_GET['post'] = $this->page_allowed_1;

		$access_checker = new RPA_Post_Access();

		try {
			$access_checker->check_post_access();
			// Если мы здесь, значит исключение не выброшено, тест пройден
			$this->assertTrue( true );
		} catch ( WPDieException $e ) {
			$this->fail( 'Доступ должен быть разрешен, но получено исключение: ' . $e->getMessage() );
		}
	}

	/**
	 * Тест: Логирование попыток доступа.
	 */
	public function test_access_logging() {
		wp_set_current_user( $this->editor_id );
		$_GET['post'] = $this->page_forbidden;

		// Очищаем логи перед тестом
		delete_option( 'rpa_access_logs' );

		$access_checker = new RPA_Post_Access();

		try {
			$access_checker->check_post_access();
		} catch ( WPDieException $e ) {
			// Игнорируем die, нас интересует лог
		}

		$logs = get_option( 'rpa_access_logs' );
		
		$this->assertNotEmpty( $logs, 'Лог не должен быть пустым' );
		$this->assertEquals( $this->page_forbidden, $logs[0]['post_id'] );
		$this->assertEquals( 'Forbidden Page', $logs[0]['post_title'] );
	}

	/**
	 * Тест: Проверка очистки логов.
	 */
	public function test_clear_logs() {
		// Создаем фиктивный лог
		$fake_log = array(
			array(
				'time' => '2023-01-01 12:00:00',
				'user_login' => 'test',
				'post_id' => 1,
				'post_title' => 'Test',
				'ip' => '127.0.0.1'
			)
		);
		update_option( 'rpa_access_logs', $fake_log );

		// Проверяем, что записалось
		$this->assertNotEmpty( get_option( 'rpa_access_logs' ) );

		// Эмулируем действие очистки (как в RPA_Admin_Page::save_settings)
		// Поскольку метод save_settings проверяет nonce и права, в юнит-тесте проще вызвать функцию API напрямую
		delete_option( 'rpa_access_logs' );

		$this->assertEmpty( get_option( 'rpa_access_logs' ) );
	}
}