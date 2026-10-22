<?php
/**
 * Explicit read-only provider verification.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\CLI;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Services\Operations\Operations_Factory;
use CampaignBridge\Workflow\Operations\Operation_Refused;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Check provider connectivity without exposing account details or changing credentials. */
final class Connection_Command extends Command {
	/**
	 * Check the stored provider connection and record the named user's audit event.
	 *
	 * ## OPTIONS
	 *
	 * [--provider=<provider>]
	 * : Provider slug. Default: mailchimp.
	 *
	 * @param array<int, string>         $args Positional arguments.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function check( array $args, array $assoc_args ): void {
		$this->respond(
			function () use ( $assoc_args ): array {
				$actor  = $this->actor( Capabilities::MANAGE_CONNECTIONS );
				$result = Operations_Factory::check_connection( $actor->user_id(), (string) ( $assoc_args['provider'] ?? 'mailchimp' ) );
				if ( ! $result->is_success() ) {
					throw new Operation_Refused( 'The provider connection check failed. Check the connection settings and try again.' );
				}

				return array(
					'provider' => 'mailchimp',
					'verified' => true,
				);
			}
		);
	}
}
