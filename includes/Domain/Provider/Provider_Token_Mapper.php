<?php
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
	/**
	 * Map CampaignBridge personalization tokens to the provider's merge syntax.
	 *
	 * @param Token_Registry        $registry     The personalization token registry.
	 * @param string                $audience_id  The provider's audience ID.
	 * @param Discovery_Result|null $merge_fields The audience's cached merge fields, when known.
	 */
	public function map( Token_Registry $registry, string $audience_id, ?Discovery_Result $merge_fields ): Token_Mapping;
}
