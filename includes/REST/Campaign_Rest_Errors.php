<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Stable Campaign workflow-to-HTTP error mapping.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Workflow\Campaign\Campaign_Draft_Result;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Result;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Centralizes public campaign error codes, statuses, and safe details. */
final class Campaign_Rest_Errors {
	/** Convert a failed workflow result into the standard WordPress REST error envelope. */
	public static function from_result( Campaign_Workflow_Result $result ): WP_Error {
		$error = $result->error();
		if ( null === $error ) {
			return self::unexpected();
		}

		$data = array( 'status' => self::status( $error ) );
		if ( Campaign_Workflow_Error::CONFLICT === $error->code() && null !== $result->campaign() ) {
			$data['current_version'] = $result->campaign()->version();
		}
		if ( null !== $result->compile_result() ) {
			$data['diagnostics'] = Campaign_Rest_Resource::diagnostics( $result->compile_result() );
		}

		return new WP_Error(
			'campaignbridge_campaign_' . $error->code(),
			$error->message(),
			$data
		);
	}

	/**
	 * Convert a failed draft handoff into the same public envelope.
	 *
	 * After a partial or ambiguous provider outcome the envelope also reports
	 * what already exists: the remote reference and the attempt, plus the
	 * normalized provider error. Raw provider detail is never included.
	 */
	public static function from_draft( Campaign_Draft_Result $result ): WP_Error {
		$error = $result->error();
		if ( null === $error ) {
			return self::unexpected();
		}

		$data = array( 'status' => self::status( $error ) );
		if ( Campaign_Workflow_Error::CONFLICT === $error->code() && null !== $result->campaign() ) {
			$data['current_version'] = $result->campaign()->version();
		}
		if ( null !== $result->reference() ) {
			$data['remote'] = Campaign_Rest_Resource::remote( $result->reference() );
		}
		if ( null !== $result->attempt() ) {
			$data['attempt'] = Campaign_Rest_Resource::attempt( $result->attempt() );
		}
		$provider = $result->provider_error();
		if ( null !== $provider ) {
			$data['provider_error'] = array(
				'code'      => $provider->code(),
				'category'  => $provider->category(),
				'retryable' => $provider->is_retryable(),
			);
		}

		return new WP_Error( 'campaignbridge_campaign_' . $error->code(), $error->message(), $data );
	}

	/** Convert a collection failure into the same public envelope. */
	public static function from_error( ?Campaign_Workflow_Error $error ): WP_Error {
		if ( null === $error ) {
			return self::unexpected();
		}

		return new WP_Error(
			'campaignbridge_campaign_' . $error->code(),
			$error->message(),
			array( 'status' => self::status( $error ) )
		);
	}

	private static function unexpected(): WP_Error {
		return new WP_Error(
			'campaignbridge_campaign_persistence_failed',
			__( 'The campaign request could not be completed.', 'campaignbridge' ),
			array( 'status' => Rest_Constants::HTTP_INTERNAL_SERVER_ERROR )
		);
	}

	/** The one documented workflow-code-to-HTTP-status contract (docs/api.md). */
	private static function status( Campaign_Workflow_Error $error ): int {
		return match ( $error->code() ) {
			Campaign_Workflow_Error::INVALID_INPUT,
			Campaign_Workflow_Error::VALIDATION_FAILED => Rest_Constants::HTTP_BAD_REQUEST,
			Campaign_Workflow_Error::FORBIDDEN => Rest_Constants::HTTP_FORBIDDEN,
			Campaign_Workflow_Error::NOT_FOUND => Rest_Constants::HTTP_NOT_FOUND,
			Campaign_Workflow_Error::INVALID_STATE,
			Campaign_Workflow_Error::CONFLICT,
			Campaign_Workflow_Error::MISSING_SNAPSHOT,
			Campaign_Workflow_Error::APPROVAL_NOT_ALLOWED,
			Campaign_Workflow_Error::IDEMPOTENCY_CONFLICT,
			Campaign_Workflow_Error::RECONCILIATION_REQUIRED => Rest_Constants::HTTP_CONFLICT,
			Campaign_Workflow_Error::PROVIDER_FAILED => Rest_Constants::HTTP_BAD_GATEWAY,
			default => Rest_Constants::HTTP_INTERNAL_SERVER_ERROR,
		};
	}
}
