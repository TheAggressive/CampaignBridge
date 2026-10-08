<?php
/**
 * First-run checklist derived from what the site has actually set up.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Onboarding_Dismissal_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guides a new site from installation to a first reviewable campaign.
 *
 * Every step reads stored state, so the checklist cannot drift from the site:
 * deleting the last template or disconnecting Mailchimp reopens its step.
 * Mailchimp steps are optional because HTML export needs no provider. The
 * checklist may be dismissed only when every required step is done, and it
 * returns when one is undone again.
 */
final class Onboarding_Checklist {
	/**
	 * The checklist for one user.
	 *
	 * @param int $user_id User viewing it.
	 * @return array{steps: array<int, array{id: string, label: string, done: bool, optional: bool, url: string|null}>, complete: bool, dismissed: bool, visible: bool}
	 */
	public static function for_user( int $user_id ): array {
		$connection = ( new Provider_Connection_Repository() )->get( 'mailchimp' );
		$templates  = wp_count_posts( Post_Type_Email_Template::POST_TYPE );
		$can_manage = user_can( $user_id, Capabilities::MANAGE_CONNECTIONS );
		$can_design = user_can( $user_id, Capabilities::MANAGE );

		$steps = array(
			array(
				'id'       => 'provider',
				'label'    => __( 'Connect Mailchimp and verify the connection', 'campaignbridge' ),
				'done'     => null !== $connection && $connection->is_verified(),
				'optional' => true,
				'url'      => $can_manage ? admin_url( 'admin.php?page=campaignbridge-settings&tab=providers' ) : null,
			),
			array(
				'id'       => 'audience',
				'label'    => __( 'Choose a default Mailchimp audience', 'campaignbridge' ),
				'done'     => null !== $connection && '' !== $connection->audience_id(),
				'optional' => true,
				'url'      => $can_manage ? admin_url( 'admin.php?page=campaignbridge-settings&tab=providers' ) : null,
			),
			array(
				'id'       => 'brand',
				'label'    => __( 'Set up your Brand Kit colors and fonts', 'campaignbridge' ),
				'done'     => Brand_Kit::SOURCE_DEFAULTS !== ( new Brand_Kit_Repository() )->get()->source(),
				'optional' => false,
				'url'      => $can_design ? admin_url( 'admin.php?page=campaignbridge-settings&tab=brand' ) : null,
			),
			array(
				'id'       => 'template',
				'label'    => __( 'Publish an email template', 'campaignbridge' ),
				'done'     => 0 < (int) ( $templates->publish ?? 0 ),
				'optional' => false,
				'url'      => user_can( $user_id, Capabilities::EDIT_TEMPLATES ) ? admin_url( 'post-new.php?post_type=' . Post_Type_Email_Template::POST_TYPE ) : null,
			),
			array(
				'id'       => 'campaign',
				'label'    => __( 'Create your first campaign', 'campaignbridge' ),
				'done'     => 0 < ( new Campaign_Repository() )->count_for_owner( $user_id ),
				'optional' => false,
				'url'      => null,
			),
		);

		$complete  = array() === array_filter( $steps, static fn ( array $step ): bool => ! $step['optional'] && ! $step['done'] );
		$dismissed = ( new Onboarding_Dismissal_Repository() )->is_dismissed( $user_id );

		return array(
			'steps'     => $steps,
			'complete'  => $complete,
			'dismissed' => $dismissed,
			'visible'   => ! ( $complete && $dismissed ),
		);
	}

	/**
	 * Dismiss the checklist for one user, only once it is complete.
	 *
	 * @param int $user_id User dismissing it.
	 * @return bool Whether it was dismissed.
	 */
	public static function dismiss( int $user_id ): bool {
		if ( ! self::for_user( $user_id )['complete'] ) {
			return false;
		}

		( new Onboarding_Dismissal_Repository() )->dismiss( $user_id );

		return true;
	}
}
