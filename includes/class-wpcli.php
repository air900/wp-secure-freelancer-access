<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load if WP-CLI is available
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Class SFAccess_WPCLI
 * Provides WP-CLI commands for managing content access restrictions.
 */
class SFAccess_WPCLI {

	/**
	 * List all restricted users with their access summary.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess list-users
	 *     wp sfaccess list-users --format=json
	 *
	 * @subcommand list-users
	 */
	public function list_users( $args, $assoc_args ) {
		$restricted_roles = SFAccess_Settings::get( 'restricted_roles', array( 'editor' ) );
		$users = get_users( array( 'role__in' => $restricted_roles ) );

		if ( empty( $users ) ) {
			WP_CLI::success( 'No restricted users found.' );
			return;
		}

		$data = array();
		foreach ( $users as $user ) {
			$pages = SFAccess_User_Meta_Handler::get_user_allowed_pages( $user->ID );
			$posts = SFAccess_User_Meta_Handler::get_user_allowed_posts( $user->ID );
			$media = SFAccess_User_Meta_Handler::get_user_allowed_media( $user->ID );
			$schedule = SFAccess_User_Meta_Handler::get_user_access_schedule( $user->ID );

			$status = 'Active';
			if ( ! empty( $schedule['start_date'] ) || ! empty( $schedule['end_date'] ) ) {
				if ( ! SFAccess_User_Meta_Handler::is_user_access_active( $user->ID ) ) {
					$status = 'Expired/Inactive';
				} else {
					$status = 'Scheduled';
				}
			}

			$data[] = array(
				'ID'     => $user->ID,
				'Login'  => $user->user_login,
				'Email'  => $user->user_email,
				'Role'   => implode( ', ', $user->roles ),
				'Pages'  => count( $pages ),
				'Posts'  => count( $posts ),
				'Media'  => count( $media ),
				'Status' => $status,
			);
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		WP_CLI\Utils\format_items( $format, $data, array( 'ID', 'Login', 'Email', 'Role', 'Pages', 'Posts', 'Media', 'Status' ) );
	}

	/**
	 * Show access details for a specific user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess user-access 5
	 *     wp sfaccess user-access editor_user --format=json
	 *
	 * @subcommand user-access
	 */
	public function user_access( $args, $assoc_args ) {
		$user = $this->get_user( $args[0] );
		if ( ! $user ) {
			WP_CLI::error( 'User not found.' );
		}

		$pages = SFAccess_User_Meta_Handler::get_user_allowed_pages( $user->ID );
		$posts = SFAccess_User_Meta_Handler::get_user_allowed_posts( $user->ID );
		$media = SFAccess_User_Meta_Handler::get_user_allowed_media( $user->ID );
		$schedule = SFAccess_User_Meta_Handler::get_user_access_schedule( $user->ID );

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			$data = array(
				'user_id'    => $user->ID,
				'user_login' => $user->user_login,
				'pages'      => $pages,
				'posts'      => $posts,
				'media'      => $media,
				'schedule'   => $schedule,
			);

			// Add CPT access
			$enabled_types = SFAccess_Settings::get( 'enabled_post_types', array( 'page', 'post' ) );
			foreach ( $enabled_types as $post_type ) {
				if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
					continue;
				}
				$data[ $post_type ] = SFAccess_User_Meta_Handler::get_user_allowed_content( $user->ID, $post_type );
			}

			WP_CLI::line( json_encode( $data, JSON_PRETTY_PRINT ) );
			return;
		}

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%BUser: ' . $user->user_login . ' (ID: ' . $user->ID . ')%n' ) );
		WP_CLI::line( '' );

		// Schedule
		if ( ! empty( $schedule['start_date'] ) || ! empty( $schedule['end_date'] ) ) {
			$is_active = SFAccess_User_Meta_Handler::is_user_access_active( $user->ID );
			$status_color = $is_active ? '%G' : '%R';
			$status_text = $is_active ? 'ACTIVE' : 'INACTIVE';
			WP_CLI::line( WP_CLI::colorize( "Schedule: {$status_color}{$status_text}%n" ) );
			if ( ! empty( $schedule['start_date'] ) ) {
				WP_CLI::line( "  Start: {$schedule['start_date']}" );
			}
			if ( ! empty( $schedule['end_date'] ) ) {
				WP_CLI::line( "  End: {$schedule['end_date']}" );
			}
			WP_CLI::line( '' );
		}

		// Pages
		WP_CLI::line( WP_CLI::colorize( '%YPages (' . count( $pages ) . '):%n' ) );
		if ( empty( $pages ) ) {
			WP_CLI::line( '  (none)' );
		} else {
			foreach ( $pages as $page_id ) {
				$title = get_the_title( $page_id );
				WP_CLI::line( "  - [{$page_id}] {$title}" );
			}
		}
		WP_CLI::line( '' );

		// Posts
		WP_CLI::line( WP_CLI::colorize( '%YPosts (' . count( $posts ) . '):%n' ) );
		if ( empty( $posts ) ) {
			WP_CLI::line( '  (none)' );
		} else {
			foreach ( $posts as $post_id ) {
				$title = get_the_title( $post_id );
				WP_CLI::line( "  - [{$post_id}] {$title}" );
			}
		}
		WP_CLI::line( '' );

		// Media
		WP_CLI::line( WP_CLI::colorize( '%YMedia (' . count( $media ) . '):%n' ) );
		if ( empty( $media ) ) {
			WP_CLI::line( '  (none)' );
		} else {
			foreach ( $media as $media_id ) {
				$title = get_the_title( $media_id );
				WP_CLI::line( "  - [{$media_id}] {$title}" );
			}
		}
	}

	/**
	 * Grant access to content for a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * <type>
	 * : Content type (page, post, media, or custom post type).
	 *
	 * <ids>
	 * : Comma-separated list of content IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess grant editor_user page 10,20,30
	 *     wp sfaccess grant 5 post 100
	 *     wp sfaccess grant editor_user media 50,51,52
	 *
	 */
	public function grant( $args, $assoc_args ) {
		$user = $this->get_user( $args[0] );
		if ( ! $user ) {
			WP_CLI::error( 'User not found.' );
		}

		$type = $args[1];
		$ids = array_map( 'intval', explode( ',', $args[2] ) );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			WP_CLI::error( 'No valid IDs provided.' );
		}

		switch ( $type ) {
			case 'page':
				$existing = SFAccess_User_Meta_Handler::get_user_allowed_pages( $user->ID );
				$new = array_unique( array_merge( $existing, $ids ) );
				SFAccess_User_Meta_Handler::set_user_allowed_pages( $user->ID, $new );
				break;

			case 'post':
				$existing = SFAccess_User_Meta_Handler::get_user_allowed_posts( $user->ID );
				$new = array_unique( array_merge( $existing, $ids ) );
				SFAccess_User_Meta_Handler::set_user_allowed_posts( $user->ID, $new );
				break;

			case 'media':
				$existing = SFAccess_User_Meta_Handler::get_user_allowed_media( $user->ID );
				$new = array_unique( array_merge( $existing, $ids ) );
				SFAccess_User_Meta_Handler::set_user_allowed_media( $user->ID, $new );
				break;

			default:
				// Check if it's a custom post type
				$enabled_types = SFAccess_Settings::get( 'enabled_post_types', array() );
				if ( in_array( $type, $enabled_types, true ) ) {
					$existing = SFAccess_User_Meta_Handler::get_user_allowed_content( $user->ID, $type );
					$new = array_unique( array_merge( $existing, $ids ) );
					SFAccess_User_Meta_Handler::set_user_allowed_content( $user->ID, $type, $new );
				} else {
					WP_CLI::error( "Unknown content type: {$type}" );
				}
				break;
		}

		$count = count( $ids );
		WP_CLI::success( "Granted access to {$count} {$type}(s) for user {$user->user_login}." );
	}

	/**
	 * Revoke access to content for a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * <type>
	 * : Content type (page, post, media, or custom post type).
	 *
	 * <ids>
	 * : Comma-separated list of content IDs, or "all" to revoke all.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess revoke editor_user page 10,20
	 *     wp sfaccess revoke 5 post all
	 *
	 */
	public function revoke( $args, $assoc_args ) {
		$user = $this->get_user( $args[0] );
		if ( ! $user ) {
			WP_CLI::error( 'User not found.' );
		}

		$type = $args[1];
		$ids_input = $args[2];

		if ( 'all' === strtolower( $ids_input ) ) {
			$ids = 'all';
		} else {
			$ids = array_map( 'intval', explode( ',', $ids_input ) );
			$ids = array_filter( $ids );
		}

		switch ( $type ) {
			case 'page':
				if ( 'all' === $ids ) {
					SFAccess_User_Meta_Handler::set_user_allowed_pages( $user->ID, array() );
				} else {
					$existing = SFAccess_User_Meta_Handler::get_user_allowed_pages( $user->ID );
					$new = array_diff( $existing, $ids );
					SFAccess_User_Meta_Handler::set_user_allowed_pages( $user->ID, $new );
				}
				break;

			case 'post':
				if ( 'all' === $ids ) {
					SFAccess_User_Meta_Handler::set_user_allowed_posts( $user->ID, array() );
				} else {
					$existing = SFAccess_User_Meta_Handler::get_user_allowed_posts( $user->ID );
					$new = array_diff( $existing, $ids );
					SFAccess_User_Meta_Handler::set_user_allowed_posts( $user->ID, $new );
				}
				break;

			case 'media':
				if ( 'all' === $ids ) {
					SFAccess_User_Meta_Handler::set_user_allowed_media( $user->ID, array() );
				} else {
					$existing = SFAccess_User_Meta_Handler::get_user_allowed_media( $user->ID );
					$new = array_diff( $existing, $ids );
					SFAccess_User_Meta_Handler::set_user_allowed_media( $user->ID, $new );
				}
				break;

			default:
				$enabled_types = SFAccess_Settings::get( 'enabled_post_types', array() );
				if ( in_array( $type, $enabled_types, true ) ) {
					if ( 'all' === $ids ) {
						SFAccess_User_Meta_Handler::set_user_allowed_content( $user->ID, $type, array() );
					} else {
						$existing = SFAccess_User_Meta_Handler::get_user_allowed_content( $user->ID, $type );
						$new = array_diff( $existing, $ids );
						SFAccess_User_Meta_Handler::set_user_allowed_content( $user->ID, $type, $new );
					}
				} else {
					WP_CLI::error( "Unknown content type: {$type}" );
				}
				break;
		}

		if ( 'all' === $ids ) {
			WP_CLI::success( "Revoked all {$type} access for user {$user->user_login}." );
		} else {
			$count = count( $ids );
			WP_CLI::success( "Revoked access to {$count} {$type}(s) for user {$user->user_login}." );
		}
	}

	/**
	 * Apply an access template to a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * <template>
	 * : Template ID.
	 *
	 * [--merge]
	 * : Merge with existing access instead of replacing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess apply-template editor_user tpl_abc123
	 *     wp sfaccess apply-template 5 tpl_abc123 --merge
	 *
	 * @subcommand apply-template
	 */
	public function apply_template( $args, $assoc_args ) {
		$user = $this->get_user( $args[0] );
		if ( ! $user ) {
			WP_CLI::error( 'User not found.' );
		}

		$template_id = $args[1];
		$template = SFAccess_Access_Templates::get_template( $template_id );

		if ( ! $template ) {
			WP_CLI::error( "Template not found: {$template_id}" );
		}

		$merge = isset( $assoc_args['merge'] );
		$result = SFAccess_Access_Templates::apply_to_user( $template_id, $user->ID, $merge );

		if ( $result ) {
			$mode = $merge ? 'merged' : 'applied';
			WP_CLI::success( "Template '{$template['name']}' {$mode} to user {$user->user_login}." );
		} else {
			WP_CLI::error( 'Failed to apply template.' );
		}
	}

	/**
	 * List available access templates.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess list-templates
	 *     wp sfaccess list-templates --format=json
	 *
	 * @subcommand list-templates
	 */
	public function list_templates( $args, $assoc_args ) {
		$templates = SFAccess_Access_Templates::get_templates();

		if ( empty( $templates ) ) {
			WP_CLI::success( 'No templates found.' );
			return;
		}

		$data = array();
		foreach ( $templates as $template_id => $template ) {
			$summary = SFAccess_Access_Templates::get_template_summary( $template_id );
			$summary_text = array();
			foreach ( $summary as $label => $count ) {
				$summary_text[] = "{$label}: {$count}";
			}

			$data[] = array(
				'ID'          => $template_id,
				'Name'        => $template['name'],
				'Description' => substr( $template['description'] ?? '', 0, 40 ),
				'Content'     => implode( ', ', $summary_text ) ?: '-',
				'Created'     => $template['created'] ?? '-',
			);
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		WP_CLI\Utils\format_items( $format, $data, array( 'ID', 'Name', 'Description', 'Content', 'Created' ) );
	}

	/**
	 * Export plugin data to JSON.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Output file path. If not specified, outputs to stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess export > backup.json
	 *     wp sfaccess export /path/to/backup.json
	 *
	 */
	public function export( $args, $assoc_args ) {
		$export_import = new SFAccess_Export_Import();
		$data = $export_import->generate_export_data();
		$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( ! empty( $args[0] ) ) {
			$file = $args[0];
			if ( file_put_contents( $file, $json ) !== false ) {
				WP_CLI::success( "Data exported to {$file}" );
			} else {
				WP_CLI::error( "Failed to write to {$file}" );
			}
		} else {
			WP_CLI::line( $json );
		}
	}

	/**
	 * Import plugin data from JSON.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Input file path.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess import backup.json
	 *     wp sfaccess import backup.json --yes
	 *
	 */
	public function import( $args, $assoc_args ) {
		$file = $args[0];

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "File not found: {$file}" );
		}

		$content = file_get_contents( $file );
		$data = json_decode( $content, true );

		if ( ! $data || ! isset( $data['version'] ) ) {
			WP_CLI::error( 'Invalid import file format.' );
		}

		WP_CLI::line( "Import file info:" );
		WP_CLI::line( "  Version: {$data['version']}" );
		WP_CLI::line( "  Exported at: {$data['exported_at']}" );
		WP_CLI::line( "  Source site: {$data['site_url']}" );

		if ( isset( $data['templates'] ) ) {
			WP_CLI::line( "  Templates: " . count( $data['templates'] ) );
		}
		if ( isset( $data['user_access'] ) ) {
			WP_CLI::line( "  Users: " . count( $data['user_access'] ) );
		}

		WP_CLI::confirm( 'Do you want to import this data?', $assoc_args );

		$export_import = new SFAccess_Export_Import();
		$result = $export_import->import_data( $data );

		if ( $result ) {
			WP_CLI::success( 'Data imported successfully.' );
		} else {
			WP_CLI::error( 'Import failed.' );
		}
	}

	/**
	 * Set access schedule for a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * [--start=<date>]
	 * : Start date (YYYY-MM-DD).
	 *
	 * [--end=<date>]
	 * : End date (YYYY-MM-DD).
	 *
	 * [--clear]
	 * : Clear the schedule.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess schedule editor_user --start=2025-01-01 --end=2025-12-31
	 *     wp sfaccess schedule 5 --end=2025-06-30
	 *     wp sfaccess schedule editor_user --clear
	 *
	 */
	public function schedule( $args, $assoc_args ) {
		$user = $this->get_user( $args[0] );
		if ( ! $user ) {
			WP_CLI::error( 'User not found.' );
		}

		if ( isset( $assoc_args['clear'] ) ) {
			SFAccess_User_Meta_Handler::clear_user_access_schedule( $user->ID );
			WP_CLI::success( "Schedule cleared for user {$user->user_login}." );
			return;
		}

		$start = isset( $assoc_args['start'] ) ? $assoc_args['start'] : '';
		$end = isset( $assoc_args['end'] ) ? $assoc_args['end'] : '';

		if ( empty( $start ) && empty( $end ) ) {
			// Show current schedule
			$schedule = SFAccess_User_Meta_Handler::get_user_access_schedule( $user->ID );
			if ( empty( $schedule['start_date'] ) && empty( $schedule['end_date'] ) ) {
				WP_CLI::line( "No schedule set for user {$user->user_login}." );
			} else {
				WP_CLI::line( "Schedule for user {$user->user_login}:" );
				WP_CLI::line( "  Start: " . ( $schedule['start_date'] ?: '(not set)' ) );
				WP_CLI::line( "  End: " . ( $schedule['end_date'] ?: '(not set)' ) );

				$is_active = SFAccess_User_Meta_Handler::is_user_access_active( $user->ID );
				$status = $is_active ? 'ACTIVE' : 'INACTIVE';
				WP_CLI::line( "  Status: {$status}" );
			}
			return;
		}

		SFAccess_User_Meta_Handler::set_user_access_schedule( $user->ID, $start, $end );
		WP_CLI::success( "Schedule set for user {$user->user_login}." );

		if ( ! empty( $start ) ) {
			WP_CLI::line( "  Start: {$start}" );
		}
		if ( ! empty( $end ) ) {
			WP_CLI::line( "  End: {$end}" );
		}
	}

	/**
	 * Copy access permissions from one user to another.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Source user ID or login.
	 *
	 * <target>
	 * : Target user ID or login.
	 *
	 * [--include-schedule]
	 * : Also copy the access schedule.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sfaccess copy-access editor1 editor2
	 *     wp sfaccess copy-access 5 10 --include-schedule
	 *
	 * @subcommand copy-access
	 */
	public function copy_access( $args, $assoc_args ) {
		$source = $this->get_user( $args[0] );
		if ( ! $source ) {
			WP_CLI::error( 'Source user not found.' );
		}

		$target = $this->get_user( $args[1] );
		if ( ! $target ) {
			WP_CLI::error( 'Target user not found.' );
		}

		if ( $source->ID === $target->ID ) {
			WP_CLI::error( 'Source and target users are the same.' );
		}

		$include_schedule = isset( $assoc_args['include-schedule'] );
		SFAccess_User_Meta_Handler::copy_user_access( $source->ID, $target->ID, $include_schedule );

		WP_CLI::success( "Access copied from {$source->user_login} to {$target->user_login}." );
	}

	/**
	 * Get user by ID or login.
	 *
	 * @param mixed $identifier User ID or login.
	 * @return WP_User|false User object or false.
	 */
	private function get_user( $identifier ) {
		if ( is_numeric( $identifier ) ) {
			return get_user_by( 'id', $identifier );
		}
		return get_user_by( 'login', $identifier );
	}
}

// Register WP-CLI commands
WP_CLI::add_command( 'sfaccess', 'SFAccess_WPCLI' );
