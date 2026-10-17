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
use CampaignBridge\Admin\Controllers\Provider_Webhook_Handler;
use CampaignBridge\Admin\Core\Form;

// Get current values.
global $screen;
if ( ! isset( $screen ) ) {
	$screen = null; // Fallback for PHPStan.
}
$campaignbridge_provider            = $screen ? $screen->get( 'provider', \CampaignBridge\Core\Storage::get_option( 'campaignbridge_provider', 'html' ) ) : \CampaignBridge\Core\Storage::get_option( 'campaignbridge_provider', 'html' );
$cb_repo                            = new \CampaignBridge\Repository\Provider_Connection_Repository();
$cb_conn                            = $cb_repo->get( 'mailchimp' );
$campaignbridge_mailchimp_api_key   = $screen ? $screen->get( 'mailchimp_api_key', $cb_conn ? $cb_conn->api_key() : '' ) : ( $cb_conn ? $cb_conn->api_key() : '' );
$campaignbridge_mailchimp_audience  = $screen ? $screen->get( 'mailchimp_audience', $cb_conn ? $cb_conn->audience_id() : '' ) : ( $cb_conn ? $cb_conn->audience_id() : '' );
$campaignbridge_is_connected        = $screen ? $screen->get( 'mailchimp_connected', false ) : false;
$campaignbridge_mailchimp_status    = $screen ? $screen->get( 'mailchimp_status', __( 'Not configured', 'campaignbridge' ) ) : __( 'Not configured', 'campaignbridge' );
$campaignbridge_mailchimp_audiences = $screen ? $screen->get( 'mailchimp_audiences', array() ) : array();
$campaignbridge_audience_error      = $screen ? $screen->get( 'mailchimp_audience_error', '' ) : '';
$campaignbridge_is_mailchimp        = 'mailchimp' === $campaignbridge_provider;
$campaignbridge_can_disconnect      = null !== $cb_conn && current_user_can( \CampaignBridge\Core\Capabilities::MANAGE_CONNECTIONS );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by the disconnect redirect.
$campaignbridge_disconnected  = isset( $_GET['disconnected'] ) && 'mailchimp' === sanitize_key( wp_unslash( $_GET['disconnected'] ) );
$campaignbridge_templates_url = admin_url( 'edit.php?post_type=cb_templates' );
$campaignbridge_webhooks      = null !== $cb_conn && current_user_can( \CampaignBridge\Core\Capabilities::MANAGE_CONNECTIONS ) ? \CampaignBridge\Services\Webhook\Webhook_Factory::registrar( 'mailchimp' ) : null;
$campaignbridge_webhook_count = null === $campaignbridge_webhooks ? 0 : count( $campaignbridge_webhooks->subscriptions() );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only result flags set by the webhook action redirect.
$campaignbridge_webhook_result = isset( $_GET['webhooks'] ) ? explode( ':', sanitize_text_field( wp_unslash( $_GET['webhooks'] ) ), 2 ) : array();
$campaignbridge_webhook_error  = isset( $_GET['webhook_error'] ) ? sanitize_key( wp_unslash( $_GET['webhook_error'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$campaignbridge_webhook_outcome = $campaignbridge_webhook_result[1] ?? '';
$campaignbridge_webhook_notice  = match ( $campaignbridge_webhook_outcome ) {
	Provider_Webhook_Handler::DONE => match ( $campaignbridge_webhook_result[0] ) {
		'disable' => __( 'Mailchimp webhooks were removed. Campaign status is still checked every few minutes.', 'campaignbridge' ),
		'rotate'  => __( 'Mailchimp webhooks were replaced with new signing secrets.', 'campaignbridge' ),
		default   => __( 'Mailchimp webhooks are on. Sent campaigns now update at the next background run, usually within a minute or two.', 'campaignbridge' ),
	},
	Provider_Webhook_Handler::NOT_CONNECTED => __( 'Connect Mailchimp before changing webhooks.', 'campaignbridge' ),
	Provider_Webhook_Handler::PARTIAL => sprintf( /* translators: %s: reason */ __( 'Some audiences could not be changed. %s Try again to finish.', 'campaignbridge' ), Provider_Webhook_Handler::error_message( $campaignbridge_webhook_error ) ),
	Provider_Webhook_Handler::FAILED => sprintf( /* translators: %s: reason */ __( 'Webhooks were not changed. %s', 'campaignbridge' ), Provider_Webhook_Handler::error_message( $campaignbridge_webhook_error ) ),
	default => '',
};
$campaignbridge_audience_options = is_array( $campaignbridge_mailchimp_audiences ) ? $campaignbridge_mailchimp_audiences : array();
if ( '' !== $campaignbridge_mailchimp_audience && ! isset( $campaignbridge_audience_options[ $campaignbridge_mailchimp_audience ] ) ) {
	$campaignbridge_audience_options[ $campaignbridge_mailchimp_audience ] = __( 'Current audience (temporarily unavailable)', 'campaignbridge' );
}

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
		// The custom save handler owns storage, so the form cannot load the key itself.
		->default( $campaignbridge_mailchimp_api_key )
		->context( 'api_key' )
		->validation( 'min_length', 10 )
		->description( __( 'Create or copy a key from your Mailchimp account settings. Existing saved keys remain encrypted.', 'campaignbridge' ) )
	->select( 'mailchimp_audience', __( 'Default audience', 'campaignbridge' ) )
		->options(
			array( '' => __( 'Select an audience', 'campaignbridge' ) )
			+ $campaignbridge_audience_options
		)
		->default( $campaignbridge_mailchimp_audience )
		->description(
			$campaignbridge_audience_error
				? sprintf( __( '%s Save a valid API key, then reload this page to try again.', 'campaignbridge' ), $campaignbridge_audience_error )
				: ( $campaignbridge_is_connected
					? __( 'Choose the audience CampaignBridge should use for new campaigns.', 'campaignbridge' )
					: __( 'Save and verify your Mailchimp API key to choose an audience.', 'campaignbridge' ) )
		)
		->end()
	->save_to_custom( array( \CampaignBridge\Admin\Controllers\Provider_Save_Handler::class, 'handle' ) )
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
				<?php if ( $campaignbridge_is_connected ) : ?>
					<div data-mailchimp-field <?php echo $campaignbridge_is_mailchimp ? '' : 'hidden'; ?>><?php $form->render_field( 'mailchimp_audience' ); ?></div>
				<?php endif; ?>
			</div>
			<footer class="cb-admin-card__footer campaignbridge-providers__save"><?php $form->render_submit(); ?></footer>
			<?php $form->form_end(); ?>
		</section>

		<?php if ( $campaignbridge_disconnected ) : ?>
			<div class="notice notice-success" role="status"><p><?php esc_html_e( 'Mailchimp was disconnected and its API key was removed from this site.', 'campaignbridge' ); ?></p></div>
		<?php endif; ?>

		<?php if ( $campaignbridge_can_disconnect ) : ?>
			<section class="cb-admin-notice cb-admin-notice--error campaignbridge-providers__disconnect" aria-labelledby="campaignbridge-disconnect-title">
				<div><span class="dashicons dashicons-dismiss"></span><span><strong id="campaignbridge-disconnect-title"><?php esc_html_e( 'Disconnect Mailchimp', 'campaignbridge' ); ?></strong><small><?php esc_html_e( 'Removes the stored API key from this site. Campaigns already scheduled in Mailchimp still send, and CampaignBridge cannot unschedule or reconcile them until you reconnect. To revoke the key itself, delete it in Mailchimp.', 'campaignbridge' ); ?></small></span></div>
				<form method="post" data-confirm="<?php echo esc_attr__( 'Disconnect Mailchimp? The stored API key will be removed from this site.', 'campaignbridge' ); ?>"><?php wp_nonce_field( 'campaignbridge_disconnect_mailchimp' ); ?><button class="button button-destructive" type="submit" name="disconnect_provider" value="mailchimp"><span class="dashicons dashicons-dismiss"></span><?php esc_html_e( 'Disconnect', 'campaignbridge' ); ?></button></form>
			</section>
		<?php endif; ?>

		<?php if ( null !== $campaignbridge_webhooks ) : ?>
			<section class="cb-admin-card campaignbridge-providers__webhooks" aria-labelledby="campaignbridge-webhooks-title">
				<header class="cb-admin-card__header"><span class="dashicons dashicons-update"></span><div><h2 id="campaignbridge-webhooks-title"><?php esc_html_e( 'Campaign webhooks', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Let Mailchimp tell this site when a campaign is sent, so its status updates at the next background run, usually within a minute or two, instead of at the next scheduled check. Only campaign events are requested; subscriber events are never sent to this site.', 'campaignbridge' ); ?></p></div><span class="cb-admin-badge <?php echo $campaignbridge_webhook_count > 0 ? 'cb-admin-badge--success' : ''; ?>"><?php echo $campaignbridge_webhook_count > 0 ? esc_html__( 'On', 'campaignbridge' ) : esc_html__( 'Off', 'campaignbridge' ); ?></span></header>
				<?php if ( '' !== $campaignbridge_webhook_notice ) : ?>
					<div class="notice <?php echo Provider_Webhook_Handler::DONE === $campaignbridge_webhook_outcome ? 'notice-success' : 'notice-error'; ?> inline" role="<?php echo Provider_Webhook_Handler::DONE === $campaignbridge_webhook_outcome ? 'status' : 'alert'; ?>"><p><?php echo esc_html( $campaignbridge_webhook_notice ); ?></p></div>
				<?php endif; ?>
				<p>
					<?php
					echo esc_html(
						$campaignbridge_webhook_count > 0
							? sprintf( /* translators: %d: number of audiences */ _n( 'Webhooks are on for %d audience. Each delivery is checked against its signing secret.', 'Webhooks are on for %d audiences. Each delivery is checked against its signing secret.', $campaignbridge_webhook_count, 'campaignbridge' ), $campaignbridge_webhook_count )
							: __( 'Webhooks are off. Campaign status is checked every few minutes. Turning webhooks on adds one webhook to each Mailchimp audience; this site must be reachable from the internet over HTTPS.', 'campaignbridge' )
					);
					?>
				</p>
				<p><label for="campaignbridge-webhook-url"><?php esc_html_e( 'Webhook address', 'campaignbridge' ); ?></label><br><input id="campaignbridge-webhook-url" class="large-text code" type="text" readonly value="<?php echo esc_attr( $campaignbridge_webhooks->url() ); ?>"></p>
				<div class="campaignbridge-providers__webhook-actions">
					<?php if ( 0 === $campaignbridge_webhook_count ) : ?>
						<form method="post"><?php wp_nonce_field( 'campaignbridge_webhooks_mailchimp' ); ?><button class="button button-primary" type="submit" name="provider_webhooks" value="enable"><?php esc_html_e( 'Turn on webhooks', 'campaignbridge' ); ?></button></form>
					<?php else : ?>
						<form method="post"><?php wp_nonce_field( 'campaignbridge_webhooks_mailchimp' ); ?><button class="button" type="submit" name="provider_webhooks" value="enable"><?php esc_html_e( 'Add missing audiences', 'campaignbridge' ); ?></button></form>
						<form method="post" data-confirm="<?php echo esc_attr__( 'Replace every Mailchimp webhook and its signing secret? Use this after the site address changes or if a secret may have leaked.', 'campaignbridge' ); ?>"><?php wp_nonce_field( 'campaignbridge_webhooks_mailchimp' ); ?><button class="button" type="submit" name="provider_webhooks" value="rotate"><?php esc_html_e( 'Rotate secrets', 'campaignbridge' ); ?></button></form>
						<form method="post" data-confirm="<?php echo esc_attr__( 'Turn off Mailchimp webhooks? Campaign status will be checked every few minutes instead.', 'campaignbridge' ); ?>"><?php wp_nonce_field( 'campaignbridge_webhooks_mailchimp' ); ?><button class="button button-destructive" type="submit" name="provider_webhooks" value="disable"><?php esc_html_e( 'Turn off webhooks', 'campaignbridge' ); ?></button></form>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="cb-admin-card campaignbridge-providers__html" aria-labelledby="campaignbridge-html-provider-title">
			<div class="campaignbridge-providers__html-mark" aria-hidden="true">&lt;/&gt;</div>
			<div><h2 id="campaignbridge-html-provider-title"><?php esc_html_e( 'HTML Email', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Compile and export provider-ready HTML without connecting an external account.', 'campaignbridge' ); ?></p></div>
			<span class="cb-admin-badge cb-admin-badge--success"><?php esc_html_e( 'Always available', 'campaignbridge' ); ?></span>
			<a class="button" href="<?php echo esc_url( $campaignbridge_templates_url ); ?>"><?php esc_html_e( 'Manage templates', 'campaignbridge' ); ?></a>
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
