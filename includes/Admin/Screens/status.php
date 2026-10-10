<?php
/**
 * Status Screen.
 *
 * Displays system status and information.
 * Controller auto-discovered: Status_Controller (if exists).
 *
 * @package CampaignBridge\Admin\Screens
 */

// Get data from controller.
global $screen;
if ( ! isset( $screen ) ) {
	$screen = null; // Fallback for PHPStan.
}
$system_info = $screen ? $screen->get( 'system_info', array() ) : array();
$plugin_info = $screen ? $screen->get( 'plugin_info', array() ) : array();
$encryption  = $screen ? $screen->get( 'encryption', array() ) : array();
$health      = $screen ? $screen->get( 'health', array() ) : array();

$campaignbridge_yes_no      = static fn ( $value ): string => true === $value ? __( 'Yes', 'campaignbridge' ) : __( 'No', 'campaignbridge' );
$campaignbridge_state_names = array(
	'draft'            => __( 'Draft', 'campaignbridge' ),
	'ready_for_review' => __( 'Ready for review', 'campaignbridge' ),
	'approved'         => __( 'Approved', 'campaignbridge' ),
	'provider_draft'   => __( 'In provider', 'campaignbridge' ),
	'scheduled'        => __( 'Scheduled', 'campaignbridge' ),
	'sending'          => __( 'Sending', 'campaignbridge' ),
	'sent'             => __( 'Sent', 'campaignbridge' ),
	'failed'           => __( 'Failed', 'campaignbridge' ),
	'cancelled'        => __( 'Cancelled', 'campaignbridge' ),
	'unknown'          => __( 'Needs reconciliation', 'campaignbridge' ),
	'archived'         => __( 'Archived', 'campaignbridge' ),
);
$campaignbridge_jobs        = is_array( $health['jobs'] ?? null ) ? $health['jobs'] : array();
$campaignbridge_job_counts  = is_array( $campaignbridge_jobs['counts'] ?? null ) ? $campaignbridge_jobs['counts'] : array();
$campaignbridge_verified_at = is_string( $health['last_verified_at'] ?? null ) ? strtotime( $health['last_verified_at'] ) : false;

$key_sources       = array(
	'external' => __( 'Defined outside the database (CAMPAIGNBRIDGE_ENCRYPTION_KEY)', 'campaignbridge' ),
	'database' => __( 'Stored in the database (fallback)', 'campaignbridge' ),
	'invalid'  => __( 'Invalid: CAMPAIGNBRIDGE_ENCRYPTION_KEY must be the base64 encoding of 32 bytes. Credentials cannot be used.', 'campaignbridge' ),
);
$credential_states = array(
	'none'        => __( 'No stored credential', 'campaignbridge' ),
	'current'     => __( 'Protected by the current key', 'campaignbridge' ),
	'previous'    => __( 'Protected by a previous key. Save Settings → Providers to re-encrypt it.', 'campaignbridge' ),
	'unavailable' => __( 'Cannot be decrypted with the configured keys', 'campaignbridge' ),
);

if ( $screen ) {
	$screen->asset_enqueue_style( 'campaignbridge-status', 'dist/styles/admin/screens/status.asset.php' );
}
?>

<div class="campaignbridge-status">
	<div class="campaignbridge-status__content">
		<!-- CampaignBridge Health Section -->
		<div class="cb-admin-card campaignbridge-status__section campaignbridge-status__health">
			<div class="cb-admin-card__header campaignbridge-status__section-header">
				<h2><?php esc_html_e( 'CampaignBridge Health', 'campaignbridge' ); ?></h2>
			</div>

			<?php if ( 0 < (int) ( $health['unknown_campaigns'] ?? 0 ) ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of campaigns whose provider outcome is unconfirmed. */
								_n( '%d campaign needs reconciliation: the provider did not confirm a request. Open it and reconcile before doing anything else with it.', '%d campaigns need reconciliation: the provider did not confirm a request. Open each one and reconcile before doing anything else with it.', (int) $health['unknown_campaigns'], 'campaignbridge' ),
								(int) $health['unknown_campaigns']
							)
						);
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=campaignbridge-campaigns' ) ); ?>"><?php esc_html_e( 'Go to Campaigns', 'campaignbridge' ); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( 0 < (int) ( $campaignbridge_jobs['overdue'] ?? 0 ) || 0 < (int) ( $campaignbridge_jobs['expired_leases'] ?? 0 ) || false === ( $campaignbridge_jobs['scheduled'] ?? true ) ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Background jobs are not running on time. WP-Cron runs only when the site receives visits or a system cron calls wp-cron.php; if WP-Cron is disabled, set up a system cron that calls it every minute.', 'campaignbridge' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="campaignbridge-status__info-grid">
				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Database tables:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( true === ( $health['schema_current'] ?? null ) ? __( 'Up to date', 'campaignbridge' ) : __( 'Not installed or out of date. Deactivate and reactivate CampaignBridge.', 'campaignbridge' ) ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Mailchimp connected:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $campaignbridge_yes_no( $health['provider_connected'] ?? null ) ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Mailchimp connection verified:', 'campaignbridge' ); ?></strong>
					<span>
						<?php
						if ( true !== ( $health['provider_connected'] ?? null ) ) {
							esc_html_e( 'No connection', 'campaignbridge' );
						} elseif ( false === $campaignbridge_verified_at ) {
							esc_html_e( 'Not checked yet. Open Settings → Providers to check the connection.', 'campaignbridge' );
						} else {
							echo esc_html(
								sprintf(
									/* translators: %s: how long ago the connection was last checked, such as "3 hours". */
									true === ( $health['provider_verified'] ?? null ) ? __( 'Yes, checked %s ago', 'campaignbridge' ) : __( 'No: Mailchimp refused the stored API key %s ago', 'campaignbridge' ),
									human_time_diff( $campaignbridge_verified_at )
								)
							);
						}
						?>
					</span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Default audience chosen:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $campaignbridge_yes_no( $health['default_audience'] ?? null ) ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Published templates:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( number_format_i18n( (int) ( $health['published_templates'] ?? 0 ) ) ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Campaigns:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( number_format_i18n( (int) ( $health['campaign_total'] ?? 0 ) ) ); ?></span>
				</div>

				<?php foreach ( $campaignbridge_state_names as $campaignbridge_state => $campaignbridge_state_name ) : ?>
					<?php if ( isset( $health['campaign_states'][ $campaignbridge_state ] ) ) : ?>
						<div class="campaignbridge-status__info-item">
							<strong><?php echo esc_html( $campaignbridge_state_name ); ?>:</strong>
							<span><?php echo esc_html( number_format_i18n( (int) $health['campaign_states'][ $campaignbridge_state ] ) ); ?></span>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Background jobs waiting:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( number_format_i18n( (int) ( $campaignbridge_job_counts['queued'] ?? 0 ) + (int) ( $campaignbridge_job_counts['claimed'] ?? 0 ) ) ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Background jobs that stopped:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( number_format_i18n( (int) ( $campaignbridge_job_counts['failed'] ?? 0 ) + (int) ( $campaignbridge_job_counts['dead'] ?? 0 ) ) ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Overdue background jobs:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( number_format_i18n( (int) ( $campaignbridge_jobs['overdue'] ?? 0 ) + (int) ( $campaignbridge_jobs['expired_leases'] ?? 0 ) ) ); ?></span>
				</div>
			</div>
		</div>

		<!-- System Information Section -->
		<div class="cb-admin-card campaignbridge-status__section">
			<div class="cb-admin-card__header campaignbridge-status__section-header">
				<h2><?php esc_html_e( 'System Information', 'campaignbridge' ); ?></h2>
			</div>

			<div class="campaignbridge-status__info-grid">
				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'WordPress Version:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $system_info['wordpress_version'] ?? 'Unknown' ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'PHP Version:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $system_info['php_version'] ?? 'Unknown' ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Server Software:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $system_info['server_software'] ?? 'Unknown' ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Memory Limit:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $system_info['memory_limit'] ?? 'Unknown' ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Max Execution Time:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $system_info['max_execution_time'] ?? 'Unknown' ); ?>s</span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Upload Max Size:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $system_info['upload_max_size'] ?? 'Unknown' ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Post Max Size:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $system_info['post_max_size'] ?? 'Unknown' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Plugin Information Section -->
		<div class="cb-admin-card campaignbridge-status__section">
			<div class="cb-admin-card__header campaignbridge-status__section-header">
				<h2><?php esc_html_e( 'Plugin Information', 'campaignbridge' ); ?></h2>
			</div>

			<div class="campaignbridge-status__info-grid">
				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Plugin Name:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $plugin_info['name'] ?? 'Unknown' ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Version:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $plugin_info['version'] ?? 'Unknown' ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Author:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $plugin_info['author'] ?? 'Unknown' ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Text Domain:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $plugin_info['text_domain'] ?? 'Unknown' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Credential Encryption Section -->
		<div class="cb-admin-card campaignbridge-status__section">
			<div class="cb-admin-card__header campaignbridge-status__section-header">
				<h2><?php esc_html_e( 'Credential Encryption', 'campaignbridge' ); ?></h2>
			</div>

			<div class="campaignbridge-status__info-grid">
				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Encryption key:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $key_sources[ $encryption['source'] ?? '' ] ?? __( 'Unknown', 'campaignbridge' ) ); ?></span>
				</div>

				<div class="campaignbridge-status__info-item">
					<strong><?php esc_html_e( 'Mailchimp credential:', 'campaignbridge' ); ?></strong>
					<span><?php echo esc_html( $credential_states[ $encryption['credential'] ?? '' ] ?? __( 'Unknown', 'campaignbridge' ) ); ?></span>
				</div>
			</div>
		</div>

	</div>
</div>
