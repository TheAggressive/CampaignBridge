<?php
/**
 * Delivery governance policies tab.
 *
 * Saving requires `campaignbridge_manage` (enforced by the form system), so
 * people who only approve, test, or send cannot relax these policies.
 *
 * @package CampaignBridge
 */

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Core\Storage;
use CampaignBridge\Repository\Delivery_Policy_Repository;

$campaignbridge_policy_form = Form::make( 'delivery_policies' )
	->div()
	->success( __( 'Delivery policies saved.', 'campaignbridge' ) )
	->save_to_options( 'campaignbridge_' )
	->switch( 'policy_separate_delivery', __( 'Require a second person to deliver', 'campaignbridge' ) )
		->default( (bool) Storage::get_option( Delivery_Policy_Repository::SEPARATE_DELIVERY, false ) )
		->description( __( 'The person who approved a campaign cannot schedule or send it. Unscheduling is always allowed.', 'campaignbridge' ) )
		->end()
	->textarea( 'policy_test_recipient_domains', __( 'Allowed test-recipient domains', 'campaignbridge' ) )
		->default( (string) Storage::get_option( Delivery_Policy_Repository::TEST_DOMAINS, '' ) )
		->rows( 3 )
		->placeholder( 'example.com' )
		->description( __( 'One domain per line, matched exactly. Leave empty to allow any address. If no line is a valid domain, no test can be sent.', 'campaignbridge' ) )
		->end()
	->submit( __( 'Save policies', 'campaignbridge' ) );

// Apply a submitted change first so the summary below reflects what is now in effect.
$campaignbridge_policy_form->get_form()->ensure_initialized();
$campaignbridge_policy_form->get_form()->prepare_for_rendering();
$campaignbridge_policy         = ( new Delivery_Policy_Repository() )->current();
$campaignbridge_policy_domains = $campaignbridge_policy->test_domains();
?>
<div class="campaignbridge-policies">
	<?php $campaignbridge_policy_form->form_start(); ?>
	<section class="cb-admin-card campaignbridge-card" aria-labelledby="campaignbridge-policy-delivery-title">
		<header class="cb-admin-card__header"><span class="dashicons dashicons-groups" aria-hidden="true"></span><div><h2 id="campaignbridge-policy-delivery-title"><?php esc_html_e( 'Separation of duties', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Require a different person to deliver a campaign than the one who approved it.', 'campaignbridge' ); ?></p></div></header>
		<?php $campaignbridge_policy_form->render_field( 'policy_separate_delivery' ); ?>
	</section>
	<section class="cb-admin-card campaignbridge-card" aria-labelledby="campaignbridge-policy-tests-title">
		<header class="cb-admin-card__header"><span class="dashicons dashicons-email" aria-hidden="true"></span><div><h2 id="campaignbridge-policy-tests-title"><?php esc_html_e( 'Test sends', 'campaignbridge' ); ?></h2><p><?php esc_html_e( 'Limit test emails to your organization\'s domains.', 'campaignbridge' ); ?></p></div></header>
		<?php $campaignbridge_policy_form->render_field( 'policy_test_recipient_domains' ); ?>
		<p class="campaignbridge-policies__effective" role="status">
			<?php
			if ( null === $campaignbridge_policy_domains ) {
				esc_html_e( 'In effect: tests may be sent to any address.', 'campaignbridge' );
			} elseif ( array() === $campaignbridge_policy_domains ) {
				esc_html_e( 'In effect: no valid domain is configured, so no test can be sent.', 'campaignbridge' );
			} else {
				/* translators: %s: comma-separated allowed domains. */
				echo esc_html( sprintf( __( 'In effect: tests may be sent only to %s.', 'campaignbridge' ), implode( ', ', $campaignbridge_policy_domains ) ) );
			}
			?>
		</p>
	</section>
	<?php $campaignbridge_policy_form->render_submit(); ?>
	<?php $campaignbridge_policy_form->form_end(); ?>
</div>
