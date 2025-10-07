<?php
/**
 * Post Types Configuration Screen
 *
 * This is a simple screen with no tabs.
 * Controller auto-discovered: Post_Types_Controller (if exists)
 */

// Get data from controller or set defaults
$post_types    = $screen->get( 'post_types', [] );
$enabled_types = $screen->get( 'enabled_types', [] );
?>

<div class="post-types-screen">
	<h2><?php _e( 'Post Types Configuration', 'campaignbridge' ); ?></h2>
	<p class="description">
		<?php _e( 'Configure which WordPress post types are available for email campaigns.', 'campaignbridge' ); ?>
	</p>

	<!-- Available Post Types -->
	<div class="post-types-section">
		<h3><?php _e( 'Available Post Types', 'campaignbridge' ); ?></h3>

		<form method="post" action="">
			<?php $screen->nonce_field( 'save_post_types' ); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e( 'Post Type', 'campaignbridge' ); ?></th>
						<th><?php _e( 'Label', 'campaignbridge' ); ?></th>
						<th><?php _e( 'Enabled', 'campaignbridge' ); ?></th>
						<th><?php _e( 'Description', 'campaignbridge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $post_types as $post_type => $info ) : ?>
						<tr>
							<td><code><?php echo esc_html( $post_type ); ?></code></td>
							<td><?php echo esc_html( $info['label'] ); ?></td>
							<td>
								<label class="switch">
									<input
										type="checkbox"
										name="enabled_types[]"
										value="<?php echo esc_attr( $post_type ); ?>"
										<?php checked( in_array( $post_type, $enabled_types ) ); ?>
									>
									<span class="slider"></span>
								</label>
							</td>
							<td><?php echo esc_html( $info['description'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Post Type Settings', 'campaignbridge' ) ); ?>
		</form>
	</div>

	<!-- Usage Information -->
	<div class="usage-info">
		<h3><?php _e( 'Usage Information', 'campaignbridge' ); ?></h3>
		<p><?php _e( 'Enabled post types will be available when creating email campaigns. Only published posts from enabled post types can be included in campaigns.', 'campaignbridge' ); ?></p>
	</div>
</div>

<style>
	.post-types-screen {
		background: white;
		padding: 20px;
		margin-top: 20px;
		border: 1px solid #ddd;
	}

	.post-types-section {
		margin-bottom: 30px;
	}

	.post-types-section h3 {
		margin-top: 0;
		padding-bottom: 10px;
		border-bottom: 1px solid #eee;
	}

	.usage-info {
		background: #f8f9fa;
		padding: 20px;
		border: 1px solid #ddd;
		border-radius: 4px;
	}

	.usage-info h3 {
		margin-top: 0;
		color: #666;
	}

	/* Toggle Switch Styles */
	.switch {
		position: relative;
		display: inline-block;
		width: 60px;
		height: 34px;
	}

	.switch input {
		opacity: 0;
		width: 0;
		height: 0;
	}

	.slider {
		position: absolute;
		cursor: pointer;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background-color: #ccc;
		transition: .4s;
		border-radius: 34px;
	}

	.slider:before {
		position: absolute;
		content: "";
		height: 26px;
		width: 26px;
		left: 4px;
		bottom: 4px;
		background-color: white;
		transition: .4s;
		border-radius: 50%;
	}

	input:checked + .slider {
		background-color: #0073aa;
	}

	input:checked + .slider:before {
		transform: translateX(26px);
	}
</style>
