<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * WordPress campaign review-input reader.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Review_Input_Source;
use CampaignBridge\Domain\Email\Design_Font_Registry;
use CampaignBridge\Domain\Email\Review_Input;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Workflow\Email\Template_Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Captures current template/design/post inputs once for immutable review. */
final class Campaign_Review_Input_Repository implements Campaign_Review_Input_Source {
	public function capture( Campaign $campaign, int $revision ): ?Review_Input {
		$template = get_post( $campaign->template_id() );
		if ( 1 > $revision || ! $template instanceof \WP_Post || Post_Type_Email_Template::POST_TYPE !== $template->post_type ) {
			return null;
		}

		$stored_fonts = Storage::get_post_meta( $campaign->template_id(), Design_Font_Registry::META_KEY, true );
		$metadata     = array( 'title' => (string) get_the_title( $template ) );
		$unsubscribe  = Storage::get_post_meta( $campaign->template_id(), 'campaignbridge_unsubscribe_url', true );
		if ( is_string( $unsubscribe ) && '' !== $unsubscribe ) {
			$metadata['unsubscribe_url'] = $unsubscribe;
		}

		$preview = new Template_Preview(
			new Post_Snapshot_Repository(),
			( new Brand_Kit_Repository() )->get(),
			Design_Font_Registry::from_json( $stored_fonts )
		);
		$input   = $preview->capture( (string) $template->post_content, $metadata );

		return new Review_Input(
			$input->content(),
			$input->blocks(),
			$input->context(),
			$input->design(),
			$revision,
			$input->compiler_version()
		);
	}
}
