<?php
/**
 * Explicit campaign reconciliation.
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

/** Reconcile through the same actor, locks, and workflow used by REST. */
final class Campaign_Command extends Command {
	/**
	 * Read provider evidence to reconcile a campaign; never send or schedule.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Campaign ID.
	 *
	 * @param array<int, string>         $args Positional arguments.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function reconcile( array $args, array $assoc_args ): void {
		$this->respond(
			function () use ( $args ): array {
				$actor  = $this->actor( Capabilities::SEND_CAMPAIGNS );
				$id     = $this->identifier( $args[0] ?? '' );
				$result = Operations_Factory::reconcile( $actor, $id );
				if ( ! $result->is_success() ) {
					throw new Operation_Refused( $result->error()?->message() ?? 'Reconciliation did not settle the campaign. Check its timeline.' );
				}

				return array(
					'id'    => $id,
					'state' => $result->campaign()?->state(),
				);
			}
		);
	}
}
