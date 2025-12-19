# Secure Freelancer Access - Plugin Development Notes

## Чек-лист для публикации плагина на WordPress.org

### 1. Структура и именование

- [ ] **Имя папки = Text Domain**: Папка плагина должна называться так же, как text domain
  - Пример: папка `secure-freelancer-access/`, text domain `secure-freelancer-access`
- [ ] **Главный файл**: Название главного файла совпадает с папкой (`secure-freelancer-access.php`)
- [ ] **Нет лишних файлов**: Удалить `.git/`, `node_modules/`, тестовые файлы, `.DS_Store`

### 2. Plugin Header (главный PHP файл)

```php
/**
 * Plugin Name: Secure Freelancer Access
 * Description: Short description (max 150 chars)
 * Version: X.X.X
 * Author: Your Name
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: secure-freelancer-access
 * Domain Path: /languages
 */
```

- [ ] **License**: Обязательно указать `GPLv2 or later`
- [ ] **License URI**: Обязательно указать URL лицензии
- [ ] **Text Domain**: Должен совпадать с именем папки
- [ ] **Version**: Формат X.X.X

### 3. readme.txt (обязательный файл)

- [ ] **Язык**: Только английский
- [ ] **Short description**: До 150 символов
- [ ] **Stable tag**: Совпадает с версией в PHP файле
- [ ] **Tested up to**: Актуальная версия WordPress
- [ ] **Requires PHP**: Минимальная версия PHP
- [ ] **License**: GPLv2 or later
- [ ] **Changelog**: История всех версий
- [ ] **Screenshots**: Описание скриншотов (опционально, но рекомендуется)

### 4. Безопасность кода

#### Редиректы
- [ ] Использовать `wp_safe_redirect()` вместо `wp_redirect()`
- [ ] После редиректа всегда `exit;`

#### Санитизация входных данных
- [ ] `$_GET` → `sanitize_text_field( wp_unslash( $_GET['key'] ) )`
- [ ] `$_POST` → `sanitize_text_field( wp_unslash( $_POST['key'] ) )`
- [ ] Массивы ID → `array_map( 'intval', $array )`
- [ ] `$_FILES['tmp_name']` → проверить `isset()` для error и tmp_name

#### Nonce валидация
- [ ] Формы: `wp_nonce_field()` + `check_admin_referer()`
- [ ] AJAX: `wp_create_nonce()` + `check_ajax_referer()`
- [ ] GET запросы: `wp_nonce_url()` + `check_admin_referer()`

#### Экранирование вывода
- [ ] HTML: `esc_html()`, `esc_html_e()`, `esc_html__()`
- [ ] Атрибуты: `esc_attr()`
- [ ] URL: `esc_url()`
- [ ] Textarea: `esc_textarea()`

### 5. Интернационализация (i18n)

- [ ] Все строки обёрнуты в `__()` или `_e()`
- [ ] Text domain везде одинаковый
- [ ] **Translators comments** для sprintf:
  ```php
  /* translators: %s: user name */
  sprintf( __( 'Hello %s', 'text-domain' ), $name )
  ```

### 6. Функции PHP

- [ ] `date()` → `gmdate()` (для timezone-независимого времени)
- [ ] `file_get_contents()` для URL → `wp_remote_get()`
- [ ] `curl_*` → WordPress HTTP API
- [ ] `json_encode()` → `wp_json_encode()`

### 7. База данных

- [ ] Использовать `$wpdb->prepare()` для SQL запросов
- [ ] Не использовать прямые SQL запросы без подготовки
- [ ] Префиксы таблиц: `$wpdb->prefix`

### 8. PHPCS игнорирование (когда необходимо)

```php
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging
```

### 9. Финальная проверка

1. [ ] Установить Plugin Check плагин
2. [ ] Запустить проверку: `Tools → Plugin Check`
3. [ ] Исправить все ERROR
4. [ ] WARNING можно оставить если есть обоснование
5. [ ] Создать ZIP с правильной структурой папок

### 10. Создание релизного архива

```bash
# Структура архива для WordPress.org
secure-freelancer-access/
├── secure-freelancer-access.php
├── readme.txt
├── includes/
├── assets/
└── languages/
```

Команда для создания:
```bash
mkdir -p temp/secure-freelancer-access
cp -r secure-freelancer-access.php readme.txt includes assets languages temp/secure-freelancer-access/
cd temp && zip -r ../secure-freelancer-access-X.X.X.zip secure-freelancer-access
rm -rf temp
```

---

## Текущая версия: 2.0.4

### Функциональность v2.0

- Ограничение доступа к Pages, Posts, CPT
- WooCommerce интеграция (Products, Orders, Coupons)
- Elementor интеграция (Templates, Theme Builder)
- Медиатека с фильтрацией
- Временный доступ по расписанию
- Шаблоны доступа
- Копирование прав между пользователями
- Dashboard виджеты
- Export/Import в JSON
- WP-CLI команды
- Лог попыток доступа


