<?php
/**
 * General email settings dashboard.
 *
 * @package CampaignBridge
 */

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Core\Storage;
use CampaignBridge\Post_Types\Post_Type_Email_Template;

global $screen;

$site_name           = get_bloginfo( 'name' );
$admin_email         = (string) get_option( 'admin_email' );
$from_name           = (string) Storage::get_option( 'campaignbridge_from_name', $site_name );
$from_email          = (string) Storage::get_option( 'campaignbridge_from_email', $admin_email );
$reply_to            = (string) Storage::get_option( 'campaignbridge_reply_to', $admin_email );
$default_footer      = (string) Storage::get_option( 'campaignbridge_default_footer', sprintf( __( "You're receiving this email because you subscribed to %s.\nNo longer want to receive these emails? Unsubscribe anytime.", 'campaignbridge' ), $site_name ) );
$preview_text        = (bool) Storage::get_option( 'campaignbridge_enable_preview_text', true );
$mailchimp_connected = (bool) ( $screen ? $screen->get( 'mailchimp_connected', false ) : false );
$post_counts         = wp_count_posts( Post_Type_Email_Template::POST_TYPE );
$template_count      = isset( $post_counts->publish ) ? (int) $post_counts->publish : 0;
$brand_configured    = (bool) Storage::get_option( 'campaignbridge_brand_kit', false );
$completed_steps     = (int) $brand_configured + (int) $mailchimp_connected + (int) ( $template_count > 0 );
$editor_url          = admin_url( 'admin.php?page=campaignbridge-editor' );
$new_template_url    = admin_url( 'post-new.php?post_type=' . Post_Type_Email_Template::POST_TYPE );
$providers_url       = add_query_arg( 'tab', 'providers' );
$brand_url           = add_query_arg( 'tab', 'brand' );
$integrations        = $screen ? (array) $screen->get( 'integrations', array() ) : array();
$mailchimp_last_test = isset( $integrations['mailchimp']['last_test'] ) ? (string) $integrations['mailchimp']['last_test'] : __( 'Never tested', 'campaignbridge' );

if ( $screen ) {
	$screen->asset_enqueue_script( 'campaignbridge-general-settings', 'dist/scripts/admin/general.asset.php' );
	$screen->localize_script(
		'campaignbridge-general-settings',
		'campaignbridgeGeneralSettings',
		array(
			'unsaved'      => __( 'Unsaved changes', 'campaignbridge' ),
			'saving'       => __( 'Saving…', 'campaignbridge' ),
			'previewOn'    => __( 'Preview text enabled', 'campaignbridge' ),
			'previewOff'   => __( 'Preview text disabled', 'campaignbridge' ),
			'resetConfirm' => __( 'Reset all CampaignBridge settings? This cannot be undone.', 'campaignbridge' ),
		)
	);
}

$form = Form::make( 'general_settings' )
	->div()
	->success( __( 'General settings saved.', 'campaignbridge' ) )
	->save_to_options( 'campaignbridge_' )
	->text( 'from_name', __( 'From name', 'campaignbridge' ) )->default( $from_name )->required()->description( __( 'This is the name your emails will be from.', 'campaignbridge' ) )->autocomplete( 'organization' )->end()
	->email( 'from_email', __( 'From email', 'campaignbridge' ) )->default( $from_email )->required()->validation( 'email', true )->description( __( 'The email address your emails will be sent from.', 'campaignbridge' ) )->autocomplete( 'email' )->end()
	->email( 'reply_to', __( 'Reply-to email', 'campaignbridge' ) )->default( $reply_to )->validation( 'email', true )->description( __( 'Where replies to your emails will be sent.', 'campaignbridge' ) )->autocomplete( 'email' )->end()
	->switch( 'enable_preview_text', __( 'Enable preview text', 'campaignbridge' ) )->default( $preview_text )->description( __( 'Show a preview text field when creating emails.', 'campaignbridge' ) )->end()
	->textarea( 'default_footer', __( 'Default footer', 'campaignbridge' ) )->default( $default_footer )->rows( 3 )->description( __( 'Included in new templates; it can be customized per template.', 'campaignbridge' ) )->end()
	->select(
		'featured_image_size',
		__( 'Featured image size', 'campaignbridge' ),
		array(
			'large'  => __( 'Large (1024 × 683)', 'campaignbridge' ),
			'medium' => __( 'Medium (300 × 300)', 'campaignbridge' ),
			'full'   => __( 'Full size', 'campaignbridge' ),
		)
	)->default( Storage::get_option( 'campaignbridge_featured_image_size', 'large' ) )->description( __( 'The default featured-image size in emails.', 'campaignbridge' ) )->end()
	->number( 'excerpt_length', __( 'Excerpt length', 'campaignbridge' ) )->default( Storage::get_option( 'campaignbridge_excerpt_length', 120 ) )->min( 20 )->max( 500 )->attributes( array( 'step' => 1 ) )->description( __( 'How many words of your content to include.', 'campaignbridge' ) )->end()
	->text( 'cta_label', __( 'Call-to-action label', 'campaignbridge' ) )->default( Storage::get_option( 'campaignbridge_cta_label', __( 'Read more', 'campaignbridge' ) ) )->max_length( 80 )->description( __( 'Default label for the main call-to-action button.', 'campaignbridge' ) )->end()
	->submit( __( 'Save settings', 'campaignbridge' ) );
?>
<div class="campaignbridge-general">
	<section class="campaignbridge-general__hero" aria-labelledby="campaignbridge-welcome-title">
		<div class="campaignbridge-general__illustration" aria-hidden="true">
			<div class="campaignbridge-general__illustration-item"><strong><?php esc_html_e( 'Your WordPress content', 'campaignbridge' ); ?></strong><div class="campaignbridge-general__mini-card"><span></span><span></span><span></span><div class="campaignbridge-general__mountains"><span class="dashicons dashicons-wordpress"></span></div><b><?php esc_html_e( 'A weekend in the mountains', 'campaignbridge' ); ?></b></div></div>
			<span class="dashicons dashicons-arrow-right-alt"></span>
			<div class="campaignbridge-general__illustration-item"><strong><?php esc_html_e( 'A beautiful email', 'campaignbridge' ); ?></strong><div class="campaignbridge-general__mini-card campaignbridge-general__mini-card--email"><span></span><span></span><span></span><div class="campaignbridge-general__mountains"><span class="dashicons dashicons-email-alt"></span></div><b><?php esc_html_e( 'A weekend in the mountains', 'campaignbridge' ); ?></b></div></div>
		</div>
		<div class="campaignbridge-general__hero-copy">
			<h2 id="campaignbridge-welcome-title"><?php esc_html_e( 'Your content. A bigger audience.', 'campaignbridge' ); ?></h2>
			<p><?php esc_html_e( 'Turn WordPress content into on-brand email templates and deliver them through your favorite providers.', 'campaignbridge' ); ?></p>
			<div class="campaignbridge-general__actions">
				<?php if ( 0 === $template_count ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $new_template_url ); ?>"><?php esc_html_e( 'Create your first template', 'campaignbridge' ); ?></a>
				<?php elseif ( ! $mailchimp_connected ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $providers_url ); ?>"><?php esc_html_e( 'Connect a provider', 'campaignbridge' ); ?></a>
					<a class="button" href="<?php echo esc_url( $editor_url ); ?>"><?php esc_html_e( 'Open your templates', 'campaignbridge' ); ?></a>
				<?php else : ?>
					<a class="button button-primary" href="<?php echo esc_url( $new_template_url ); ?>"><?php esc_html_e( 'Create another template', 'campaignbridge' ); ?></a>
					<a class="button" href="<?php echo esc_url( $editor_url ); ?>"><?php esc_html_e( 'Open your templates', 'campaignbridge' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<aside class="cb-admin-card campaignbridge-card campaignbridge-general__setup" aria-labelledby="campaignbridge-setup-title">
		<h2 id="campaignbridge-setup-title"><span class="dashicons dashicons-clock"></span><?php esc_html_e( 'Setup status', 'campaignbridge' ); ?></h2>
		<div class="campaignbridge-general__setup-content">
			<div class="campaignbridge-general__progress" style="--progress: <?php echo esc_attr( (string) ( $completed_steps * 33.333 ) ); ?>%"><strong><?php echo esc_html( $completed_steps . '/3' ); ?></strong><span><?php esc_html_e( 'complete', 'campaignbridge' ); ?></span></div>
			<ul>
				<li class="<?php echo $brand_configured ? 'is-complete' : ''; ?>"><span class="dashicons <?php echo $brand_configured ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span><a href="<?php echo esc_url( $brand_url ); ?>"><?php esc_html_e( 'Brand configured', 'campaignbridge' ); ?></a><small><?php esc_html_e( 'Your colors and typography are set.', 'campaignbridge' ); ?></small></li>
				<li class="<?php echo $mailchimp_connected ? 'is-complete' : ''; ?>"><span class="dashicons <?php echo $mailchimp_connected ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span><a href="<?php echo esc_url( $providers_url ); ?>"><?php esc_html_e( 'Provider connected', 'campaignbridge' ); ?></a><small><?php echo $mailchimp_connected ? esc_html__( 'Mailchimp is connected.', 'campaignbridge' ) : esc_html__( 'Connect an email provider.', 'campaignbridge' ); ?></small></li>
				<li class="<?php echo $template_count > 0 ? 'is-complete' : ''; ?>"><span class="dashicons <?php echo $template_count > 0 ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span><a href="<?php echo esc_url( $template_count > 0 ? $editor_url : $new_template_url ); ?>"><?php echo $template_count > 0 ? esc_html__( 'First template created', 'campaignbridge' ) : esc_html__( 'Create your first template', 'campaignbridge' ); ?></a><small><?php echo $template_count > 0 ? esc_html( sprintf( _n( '%d template is ready.', '%d templates are ready.', $template_count, 'campaignbridge' ), $template_count ) ) : esc_html__( 'Turn content into an email.', 'campaignbridge' ); ?></small></li>
			</ul>
		</div>
	</aside>

	<div class="campaignbridge-general__main">
		<?php $form->form_start(); ?>
		<section class="cb-admin-card campaignbridge-card campaignbridge-general__defaults" aria-labelledby="campaignbridge-defaults-title">
			<header class="cb-admin-card__header"><span class="dashicons dashicons-email"></span><div><h2 id="campaignbridge-defaults-title"><?php esc_html_e( 'Sender identity', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Set the default sender details used by new email templates.', 'campaignbridge' ); ?></p></div></header>
			<div class="campaignbridge-general__field-grid campaignbridge-general__field-grid--three"><?php $form->render_field( 'from_name' ); ?><?php $form->render_field( 'from_email' ); ?><?php $form->render_field( 'reply_to' ); ?></div>
		</section>
		<section class="cb-admin-card campaignbridge-card campaignbridge-general__email-content" aria-labelledby="campaignbridge-email-content-title">
			<header class="cb-admin-card__header"><span class="dashicons dashicons-text-page"></span><div><h2 id="campaignbridge-email-content-title"><?php esc_html_e( 'Email content', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Choose the defaults included when a new template is created.', 'campaignbridge' ); ?></p></div></header>
			<div class="campaignbridge-general__field-grid campaignbridge-general__field-grid--content"><?php $form->render_field( 'enable_preview_text' ); ?><div><?php $form->render_field( 'default_footer' ); ?></div></div>
		</section>
		<section class="cb-admin-card campaignbridge-card campaignbridge-general__content-defaults" aria-labelledby="campaignbridge-content-title">
			<header class="cb-admin-card__header"><span class="dashicons dashicons-media-document"></span><div><h2 id="campaignbridge-content-title"><?php esc_html_e( 'Content defaults', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Control how your content is pulled into email templates.', 'campaignbridge' ); ?></p></div></header>
			<div class="campaignbridge-general__field-grid campaignbridge-general__field-grid--three"><?php $form->render_field( 'featured_image_size' ); ?><?php $form->render_field( 'excerpt_length' ); ?><?php $form->render_field( 'cta_label' ); ?></div>
		</section>
		<footer class="campaignbridge-general__save-bar"><span class="campaignbridge-general__save-status" role="status" aria-live="polite"><?php esc_html_e( 'All changes save together.', 'campaignbridge' ); ?></span><?php $form->render_submit(); ?></footer>
		<?php $form->form_end(); ?>
		<section class="cb-admin-card campaignbridge-card campaignbridge-general__provider" aria-labelledby="campaignbridge-provider-title">
			<header class="cb-admin-card__header"><span class="dashicons dashicons-admin-links"></span><div><h2 id="campaignbridge-provider-title"><?php esc_html_e( 'Provider status', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Manage your email service provider connections.', 'campaignbridge' ); ?></p></div></header>
			<div class="campaignbridge-general__provider-row"><span class="campaignbridge-general__provider-mark"><span class="dashicons dashicons-email-alt"></span></span><strong>Mailchimp</strong><span class="cb-admin-badge campaignbridge-badge <?php echo $mailchimp_connected ? 'cb-admin-badge--success is-connected' : ''; ?>"><?php echo $mailchimp_connected ? esc_html__( 'Connected', 'campaignbridge' ) : esc_html__( 'Not connected', 'campaignbridge' ); ?></span><span class="campaignbridge-general__provider-detail"><?php echo $mailchimp_connected ? esc_html( sprintf( __( 'Connected · Last checked %s', 'campaignbridge' ), $mailchimp_last_test ) ) : esc_html__( 'Connect Mailchimp to start sending.', 'campaignbridge' ); ?></span><a class="button" href="<?php echo esc_url( $providers_url ); ?>"><?php esc_html_e( 'Manage', 'campaignbridge' ); ?></a></div>
			<a class="cb-admin-action-row campaignbridge-general__row-link" href="<?php echo esc_url( $providers_url ); ?>"><span class="cb-admin-icon-disc dashicons dashicons-plus-alt2"></span><span><strong><?php esc_html_e( 'Connect another provider', 'campaignbridge' ); ?></strong><small><?php esc_html_e( 'Configure Mailchimp and future provider integrations.', 'campaignbridge' ); ?></small></span><span class="dashicons dashicons-arrow-right-alt2"></span></a>
		</section>
	</div>

	<div class="campaignbridge-general__rail">
		<section class="cb-admin-card campaignbridge-card campaignbridge-general__preview" aria-labelledby="campaignbridge-preview-title">
			<header class="cb-admin-card__header"><span class="dashicons dashicons-visibility"></span><div><h2 id="campaignbridge-preview-title"><?php esc_html_e( 'Defaults preview', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Updates as you edit the settings.', 'campaignbridge' ); ?></p></div></header>
			<div class="campaignbridge-general__email-preview">
				<div class="campaignbridge-general__preview-sender"><span><?php esc_html_e( 'From', 'campaignbridge' ); ?></span><strong data-preview="from-name"><?php echo esc_html( $from_name ); ?></strong><small data-preview="from-email"><?php echo esc_html( $from_email ); ?></small></div>
				<span class="campaignbridge-general__preview-text-state" data-preview="preview-text"><?php echo $preview_text ? esc_html__( 'Preview text enabled', 'campaignbridge' ) : esc_html__( 'Preview text disabled', 'campaignbridge' ); ?></span>
				<a class="campaignbridge-general__preview-cta" data-preview="cta-label"><?php echo esc_html( (string) Storage::get_option( 'campaignbridge_cta_label', __( 'Read more', 'campaignbridge' ) ) ); ?></a>
				<hr>
				<p data-preview="footer"><?php echo nl2br( esc_html( $default_footer ) ); ?></p>
			</div>
		</section>
	</div>

	<details class="campaignbridge-general__advanced">
		<summary><?php esc_html_e( 'Advanced settings', 'campaignbridge' ); ?></summary>
		<section class="cb-admin-notice cb-admin-notice--error campaignbridge-general__danger" aria-labelledby="campaignbridge-danger-title"><div><span class="dashicons dashicons-warning"></span><span><strong id="campaignbridge-danger-title"><?php esc_html_e( 'Reset CampaignBridge', 'campaignbridge' ); ?></strong><small><?php esc_html_e( 'Restore every CampaignBridge setting to its default value.', 'campaignbridge' ); ?></small></span></div><form method="post" data-reset-settings><?php wp_nonce_field( 'campaignbridge_reset_all' ); ?><button class="button button-destructive" type="submit" name="reset_all_settings" value="1"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Reset all settings', 'campaignbridge' ); ?></button></form></section>
	</details>
</div>
