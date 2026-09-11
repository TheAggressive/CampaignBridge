<?php
/**
 * Resolved email design composition boundary.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Design;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Email_Design_Resolver;
use CampaignBridge\Domain\Email\Email_Design_Validator;
use CampaignBridge\Domain\Email\Resolved_Email_Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds the shared runtime design from layered input and active identity. */
final class Email_Design_Factory {
	/**
	 * Resolve the packaged and active-theme design for a consumer workflow.
	 *
	 * @param Brand_Kit|null $brand_kit Active brand identity, when configured.
	 */
	public static function resolve( ?Brand_Kit $brand_kit = null ): Resolved_Email_Design {
		$loader = new Email_Design_Loader();

		return ( new Email_Design_Resolver( new Email_Design_Validator( $loader->schema() ) ) )
			->resolve_layers( $loader->layers(), $brand_kit );
	}
}
