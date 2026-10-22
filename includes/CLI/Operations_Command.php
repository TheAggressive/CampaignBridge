<?php
/**
 * Stored diagnostic output.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\CLI;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Services\Operations\Operations_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** CampaignBridge operational commands. */
final class Operations_Command extends Command {
	/**
	 * Print aggregate stored health as JSON without making provider requests.
	 *
	 * @param array<int, string>         $args Positional arguments.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function diagnostics( array $args, array $assoc_args ): void {
		$this->respond(
			function (): array {
				$this->actor( Capabilities::MANAGE );

				return Operations_Factory::diagnostics();
			}
		);
	}
}
