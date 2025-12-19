=== Secure Freelancer Access ===
Contributors: air900
Tags: permissions, roles, access control, editor, restrict
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 2.0.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restrict editor access to specific pages and posts in WordPress admin.

== Description ==

**Secure Freelancer Access** is a plugin for granular content access control in WordPress admin panel. Perfect for agencies working with freelancers who need access only to specific pages.

= The Problem =

By default, users with Editor role can see ALL pages and posts on the site. This creates issues when you need to give a freelancer or external specialist access only to specific pages.

= The Solution =

This plugin allows administrators to configure exactly which pages and posts each editor can see:

* Only allowed items appear in admin lists
* Direct URL access to forbidden pages is blocked (403 Forbidden)
* Access attempt logging for security audit

= Key Features =

* Restrict access to specific Pages
* Restrict access to specific Posts
* Custom Post Types support
* WooCommerce integration (Products, Orders, Coupons)
* Elementor integration (Templates, Theme Builder)
* Media Library filtering
* Category and taxonomy-based access
* Temporary access with date scheduling
* Access templates for quick permission assignment
* Copy permissions between users
* Export/Import settings to JSON
* WP-CLI commands
* Dashboard widgets
* Access attempt logging
* Direct URL blocking (403 Forbidden)
* Secure (nonce validation, input sanitization)

= How It Works =

1. Go to `Settings → Content Access Restriction`
2. Select a user from the list
3. Click "Edit Access"
4. Check the pages and posts to allow
5. Save changes

The editor will now only see the allowed items in admin!

= Who Is This For =

* Agencies working with external developers
* Site owners with multiple editors
* Projects requiring granular access control
* Anyone who needs to limit content visibility for specific users

== Installation ==

= Automatic Installation =

1. Go to `Plugins → Add New`
2. Search for "Secure Freelancer Access"
3. Click "Install" then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Extract to `/wp-content/plugins/secure-freelancer-access/`
3. Activate the plugin in WordPress admin

= Configuration =

1. After activation, go to `Settings → Content Access Restriction`
2. Configure which roles to restrict in Settings tab
3. Select a user and edit their access permissions
4. Save changes

== Frequently Asked Questions ==

= Does this affect administrators? =

No. Administrators with `manage_options` capability see all content without restrictions. The plugin only applies to configured restricted roles.

= Can I restrict access to Custom Post Types? =

Yes! Version 2.0+ supports Custom Post Types. Enable them in Settings tab.

= What happens if an editor tries to access a forbidden page directly? =

The plugin blocks access and shows "Access Denied" (HTTP 403). The attempt is logged.

= Does this work with WooCommerce? =

Yes! Version 2.0+ includes WooCommerce integration for Products, Orders, and Coupons. Enable in Settings.

= Does this work with Elementor? =

Yes! Version 2.0+ includes Elementor integration for Templates and Theme Builder elements. Enable in Settings.

= Can I set temporary access? =

Yes! You can set start and end dates for user access. Access automatically expires after the end date.

= Where is access data stored? =

Data is stored in `wp_usermeta` for each user:
* `rpa_allowed_pages` - array of allowed page IDs
* `rpa_allowed_posts` - array of allowed post IDs

Access logs are stored in `wp_options` as `rpa_access_logs`.

= How do I remove all plugin data? =

Data is NOT deleted on deactivation. For complete cleanup, run:

`
delete_metadata('user', 0, 'rpa_allowed_pages', '', true);
delete_metadata('user', 0, 'rpa_allowed_posts', '', true);
delete_option('rpa_access_logs');
delete_option('rpa_settings');
delete_option('rpa_templates');
`

= Does the plugin slow down the site? =

No. The plugin only works in the admin panel and doesn't affect the frontend. All queries are optimized.

== Screenshots ==

1. Main page - list of all restricted users with access info
2. User access editing form with page/post selection
3. Access attempt log
4. Settings page with role and content type configuration
5. Access templates management

== Changelog ==

= 2.0.3 (2025-12-19) =
* Fixed Plugin Check errors
* Added translators comments
* Fixed date() to gmdate()
* Improved input sanitization
* Updated Tested up to: 6.9

= 2.0.2 (2025-12-19) =
* Complete v2.0 feature set
* Media Library filtering
* WooCommerce integration (Products, Orders, Coupons)
* Elementor integration (Templates, Theme Builder)
* Access Templates system
* Copy permissions between users
* Dashboard widgets
* Export/Import to JSON
* WP-CLI commands
* Temporary access scheduling
* Security improvements

= 2.0.1 (2025-12-18) =
* Settings page with role selection
* REST API protection
* Custom Post Types support
* Taxonomy-based access
* Multiple role support

= 1.0.0 (2025-12-15) =
* Initial release
* Basic functionality (MVP)
* Page and post access restriction
* Admin interface with checkboxes
* Access logging
* Security (nonce, validation, sanitization)

== Upgrade Notice ==

= 2.0.3 =
Plugin Check fixes and improved compatibility with WordPress.org standards.

= 2.0.2 =
Major update with WooCommerce, Elementor, templates, export/import, and WP-CLI.

= 2.0.1 =
Added settings page, REST API protection, and Custom Post Types support.

= 1.0.0 =
Initial release. Install and configure access for your editors!
