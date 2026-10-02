<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Port method contract is documented by Provider_Token_Mapper.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- A mismatched discovery result fails closed.
/**
 * Mailchimp merge-tag mapping for canonical tokens.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Domain\Email\Token\Token_Registry;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Provider\Provider_Token_Mapper;
use CampaignBridge\Domain\Provider\Token_Mapping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps canonical provider tokens to Mailchimp merge tags.
 *
 * System tags (email, archive, unsubscribe) exist on every Mailchimp
 * audience. Name tags are ordinary audience merge fields that an owner can
 * rename or delete, so they map only when that audience's discovered merge
 * fields prove the tag exists. Audience custom fields are never promoted to
 * portable canonical tokens.
 */
final class Mailchimp_Token_Mapper implements Provider_Token_Mapper {
	/**
	 * Canonical token => array{merge tag, merge field the audience must have}.
	 *
	 * @var array<string, array{0: string, 1: string|null}>
	 */
	private const TAGS = array(
		'cb:subscriber.email'         => array( '*|EMAIL|*', null ),
		'cb:subscriber.first_name'    => array( '*|FNAME|*', 'FNAME' ),
		'cb:subscriber.last_name'     => array( '*|LNAME|*', 'LNAME' ),
		'cb:campaign.view_online_url' => array( '*|ARCHIVE|*', null ),
		'cb:campaign.unsubscribe_url' => array( '*|UNSUB|*', null ),
	);

	public function map( Token_Registry $registry, string $audience_id, ?Discovery_Result $merge_fields ): Token_Mapping {
		if (
			null !== $merge_fields
			&& ( 'mailchimp' !== $merge_fields->provider() || Discovery_Kind::MERGE_FIELDS !== $merge_fields->kind() || $audience_id !== $merge_fields->scope() )
		) {
			throw new \InvalidArgumentException( 'Merge fields must be the discovered Mailchimp fields of the same audience.' );
		}

		$available = array();
		foreach ( null === $merge_fields ? array() : $merge_fields->items() as $field ) {
			if ( $field instanceof Discovered_Merge_Field ) {
				$available[ $field->tag() ] = true;
			}
		}

		$mapped      = array();
		$unsupported = array();
		foreach ( $registry->all() as $definition ) {
			if ( ! $definition->requires_provider_resolution() ) {
				continue;
			}
			$id = $definition->get_id();
			if ( ! isset( self::TAGS[ $id ] ) ) {
				$unsupported[ $id ] = Token_Mapping::REASON_UNSUPPORTED;
				continue;
			}

			[ $tag, $field ] = self::TAGS[ $id ];
			if ( null === $field || isset( $available[ $field ] ) ) {
				$mapped[ $id ] = $tag;
			} elseif ( null === $merge_fields || ! $merge_fields->is_complete() ) {
				$unsupported[ $id ] = Token_Mapping::REASON_MERGE_FIELDS_INCOMPLETE;
			} else {
				$unsupported[ $id ] = Token_Mapping::REASON_MERGE_FIELD_MISSING;
			}
		}

		return Token_Mapping::create( 'mailchimp', $audience_id, $mapped, $unsupported, $registry );
	}
}
