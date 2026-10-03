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
