<?php
/**
 * WP-CLI registration boundary.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers commands only in an explicitly initialized WP-CLI process. */
final class Commands {
	/** Register command names without reading storage or contacting providers. */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'campaignbridge', new Operations_Command() );
		\WP_CLI::add_command( 'campaignbridge jobs', new Jobs_Command() );
		\WP_CLI::add_command( 'campaignbridge connection', new Connection_Command() );
		\WP_CLI::add_command( 'campaignbridge campaign', new Campaign_Command() );
	}
}
