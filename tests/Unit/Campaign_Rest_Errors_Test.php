<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Campaign REST error mapping tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\REST\Campaign_Rest_Errors;
use CampaignBridge\REST\Campaign_Rest_Schema;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Result;
use WP_UnitTestCase;

/** Locks every canonical workflow error to one public HTTP contract. */
final class Campaign_Rest_Errors_Test extends WP_UnitTestCase {
	/**
	 * @dataProvider statuses
	 */
	public function test_every_workflow_error_has_a_stable_status( string $code, int $status ): void {
		$result = Campaign_Workflow_Result::failure( new Campaign_Workflow_Error( $code, 'Safe message.' ) );
		$error  = Campaign_Rest_Errors::from_result( $result );

		self::assertSame( 'campaignbridge_campaign_' . $code, $error->get_error_code() );
		self::assertSame( $status, $error->get_error_data()['status'] );
		self::assertSame( 'Safe message.', $error->get_error_message() );
	}

	public function test_envelope_exposes_only_documented_safe_details(): void {
		$campaign = Campaign::create( 'campaign-one', 7, 42, null, null, '2026-09-28T12:00:00Z' );

		$conflict = Campaign_Rest_Errors::from_result(
			Campaign_Workflow_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::CONFLICT, 'Stale.' ), $campaign )
		);
		self::assertSame(
			array(
				'status'          => 409,
				'current_version' => 1,
			),
			$conflict->get_error_data()
		);

		$forbidden = Campaign_Rest_Errors::from_result(
			Campaign_Workflow_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::FORBIDDEN, 'Denied.' ), $campaign )
		);
		self::assertSame( array( 'status' => 403 ), $forbidden->get_error_data() );

		$compiled = new Compile_Result( '', '', array( Compile_Diagnostic::error( 'unsupported_block', 'blocks.0', 'Unsupported.' ) ), array(), '', '1', '1' );
		$invalid  = Campaign_Rest_Errors::from_result(
			Campaign_Workflow_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::VALIDATION_FAILED, 'Invalid.' ), $campaign, $compiled )
		);
		self::assertSame( 'unsupported_block', $invalid->get_error_data()['diagnostics'][0]['code'] );

		foreach ( array( $conflict, $forbidden, $invalid ) as $error ) {
			$envelope = rest_convert_error_to_response( $error )->get_data();
			self::assertTrue( rest_validate_value_from_schema( $envelope, Campaign_Rest_Schema::error(), 'error' ) );
		}

		$unexpected = Campaign_Rest_Errors::from_result( Campaign_Workflow_Result::success( $campaign ) );
		self::assertSame( 'campaignbridge_campaign_persistence_failed', $unexpected->get_error_code() );
		self::assertSame( 500, $unexpected->get_error_data()['status'] );
	}

	/** @return array<string, array{string, int}> */
	public function statuses(): array {
		return array(
			'not found'            => array( Campaign_Workflow_Error::NOT_FOUND, 404 ),
			'invalid state'        => array( Campaign_Workflow_Error::INVALID_STATE, 409 ),
			'conflict'             => array( Campaign_Workflow_Error::CONFLICT, 409 ),
			'invalid input'        => array( Campaign_Workflow_Error::INVALID_INPUT, 400 ),
			'validation failed'    => array( Campaign_Workflow_Error::VALIDATION_FAILED, 400 ),
			'missing snapshot'     => array( Campaign_Workflow_Error::MISSING_SNAPSHOT, 409 ),
			'approval not allowed' => array( Campaign_Workflow_Error::APPROVAL_NOT_ALLOWED, 409 ),
			'forbidden'            => array( Campaign_Workflow_Error::FORBIDDEN, 403 ),
			'persistence failed'   => array( Campaign_Workflow_Error::PERSISTENCE_FAILED, 500 ),
			'idempotency conflict' => array( Campaign_Workflow_Error::IDEMPOTENCY_CONFLICT, 409 ),
			'reconciliation'       => array( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 409 ),
			'provider failed'      => array( Campaign_Workflow_Error::PROVIDER_FAILED, 502 ),
			'rate limited'         => array( Campaign_Workflow_Error::RATE_LIMITED, 429 ),
		);
	}
}
