# WordPress.org Release Checklist

This checklist ensures the plugin meets all WordPress.org requirements before submission.

## Pre-Release Preparation

### 1. Version Sync
- [ ] Update version in `secure-freelancer-access.php` header
- [ ] Update `Stable tag` in `readme.txt` to match
- [ ] Update changelog in `readme.txt`
- [ ] Update `Tested up to` with current WordPress version

### 2. File Structure Verification
```
secure-freelancer-access/
├── secure-freelancer-access.php   (main plugin file)
├── readme.txt                      (WordPress.org readme)
├── includes/                       (PHP classes)
├── assets/                         (CSS/JS/images)
└── languages/                      (translations)
```

### 3. Required Files Check
- [ ] `secure-freelancer-access.php` exists with valid header
- [ ] `readme.txt` exists with all required sections
- [ ] `LICENSE` or license in header (GPLv2 or later)

---

## Code Quality Checks

### 4. Security Audit
Run through all PHP files and verify:

#### Input Sanitization
- [ ] All `$_GET` sanitized: `sanitize_text_field( wp_unslash( $_GET['key'] ) )`
- [ ] All `$_POST` sanitized: `sanitize_text_field( wp_unslash( $_POST['key'] ) )`
- [ ] Array IDs use: `array_map( 'intval', $array )`

#### Output Escaping
- [ ] HTML output: `esc_html()`, `esc_html_e()`, `esc_html__()`
- [ ] Attributes: `esc_attr()`
- [ ] URLs: `esc_url()`
- [ ] Textareas: `esc_textarea()`

#### Nonce Validation
- [ ] Forms have `wp_nonce_field()` + `check_admin_referer()`
- [ ] AJAX uses `wp_create_nonce()` + `check_ajax_referer()`
- [ ] GET actions use `wp_nonce_url()` + `check_admin_referer()`

#### Redirects
- [ ] Use `wp_safe_redirect()` (not `wp_redirect()`)
- [ ] Always `exit;` after redirect

### 5. WordPress Functions
- [ ] `date()` replaced with `gmdate()` or `wp_date()`
- [ ] `json_encode()` replaced with `wp_json_encode()`
- [ ] No `file_get_contents()` for URLs (use `wp_remote_get()`)
- [ ] No direct `curl_*` functions (use HTTP API)

### 6. Database
- [ ] All SQL queries use `$wpdb->prepare()`
- [ ] Table names use `$wpdb->prefix`

### 7. Internationalization (i18n)
- [ ] All user-facing strings wrapped in `__()` or `_e()`
- [ ] Text domain is `secure-freelancer-access` everywhere
- [ ] Translator comments for sprintf placeholders:
  ```php
  /* translators: %s: user name */
  sprintf( __( 'Hello %s', 'secure-freelancer-access' ), $name )
  ```

---

## Automated Testing

### 8. Plugin Check (WordPress.org Official)
1. Install "Plugin Check" plugin from WordPress.org
2. Go to `Tools → Plugin Check`
3. Select "Secure Freelancer Access"
4. Run all checks
5. Fix all **ERROR** items (mandatory)
6. Review **WARNING** items (fix or document reason to ignore)

### 9. PHP Compatibility
```bash
# If PHPCS is installed
phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- secure-freelancer-access.php includes/
```

---

## readme.txt Validation

### 10. Required Sections
- [ ] `=== Plugin Name ===` header
- [ ] `Contributors:` (wordpress.org username)
- [ ] `Tags:` (max 5 relevant tags)
- [ ] `Requires at least:` WordPress version
- [ ] `Tested up to:` latest WordPress version
- [ ] `Requires PHP:` minimum PHP version
- [ ] `Stable tag:` current version
- [ ] `License:` GPLv2 or later
- [ ] `License URI:` https://www.gnu.org/licenses/gpl-2.0.html
- [ ] `== Description ==` section
- [ ] `== Installation ==` section
- [ ] `== Changelog ==` section

### 11. readme.txt Validator
Visit: https://wordpress.org/plugins/developers/readme-validator/
- [ ] No errors
- [ ] No critical warnings

---

## Build Process

### 12. Create Release Archive
```bash
# Set version
VERSION="2.0.5"

# Create temp directory
mkdir -p release/secure-freelancer-access

# Copy plugin files (exclude dev files)
cp secure-freelancer-access.php release/secure-freelancer-access/
cp readme.txt release/secure-freelancer-access/
cp -r includes release/secure-freelancer-access/
cp -r assets release/secure-freelancer-access/
cp -r languages release/secure-freelancer-access/

# Create ZIP
cd release
zip -r "../secure-freelancer-access-${VERSION}.zip" secure-freelancer-access

# Cleanup
cd ..
rm -rf release

echo "Created: secure-freelancer-access-${VERSION}.zip"
```

### 13. Archive Contents Verification
```bash
# List archive contents
unzip -l secure-freelancer-access-*.zip
```

Verify NO unwanted files:
- [ ] No `.git/` directory
- [ ] No `node_modules/`
- [ ] No `.DS_Store`
- [ ] No `test-*.php` or `test-*.js`
- [ ] No `*.zip` files
- [ ] No `debug/` directory
- [ ] No `idea.md` or dev notes
- [ ] No `claude.md` or `CLAUDE.md`
- [ ] No `package.json` / `package-lock.json`
- [ ] No `screenshots/` (these go to WordPress.org SVN assets)

---

## Screenshots (Optional but Recommended)

### 14. Screenshot Preparation
- [ ] Screenshots named: `screenshot-1.png`, `screenshot-2.png`, etc.
- [ ] Maximum width: 1200px (772px recommended)
- [ ] PNG or JPEG format
- [ ] Descriptions in `readme.txt`:
  ```
  == Screenshots ==
  1. Admin settings page
  2. User permissions interface
  ```

Note: Screenshots go to `/assets/` in SVN, not in the plugin ZIP.

---

## Submission

### 15. First-Time Submission
1. Go to: https://wordpress.org/plugins/developers/add/
2. Upload ZIP file
3. Wait for review (usually 1-5 business days)
4. Respond to any reviewer feedback

### 16. Update Existing Plugin
1. Use SVN to commit changes to `trunk/`
2. Create tag: `svn cp trunk tags/X.X.X`
3. Commit with message describing changes

---

## Post-Release

### 17. Verification
- [ ] Plugin page displays correctly on WordPress.org
- [ ] Version number is correct
- [ ] Screenshots display (if provided)
- [ ] Install from WordPress admin works
- [ ] Activate without errors

### 18. Git Tag
```bash
git tag -a vX.X.X -m "Release X.X.X"
git push origin vX.X.X
```

---

## Quick Reference: Common PHPCS Ignores

When certain warnings are intentional, use these comments:

```php
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Already sanitized above
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for debugging
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is already escaped
// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- No placeholders
```

---

## Useful Links

- [Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
- [Plugin Security](https://developer.wordpress.org/plugins/security/)
- [readme.txt Validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Plugin Check Plugin](https://wordpress.org/plugins/plugin-check/)
- [SVN Guide](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
