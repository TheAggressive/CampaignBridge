<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * WordPress campaign template input reader.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Campaign\Campaign_Envelope;
use CampaignBridge\Domain\Campaign\Campaign_Template_Input;
use CampaignBridge\Domain\Campaign\Campaign_Template_Input_Source;
use CampaignBridge\Domain\Email\Design_Font_Registry;
use CampaignBridge\Post_Types\Post_Type_Email_Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reads live template content and design metadata without compiling it. */
final class Campaign_Template_Input_Repository implements Campaign_Template_Input_Source {
	public function get( int $template_id ): ?Campaign_Template_Input {
		$template = get_post( $template_id );
		if ( ! $template instanceof \WP_Post || Post_Type_Email_Template::POST_TYPE !== $template->post_type ) {
			return null;
		}

		$metadata    = array( 'title' => (string) get_the_title( $template ) );
		$unsubscribe = Storage::get_post_meta( $template_id, 'campaignbridge_unsubscribe_url', true );
		if ( is_string( $unsubscribe ) && '' !== $unsubscribe ) {
			$metadata['unsubscribe_url'] = $unsubscribe;
		}

		try {
			$envelope = Campaign_Envelope::capture(
				self::meta_string( $template_id, 'campaignbridge_subject' ),
				self::meta_string( $template_id, 'campaignbridge_preheader' ),
				self::meta_string( $template_id, 'campaignbridge_sender_name' ),
				self::meta_string( $template_id, 'campaignbridge_sender_email' )
			);
		} catch ( \InvalidArgumentException ) {
			// An unbounded envelope value fails capture visibly instead of being truncated.
			return null;
		}

		return new Campaign_Template_Input(
			(string) $template->post_content,
			$metadata,
			Design_Font_Registry::from_json( Storage::get_post_meta( $template_id, Design_Font_Registry::META_KEY, true ) ),
			$envelope
		);
	}

	/** One string meta value, or '' when absent or not a string. */
	private static function meta_string( int $template_id, string $key ): string {
		$value = Storage::get_post_meta( $template_id, $key, true );

		return is_string( $value ) ? $value : '';
	}
}
