<?php
/**
 * Email Providers Settings Tab.
 *
 * Auto-discovered as part of Settings screen
 * Controller: Settings_Controller (auto-discovered)
 *
 * @package CampaignBridge\Admin\Screens\settings
 */

// Include the Form API.
use CampaignBridge\Admin\Core\Form;

// Get current values.
global $screen;
if ( ! isset( $screen ) ) {
	$screen = null; // Fallback for PHPStan.
}
$campaignbridge_provider           = $screen ? $screen->get( 'provider', \CampaignBridge\Core\Storage::get_option( 'campaignbridge_provider', 'html' ) ) : \CampaignBridge\Core\Storage::get_option( 'campaignbridge_provider', 'html' );
$campaignbridge_mailchimp_api_key  = $screen ? $screen->get( 'mailchimp_api_key', \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_api_key', '' ) ) : \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_api_key', '' );
$campaignbridge_mailchimp_audience = $screen ? $screen->get( 'mailchimp_audience', \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_audience', '' ) ) : \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_audience', '' );
$campaignbridge_is_connected       = $screen ? $screen->get( 'mailchimp_connected', false ) : false;
$campaignbridge_mailchimp_status   = $screen ? $screen->get( 'mailchimp_status', __( 'Not configured', 'campaignbridge' ) ) : __( 'Not configured', 'campaignbridge' );
$campaignbridge_is_mailchimp       = 'mailchimp' === $campaignbridge_provider;
$campaignbridge_editor_url         = admin_url( 'admin.php?page=campaignbridge-editor' );

if ( $screen ) {
	$screen->asset_enqueue_script( 'campaignbridge-providers', 'dist/scripts/admin/providers.asset.php' );
}

// Create the form using the Form API.
$form = Form::make( 'providers' )
	->div()
	->select( 'provider', __( 'Delivery method', 'campaignbridge' ) )
		->options(
			array(
				'html'      => __( 'HTML Email (built in)', 'campaignbridge' ),
				'mailchimp' => __( 'Mailchimp', 'campaignbridge' ),
			)
		)
		->default( $campaignbridge_provider )
		->description( __( 'Choose how CampaignBridge prepares your email templates for delivery.', 'campaignbridge' ) )
		->required()
	->encrypted( 'mailchimp_api_key', __( 'Mailchimp API key', 'campaignbridge' ) )
		->context( 'api_key' )
		->validation( 'min_length', 10 )
		->description( __( 'Create or copy a key from your Mailchimp account settings. Existing saved keys remain encrypted.', 'campaignbridge' ) )
	->text( 'mailchimp_audience', __( 'Default audience', 'campaignbridge' ) )
		->description( __( 'Optional audience identifier to use for new campaigns.', 'campaignbridge' ) )
		->end()
	->before_save(
		function ( $data ) {
			// Save to options.
					\CampaignBridge\Core\Storage::update_option( 'campaignbridge_provider', $data['provider'] );

			if ( 'mailchimp' === $data['provider'] ) {
				if ( ! empty( $data['mailchimp_api_key'] ) ) {
					\CampaignBridge\Core\Storage::update_option( 'campaignbridge_mailchimp_api_key', $data['mailchimp_api_key'] );
				}
				if ( ! empty( $data['mailchimp_audience'] ) ) {
					\CampaignBridge\Core\Storage::update_option( 'campaignbridge_mailchimp_audience', $data['mailchimp_audience'] );
				}
			}

			// Return modified data if needed.
			return $data;
		}
	)
	->success( __( 'Provider settings saved.', 'campaignbridge' ) )
	->submit( __( 'Save provider settings', 'campaignbridge' ) );
?>

<div class="campaignbridge-providers">
	<section class="cb-admin-card campaignbridge-providers__hero" aria-labelledby="campaignbridge-providers-title">
		<div class="campaignbridge-providers__flow" aria-hidden="true">
			<span class="campaignbridge-providers__content-mark dashicons dashicons-wordpress"></span>
			<span class="dashicons dashicons-arrow-right-alt"></span>
			<span class="campaignbridge-providers__provider-mark dashicons dashicons-email-alt"></span>
		</div>
		<div>
			<h2 id="campaignbridge-providers-title"><?php echo $campaignbridge_is_connected ? esc_html__( 'Your email provider is connected', 'campaignbridge' ) : esc_html__( 'Connect your email provider', 'campaignbridge' ); ?></h2>
			<p><?php esc_html_e( 'Choose how CampaignBridge prepares email templates for delivery. Use the built-in HTML workflow or connect Mailchimp.', 'campaignbridge' ); ?></p>
			<a class="button button-primary" href="#campaignbridge-provider-configuration"><?php echo $campaignbridge_is_connected ? esc_html__( 'Manage connection', 'campaignbridge' ) : esc_html__( 'Configure delivery', 'campaignbridge' ); ?></a>
		</div>
	</section>

	<aside class="cb-admin-card campaignbridge-providers__status" aria-labelledby="campaignbridge-provider-status-title">
		<header class="cb-admin-card__header"><span class="dashicons dashicons-admin-links"></span><div><h2 id="campaignbridge-provider-status-title"><?php esc_html_e( 'Delivery status', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Your currently selected workflow.', 'campaignbridge' ); ?></p></div></header>
		<div class="campaignbridge-providers__status-body">
			<span class="campaignbridge-providers__status-icon <?php echo $campaignbridge_is_connected || ! $campaignbridge_is_mailchimp ? 'is-ready' : ''; ?>"><span class="dashicons <?php echo $campaignbridge_is_connected || ! $campaignbridge_is_mailchimp ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span></span>
			<div><strong><?php echo $campaignbridge_is_mailchimp ? esc_html__( 'Mailchimp', 'campaignbridge' ) : esc_html__( 'HTML Email', 'campaignbridge' ); ?></strong><span><?php echo $campaignbridge_is_mailchimp ? esc_html( (string) $campaignbridge_mailchimp_status ) : esc_html__( 'Built in and always available', 'campaignbridge' ); ?></span></div>
		</div>
	</aside>

	<main class="campaignbridge-providers__main">
		<section id="campaignbridge-provider-configuration" class="cb-admin-card campaignbridge-providers__configuration" aria-labelledby="campaignbridge-provider-configuration-title">
			<header class="cb-admin-card__header"><span class="dashicons dashicons-admin-settings"></span><div><h2 id="campaignbridge-provider-configuration-title"><?php esc_html_e( 'Provider configuration', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Select a delivery method and securely store its connection details.', 'campaignbridge' ); ?></p></div><span class="cb-admin-badge <?php echo $campaignbridge_is_connected || ! $campaignbridge_is_mailchimp ? 'cb-admin-badge--success' : ''; ?>"><?php echo $campaignbridge_is_mailchimp ? ( $campaignbridge_is_connected ? esc_html__( 'Connected', 'campaignbridge' ) : esc_html__( 'Not connected', 'campaignbridge' ) ) : esc_html__( 'Ready', 'campaignbridge' ); ?></span></header>
			<?php $form->form_start(); ?>
			<div class="campaignbridge-providers__fields">
				<?php $form->render_field( 'provider' ); ?>
				<div data-mailchimp-field <?php echo $campaignbridge_is_mailchimp ? '' : 'hidden'; ?>><?php $form->render_field( 'mailchimp_api_key' ); ?></div>
				<div data-mailchimp-field <?php echo $campaignbridge_is_mailchimp ? '' : 'hidden'; ?>><?php $form->render_field( 'mailchimp_audience' ); ?></div>
			</div>
			<footer class="cb-admin-card__footer campaignbridge-providers__save"><span><?php esc_html_e( 'Connection details are encrypted before storage.', 'campaignbridge' ); ?></span><?php $form->render_submit(); ?></footer>
			<?php $form->form_end(); ?>
		</section>

		<section class="cb-admin-card campaignbridge-providers__html" aria-labelledby="campaignbridge-html-provider-title">
			<div class="campaignbridge-providers__html-mark" aria-hidden="true">&lt;/&gt;</div>
			<div><h2 id="campaignbridge-html-provider-title"><?php esc_html_e( 'HTML Email', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Compile and export provider-ready HTML without connecting an external account.', 'campaignbridge' ); ?></p></div>
			<span class="cb-admin-badge cb-admin-badge--success"><?php esc_html_e( 'Always available', 'campaignbridge' ); ?></span>
			<a class="button" href="<?php echo esc_url( $campaignbridge_editor_url ); ?>"><?php esc_html_e( 'Open editor', 'campaignbridge' ); ?></a>
		</section>

		<section class="campaignbridge-providers__available" aria-labelledby="campaignbridge-available-title">
			<header><h2 id="campaignbridge-available-title"><?php esc_html_e( 'More providers', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Additional integrations are planned but are not available yet.', 'campaignbridge' ); ?></p></header>
			<div class="campaignbridge-providers__provider-grid">
				<div class="cb-admin-card"><strong>Brevo</strong><span class="cb-admin-badge"><?php esc_html_e( 'Planned', 'campaignbridge' ); ?></span></div>
				<div class="cb-admin-card"><strong>ConvertKit</strong><span class="cb-admin-badge"><?php esc_html_e( 'Planned', 'campaignbridge' ); ?></span></div>
				<div class="cb-admin-card"><strong>MailerLite</strong><span class="cb-admin-badge"><?php esc_html_e( 'Planned', 'campaignbridge' ); ?></span></div>
			</div>
		</section>
	</main>

	<aside class="campaignbridge-providers__rail">
		<section class="cb-admin-card campaignbridge-providers__help" aria-labelledby="campaignbridge-provider-help-title">
			<header class="cb-admin-card__header"><span class="dashicons dashicons-lightbulb"></span><div><h2 id="campaignbridge-provider-help-title"><?php esc_html_e( 'Provider help', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Official resources for configuring Mailchimp.', 'campaignbridge' ); ?></p></div></header>
			<a class="cb-admin-action-row" href="https://mailchimp.com/help/about-api-keys/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-admin-network"></span><span><strong><?php esc_html_e( 'Find your Mailchimp API key', 'campaignbridge' ); ?></strong><small><?php esc_html_e( 'Mailchimp account documentation', 'campaignbridge' ); ?></small></span><span class="dashicons dashicons-external"></span></a>
			<a class="cb-admin-action-row" href="https://mailchimp.com/help/getting-started-with-audience/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-groups"></span><span><strong><?php esc_html_e( 'Choose an audience', 'campaignbridge' ); ?></strong><small><?php esc_html_e( 'Mailchimp audience documentation', 'campaignbridge' ); ?></small></span><span class="dashicons dashicons-external"></span></a>
		</section>
	</aside>
</div>
