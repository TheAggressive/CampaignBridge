<?php
/**
 * Email Template Editor Screen
 *
 * This is a simple screen with no tabs.
 * Controller auto-discovered: Editor_Controller (if exists)
 */

// Get data from controller or set defaults
$cb_templates        = $screen->get( 'templates', [] );
$cb_recent_templates = $screen->get( 'recent_templates', [] );
?>

<div class="editor-screen">
	<h2><?php _e( 'Email Template Editor', 'campaignbridge' ); ?></h2>
	<p class="description">
		<?php _e( 'Create and edit email campaign templates using the block editor.', 'campaignbridge' ); ?>
	</p>

	<!-- Quick Actions -->
	<div class="editor-actions">
		<button type="button" class="button button-primary" id="create-template">
			<?php _e( 'Create New Template', 'campaignbridge' ); ?>
		</button>
		<button type="button" class="button" id="view-templates">
			<?php _e( 'Browse Templates', 'campaignbridge' ); ?>
		</button>
	</div>

	<!-- Recent Templates -->
	<div class="recent-templates">
		<h3><?php _e( 'Recent Templates', 'campaignbridge' ); ?></h3>

		<?php if ( empty( $cb_recent_templates ) ) : ?>
			<p><?php _e( 'No templates created yet.', 'campaignbridge' ); ?></p>
		<?php else : ?>
			<div class="template-grid">
				<?php foreach ( $cb_recent_templates as $template ) : ?>
					<div class="template-card">
						<h4><?php echo esc_html( $template['title'] ); ?></h4>
						<p class="template-meta">
							<?php _e( 'Created:', 'campaignbridge' ); ?>
							<?php echo esc_html( $template['date'] ); ?>
						</p>
						<div class="template-actions">
							<button type="button" class="button button-small" data-template-id="<?php echo esc_attr( $template['id'] ); ?>">
								<?php _e( 'Edit', 'campaignbridge' ); ?>
							</button>
							<button type="button" class="button button-small" data-template-id="<?php echo esc_attr( $template['id'] ); ?>">
								<?php _e( 'Preview', 'campaignbridge' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<!-- Template Editor (placeholder) -->
	<div class="template-editor" style="display: none;">
		<h3><?php _e( 'Template Editor', 'campaignbridge' ); ?></h3>
		<p><?php _e( 'Block editor interface would go here.', 'campaignbridge' ); ?></p>
		<button type="button" class="button" id="close-editor">
			<?php _e( 'Close Editor', 'campaignbridge' ); ?>
		</button>
	</div>
</div>

<style>
	.editor-screen {
		background: white;
		padding: 20px;
		margin-top: 20px;
		border: 1px solid #ddd;
	}

	.editor-actions {
		margin-bottom: 30px;
		padding: 20px;
		background: #f8f9fa;
		border: 1px solid #ddd;
		border-radius: 4px;
	}

	.editor-actions .button {
		margin-right: 10px;
	}

	.recent-templates h3 {
		margin-top: 0;
		padding-bottom: 10px;
		border-bottom: 1px solid #eee;
	}

	.template-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
		gap: 20px;
		margin-top: 20px;
	}

	.template-card {
		background: white;
		border: 1px solid #ddd;
		border-radius: 4px;
		padding: 15px;
	}

	.template-card h4 {
		margin: 0 0 10px 0;
		font-size: 16px;
	}

	.template-meta {
		color: #666;
		font-size: 14px;
		margin: 0 0 15px 0;
	}

	.template-actions {
		display: flex;
		gap: 10px;
	}

	.template-actions .button {
		flex: 1;
	}

	.template-editor {
		margin-top: 30px;
		padding: 20px;
		background: #f8f9fa;
		border: 1px solid #ddd;
		border-radius: 4px;
	}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Create template functionality
	document.getElementById('create-template').addEventListener('click', function() {
		document.querySelector('.template-editor').style.display = 'block';
		document.querySelector('.recent-templates').style.display = 'none';
	});

	// View templates functionality
	document.getElementById('view-templates').addEventListener('click', function() {
		// In a real implementation, this would show a template browser
		alert('<?php esc_js( __( 'Template browser would open here', 'campaignbridge' ) ); ?>');
	});

	// Close editor functionality
	document.getElementById('close-editor').addEventListener('click', function() {
		document.querySelector('.template-editor').style.display = 'none';
		document.querySelector('.recent-templates').style.display = 'block';
	});

	// Edit template buttons
	document.querySelectorAll('.template-actions .button').forEach(button => {
		button.addEventListener('click', function() {
			const templateId = this.getAttribute('data-template-id');
			if (this.textContent.includes('Edit')) {
				document.querySelector('.template-editor').style.display = 'block';
				document.querySelector('.recent-templates').style.display = 'none';
			} else {
				// Preview functionality
				alert('<?php esc_js( __( 'Preview for template ' . $templateId . ' would open here', 'campaignbridge' ) ); ?>');
			}
		});
	});
});
</script>
