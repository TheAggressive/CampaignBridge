<?php
/**
 * Provider webhook enable, rotate, and disable actions.
 *
 * @package CampaignBridge\Admin\Controllers
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Controllers;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Core\Error_Handler;
use CampaignBridge\Services\Webhook\Webhook_Factory;
use CampaignBridge\Workflow\Webhook\Webhook_Registration_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs one webhook action for an operator who can manage connections.
 *
 * Each action changes webhooks in the provider account, so it runs only
 * when an operator asks for it, never as part of a campaign workflow.
 */
final class Provider_Webhook_Handler {
	public const ACTIONS = array( 'enable', 'rotate', 'disable' );

	/** Outcome codes the providers screen explains. */
	public const DONE          = 'done';
	public const PARTIAL       = 'partial';
	public const FAILED        = 'failed';
	public const NOT_CONNECTED = 'not_connected';

	/**
	 * Run one action.
	 *
	 * @param string $provider Provider slug.
	 * @param string $action   One of ACTIONS.
	 * @return array{outcome: string, error: string}|null Null when refused.
	 */
	public static function handle( string $provider, string $action ): ?array {
		if ( ! in_array( $action, self::ACTIONS, true ) || ! current_user_can( Capabilities::MANAGE_CONNECTIONS ) ) {
			return null;
		}
		$registrar = Webhook_Factory::registrar( $provider );
		if ( null === $registrar ) {
			return null;
		}

		$result = match ( $action ) {
			'enable' => $registrar->enable(),
			'rotate' => $registrar->rotate(),
			default  => $registrar->disable(),
		};
		Error_Handler::info(
			'Provider webhooks changed',
			array(
				'provider' => $provider,
				'action'   => $action,
				'changed'  => $result->changed(),
				'failed'   => $result->failed_count(),
				'error'    => $result->error()?->code(),
				'user_id'  => get_current_user_id(),
			)
		);

		return array(
			'outcome' => self::outcome( $result ),
			'error'   => (string) $result->error()?->code(),
		);
	}

	/**
	 * Operator-facing message for a stored error code.
	 *
	 * @param string $code Normalized provider error code.
	 */
	public static function error_message( string $code ): string {
		return match ( $code ) {
			'mailchimp_webhook_url_rejected' => __( 'Mailchimp could not reach this site\'s webhook address. The site must be reachable from the internet over HTTPS.', 'campaignbridge' ),
			'mailchimp_authentication_failed', 'mailchimp_authorization_failed' => __( 'Mailchimp rejected the stored API key.', 'campaignbridge' ),
			'mailchimp_rate_limited' => __( 'Mailchimp rate limit reached. Try again later.', 'campaignbridge' ),
			'mailchimp_connection_timeout', 'mailchimp_connection_unavailable' => __( 'Mailchimp could not be reached. Try again later.', 'campaignbridge' ),
			default => __( 'Mailchimp returned an unexpected response.', 'campaignbridge' ),
		};
	}

	/**
	 * The screen's outcome code for a result.
	 *
	 * @param Webhook_Registration_Result $result Registration outcome.
	 */
	private static function outcome( Webhook_Registration_Result $result ): string {
		return match ( true ) {
			Webhook_Registration_Result::NOT_CONNECTED === $result->reason() => self::NOT_CONNECTED,
			$result->is_success() => self::DONE,
			$result->changed() > 0 => self::PARTIAL,
			default => self::FAILED,
		};
	}
}
