<?php
/**
 * Campaigns screen: list, filter, and create campaigns.
 *
 * The screen is a React application over the campaign REST contracts: the
 * campaign list, and one campaign's review page when `campaign` is in the URL.
 * It receives only what it cannot ask the API for: the current user's
 * authority, admin URLs, the template REST base, which providers are
 * connected, whether delivery requires a second person, and the current
 * user's onboarding checklist.
 *
 * @package CampaignBridge\Admin\Screens
 */

use CampaignBridge\Admin\Onboarding_Checklist;
use CampaignBridge\Core\Capabilities;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Delivery_Policy_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;

global $screen;
if ( $screen ) {
	$campaignbridge_template_type = get_post_type_object( Post_Type_Email_Template::POST_TYPE );
	$campaignbridge_mailchimp     = ( new Provider_Connection_Repository() )->get( 'mailchimp' );

	$screen->asset_enqueue_script( 'campaignbridge-campaigns', 'dist/scripts/admin/campaigns/index.asset.php' );
	wp_set_script_translations( 'cb-campaignbridge-campaigns', 'campaignbridge' );
	$screen->localize_script(
		'campaignbridge-campaigns',
		'campaignbridgeCampaigns',
		array(
			'currentUserId'     => get_current_user_id(),
			'canManageAll'      => current_user_can( Capabilities::MANAGE ),
			'templatesRestBase' => $campaignbridge_template_type && $campaignbridge_template_type->rest_base ? $campaignbridge_template_type->rest_base : Post_Type_Email_Template::POST_TYPE,
			'newTemplateUrl'    => admin_url( 'post-new.php?post_type=' . Post_Type_Email_Template::POST_TYPE ),
			'editTemplateUrl'   => admin_url( 'post.php?action=edit&post=' ),
			'screenUrl'         => admin_url( 'admin.php?page=campaignbridge-campaigns' ),
			'separateDelivery'  => ( new Delivery_Policy_Repository() )->current()->requires_separate_delivery(),
			'providersUrl'      => admin_url( 'admin.php?page=campaignbridge-settings&tab=providers' ),
			'providers'         => array(
				array(
					'slug'      => 'mailchimp',
					'label'     => __( 'Mailchimp', 'campaignbridge' ),
					'connected' => null !== $campaignbridge_mailchimp,
					'audience'  => null === $campaignbridge_mailchimp ? '' : $campaignbridge_mailchimp->audience_id(),
				),
			),
			'onboarding'        => Onboarding_Checklist::for_user( get_current_user_id() ),
		)
	);
}
?>
<div id="campaignbridge-campaigns-root" class="campaignbridge-campaigns">
	<p class="campaignbridge-campaigns__loading"><?php esc_html_e( 'Loading campaigns…', 'campaignbridge' ); ?></p>
	<noscript><?php esc_html_e( 'The Campaigns screen needs JavaScript.', 'campaignbridge' ); ?></noscript>
</div>
