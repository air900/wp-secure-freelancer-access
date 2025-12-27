<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFAccess_Dashboard_Widget
 * Adds dashboard widgets for admins and restricted users.
 */
class SFAccess_Dashboard_Widget {

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register dashboard widgets.
	 */
	public function register_widgets() {
		// Admin widget - overview
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget(
				'sfaccess_admin_widget',
				__( 'Secure Freelancer Access', 'secure-freelancer-access' ),
				array( $this, 'render_admin_widget' )
			);
		}

		// Restricted user widget - their access info
		if ( SFAccess_Settings::is_current_user_restricted() ) {
			wp_add_dashboard_widget(
				'sfaccess_user_widget',
				__( 'My Content Access', 'secure-freelancer-access' ),
				array( $this, 'render_user_widget' )
			);
		}
	}

	/**
	 * Render admin dashboard widget.
	 */
	public function render_admin_widget() {
		$restricted_roles = SFAccess_Settings::get( 'restricted_roles', array( 'editor' ) );
		$users = get_users( array( 'role__in' => $restricted_roles ) );
		$logs = get_option( 'sfaccess_access_logs', array() );

		// Count users with active access
		$active_users = 0;
		$expired_users = 0;
		foreach ( $users as $user ) {
			if ( SFAccess_User_Meta_Handler::is_user_access_active( $user->ID ) ) {
				$active_users++;
			} else {
				$expired_users++;
			}
		}

		$settings_url = admin_url( 'options-general.php?page=secure-freelancer-access' );
		$logs_url = admin_url( 'options-general.php?page=secure-freelancer-access&view=logs' );

		?>
		<div class="sfaccess-dashboard-widget">
			<ul>
				<li>
					<strong><?php esc_html_e( 'Restricted Users:', 'secure-freelancer-access' ); ?></strong>
					<?php echo esc_html( count( $users ) ); ?>
					<?php if ( $expired_users > 0 ) : ?>
						<span style="color: #d63638;">(<?php echo esc_html( $expired_users ); ?> <?php esc_html_e( 'expired', 'secure-freelancer-access' ); ?>)</span>
					<?php endif; ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Recent Access Denials:', 'secure-freelancer-access' ); ?></strong>
					<?php echo esc_html( count( $logs ) ); ?>
				</li>
			</ul>

			<?php if ( ! empty( $logs ) ) : ?>
				<h4><?php esc_html_e( 'Latest Access Attempts', 'secure-freelancer-access' ); ?></h4>
				<table class="widefat" style="font-size: 12px;">
					<tbody>
						<?php
						$recent_logs = array_slice( $logs, 0, 5 );
						foreach ( $recent_logs as $log ) :
						?>
							<tr>
								<td><?php echo esc_html( $log['time'] ); ?></td>
								<td><?php echo esc_html( $log['user_login'] ); ?></td>
								<td><?php echo esc_html( wp_trim_words( $log['post_title'], 5 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p class="sfaccess-widget-links" style="margin-top: 10px;">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-small"><?php esc_html_e( 'Manage Access', 'secure-freelancer-access' ); ?></a>
				<?php if ( ! empty( $logs ) ) : ?>
					<a href="<?php echo esc_url( $logs_url ); ?>" class="button button-small"><?php esc_html_e( 'View All Logs', 'secure-freelancer-access' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render user dashboard widget.
	 */
	public function render_user_widget() {
		$user_id = get_current_user_id();
		$schedule = SFAccess_User_Meta_Handler::get_user_access_schedule( $user_id );
		$is_active = SFAccess_User_Meta_Handler::is_user_access_active( $user_id );

		$allowed_pages = SFAccess_User_Meta_Handler::get_user_allowed_pages( $user_id );
		$allowed_posts = SFAccess_User_Meta_Handler::get_user_allowed_posts( $user_id );

		?>
		<div class="sfaccess-dashboard-widget">
			<?php if ( ! $is_active ) : ?>
				<div class="notice notice-error inline" style="margin: 0 0 10px;">
					<p><strong><?php esc_html_e( 'Your access has expired.', 'secure-freelancer-access' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<ul>
				<li>
					<strong><?php esc_html_e( 'Pages:', 'secure-freelancer-access' ); ?></strong>
					<?php echo esc_html( count( $allowed_pages ) ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Posts:', 'secure-freelancer-access' ); ?></strong>
					<?php echo esc_html( count( $allowed_posts ) ); ?>
				</li>
				<?php if ( $schedule ) : ?>
					<li>
						<strong><?php esc_html_e( 'Access Period:', 'secure-freelancer-access' ); ?></strong>
						<?php
						if ( ! empty( $schedule['start_date'] ) && ! empty( $schedule['end_date'] ) ) {
							echo esc_html( sprintf(
								/* translators: %1$s: start date, %2$s: end date */
								__( '%1$s to %2$s', 'secure-freelancer-access' ),
								date_i18n( get_option( 'date_format' ), strtotime( $schedule['start_date'] ) ),
								date_i18n( get_option( 'date_format' ), strtotime( $schedule['end_date'] ) )
							) );
						} elseif ( ! empty( $schedule['end_date'] ) ) {
							echo esc_html( sprintf(
								/* translators: %s: end date */
								__( 'Until %s', 'secure-freelancer-access' ),
								date_i18n( get_option( 'date_format' ), strtotime( $schedule['end_date'] ) )
							) );
						} elseif ( ! empty( $schedule['start_date'] ) ) {
							echo esc_html( sprintf(
								/* translators: %s: start date */
								__( 'From %s', 'secure-freelancer-access' ),
								date_i18n( get_option( 'date_format' ), strtotime( $schedule['start_date'] ) )
							) );
						}
						?>
					</li>
				<?php endif; ?>
			</ul>

			<p class="description" style="margin-top: 10px;">
				<?php esc_html_e( 'Contact an administrator if you need access to additional content.', 'secure-freelancer-access' ); ?>
			</p>
		</div>
		<?php
	}
}
