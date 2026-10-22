<?php
/**
 * Shared WP-CLI authorization and safe output boundary.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\CLI;

use CampaignBridge\Core\Campaign_Authorizer;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Operations\Operation_Refused;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Converts only application-authored refusals to CLI errors; unexpected details stay private. */
abstract class Command {
	/**
	 * Require a named WordPress user with the operation's normal capability.
	 *
	 * @param string $capability Required capability.
	 * @throws Operation_Refused When no authorized user was supplied.
	 */
	protected function actor( string $capability ): Campaign_Actor {
		$id = get_current_user_id();
		if ( $id < 1 || ! current_user_can( $capability ) ) {
			throw new Operation_Refused( 'Use --user=<login-or-id> with the required CampaignBridge capability.' );
		}

		return ( new Campaign_Authorizer() )->actor( $id );
	}

	/**
	 * Print JSON on success, or a nonzero error without external exception details.
	 *
	 * @param callable $operation Authorized request.
	 * @phpstan-param callable(): array<array-key, mixed> $operation
	 */
	protected function respond( callable $operation ): void {
		try {
			$data = $operation();
		} catch ( Operation_Refused $error ) {
			\WP_CLI::error( $error->getMessage() );
			return;
		} catch ( \Throwable ) {
			\WP_CLI::error( 'The operation could not be completed. Check CampaignBridge status and try again.' );
			return;
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			\WP_CLI::error( 'The command output could not be encoded.' );
			return;
		}
		\WP_CLI::line( $json );
	}

	/**
	 * Validate an identifier without echoing arbitrary input on refusal.
	 *
	 * @param string $id Candidate ID.
	 * @throws Operation_Refused On invalid input.
	 */
	protected function identifier( string $id ): string {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $id ) ) {
			throw new Operation_Refused( 'Supply a valid record ID.' );
		}

		return $id;
	}
}
