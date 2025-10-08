<?php
/**
 * Status Screen - System status and debugging information
 *
 * This is a simple screen with no tabs.
 * Controller auto-discovered: Status_Controller (if exists)
 */

// Get data from controller or set defaults
$cb_system_info  = $screen->get( 'system_info', [] );
$cb_integrations = $screen->get( 'integrations', [] );
$cb_stats        = $screen->get( 'stats', [] );
?>

<div class="status-screen">
	<h2><?php _e( 'System Status', 'campaignbridge' ); ?></h2>
	<p class="description">
		<?php _e( 'Comprehensive system information and debugging data for CampaignBridge.', 'campaignbridge' ); ?>
	</p>

	<!-- System Information -->
	<div class="status-section">
		<h3><?php _e( 'System Information', 'campaignbridge' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<tbody>
				<tr>
					<td><strong><?php _e( 'Plugin Version', 'campaignbridge' ); ?></strong></td>
					<td><?php echo esc_html( $cb_system_info['plugin_version'] ?? 'Unknown' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php _e( 'WordPress Version', 'campaignbridge' ); ?></strong></td>
					<td><?php echo esc_html( $cb_system_info['wordpress_version'] ?? get_bloginfo( 'version' ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php _e( 'PHP Version', 'campaignbridge' ); ?></strong></td>
					<td><?php echo esc_html( $cb_system_info['php_version'] ?? PHP_VERSION ); ?></td>
				</tr>
				<tr>
					<td><strong><?php _e( 'Debug Mode', 'campaignbridge' ); ?></strong></td>
					<td>
						<?php if ( $cb_system_info['debug_mode'] ?? false ) : ?>
							<span class="status-enabled"><?php _e( 'Enabled', 'campaignbridge' ); ?></span>
						<?php else : ?>
							<span class="status-disabled"><?php _e( 'Disabled', 'campaignbridge' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Integration Status -->
	<div class="status-section">
		<h3><?php _e( 'Integration Status', 'campaignbridge' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php _e( 'Provider', 'campaignbridge' ); ?></th>
					<th><?php _e( 'Status', 'campaignbridge' ); ?></th>
					<th><?php _e( 'Last Test', 'campaignbridge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cb_integrations as $provider => $info ) : ?>
					<tr>
						<td><strong><?php echo esc_html( ucfirst( $provider ) ); ?></strong></td>
						<td>
							<?php if ( $info['connected'] ) : ?>
								<span class="status-connected"><?php _e( 'Connected', 'campaignbridge' ); ?></span>
							<?php else : ?>
								<span class="status-disconnected"><?php _e( 'Not Connected', 'campaignbridge' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $info['last_test'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Statistics -->
	<div class="status-section">
		<h3><?php _e( 'Campaign Statistics', 'campaignbridge' ); ?></h3>
		<div class="stats-grid">
			<div class="stat-card">
				<h4><?php _e( 'Total Campaigns', 'campaignbridge' ); ?></h4>
				<p class="stat-number"><?php echo number_format( $cb_stats['total_campaigns'] ?? 0 ); ?></p>
			</div>
			<div class="stat-card">
				<h4><?php _e( 'Total Sent', 'campaignbridge' ); ?></h4>
				<p class="stat-number"><?php echo number_format( $cb_stats['total_sent'] ?? 0 ); ?></p>
			</div>
			<div class="stat-card">
				<h4><?php _e( 'Subscribers', 'campaignbridge' ); ?></h4>
				<p class="stat-number"><?php echo number_format( $cb_stats['subscribers'] ?? 0 ); ?></p>
			</div>
		</div>
	</div>

	<!-- Actions -->
	<div class="status-section">
		<h3><?php _e( 'Actions', 'campaignbridge' ); ?></h3>
		<div class="action-buttons">
			<form method="post" action="" style="display: inline;">
				<?php $screen->nonce_field( 'refresh_stats' ); ?>
				<button type="submit" name="refresh_stats" value="1" class="button">
					<?php _e( 'Refresh Status', 'campaignbridge' ); ?>
				</button>
			</form>
			<form method="post" action="" style="display: inline; margin-left: 10px;">
				<?php $screen->nonce_field( 'clear_cache' ); ?>
				<button type="submit" name="clear_cache" value="1" class="button">
					<?php _e( 'Clear Cache', 'campaignbridge' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>

<style>
	.status-screen {
		background: white;
		padding: 20px;
		margin-top: 20px;
		border: 1px solid #ddd;
	}

	.status-section {
		margin-bottom: 30px;
	}

	.status-section h3 {
		margin-top: 0;
		padding-bottom: 10px;
		border-bottom: 1px solid #eee;
	}

	.stats-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 20px;
		margin-top: 20px;
	}

	.stat-card {
		background: #f8f9fa;
		padding: 20px;
		border: 1px solid #ddd;
		border-radius: 4px;
		text-align: center;
	}

	.stat-card h4 {
		margin: 0 0 10px 0;
		font-size: 14px;
		color: #666;
	}

	.stat-number {
		font-size: 24px;
		font-weight: bold;
		color: #0073aa;
		margin: 0;
	}

	.status-enabled {
		color: #28a745;
		font-weight: bold;
	}

	.status-disabled {
		color: #dc3545;
		font-weight: bold;
	}

	.status-connected {
		color: #28a745;
		font-weight: bold;
	}

	.status-disconnected {
		color: #dc3545;
		font-weight: bold;
	}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Add confirmation for destructive actions
	document.querySelectorAll('button[name="clear_cache"]').forEach(button => {
		button.addEventListener('click', function(e) {
			if (!confirm('<?php esc_js( __( 'Are you sure you want to clear the cache?', 'campaignbridge' ) ); ?>')) {
				e.preventDefault();
				return false;
			}
		});
	});
});
</script>
