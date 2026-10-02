<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed port signature is the contract.
/**
 * Provider token mapping port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Email\Token\Token_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps canonical provider-resolved tokens to one provider's syntax.
 *
 * Audience-dependent tokens are proven against that audience's discovered
 * merge fields. Without discovery, they are declared unsupported rather
 * than assumed to exist.
 */
interface Provider_Token_Mapper {
	public function map( Token_Registry $registry, string $audience_id, ?Discovery_Result $merge_fields ): Token_Mapping;
}
