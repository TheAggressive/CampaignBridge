<?php
/**
 * Stable Campaign REST argument and response schemas.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Workflow\Campaign\Campaign_Actions;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Domain\Provider\Test_Delivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishes the public campaign transport contract.
 *
 * Argument bounds mirror the domain identifiers so malformed input is refused
 * before the workflow; the workflow still performs its own validation.
 */
final class Campaign_Rest_Schema {
	/** Opaque lowercase identifier shared by campaigns, snapshots, and providers. */
	public const IDENTIFIER_PATTERN = Campaign_Request_Schema::IDENTIFIER_PATTERN;

	/** Opaque retry key; the workflow bounds it to 191 bytes. */
	public const IDEMPOTENCY_KEY_PATTERN = Campaign_Request_Schema::IDEMPOTENCY_KEY_PATTERN;

	public const MAX_PER_PAGE = Campaign_Request_Schema::MAX_PER_PAGE;

	/**
	 * Argument schema for a campaign ID in the route.
	 *
	 * @return array<string, mixed>
	 */
	public static function campaign_id(): array {
		return Campaign_Request_Schema::campaign_id();
	}
	/**
	 * Argument schema for an email template post ID.
	 *
	 * @return array<string, mixed>
	 */
	public static function template_id(): array {
		return Campaign_Request_Schema::template_id();
	}
	/**
	 * Argument schema for the campaign owner on create.
	 *
	 * @return array<string, mixed>
	 */
	public static function owner_user_id(): array {
		return Campaign_Request_Schema::owner_user_id();
	}
	/**
	 * Argument schema for the version the client last read.
	 *
	 * @return array<string, mixed>
	 */
	public static function expected_version(): array {
		return Campaign_Request_Schema::expected_version();
	}
	/**
	 * Argument schema for an optional provider reference.
	 *
	 * @return array<string, mixed>
	 */
	public static function provider(): array {
		return Campaign_Request_Schema::provider();
	}
	/**
	 * Argument schema for an optional audience reference.
	 *
	 * @return array<string, mixed>
	 */
	public static function audience_reference(): array {
		return Campaign_Request_Schema::audience_reference();
	}
	/**
	 * Argument schema for a client-generated retry key.
	 *
	 * @return array<string, mixed>
	 */
	public static function idempotency_key(): array {
		return Campaign_Request_Schema::idempotency_key();
	}
	/**
	 * Argument schema for test-send recipients.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_recipients(): array {
		return Campaign_Request_Schema::test_recipients();
	}
	/**
	 * Argument schema for a scheduled delivery time.
	 *
	 * @return array<string, mixed>
	 */
	public static function scheduled_for(): array {
		return Campaign_Request_Schema::scheduled_for();
	}
	/**
	 * Argument schema for the audience reference repeated as confirmation.
	 *
	 * @return array<string, mixed>
	 */
	public static function confirm_audience_reference(): array {
		return Campaign_Request_Schema::confirm_audience_reference();
	}
	/**
	 * Argument schema for the part of the draft a test sends.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_format(): array {
		return Campaign_Request_Schema::test_format();
	}
	/**
	 * Argument schemas for the campaign collection: owner, page, size, and filters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function collection_args(): array {
		return Campaign_Request_Schema::collection_args();
	}
	/**
	 * Response schema for one campaign representation.
	 *
	 * @return array<string, mixed>
	 */
	public static function campaign(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'state', 'version', 'owner_user_id', 'template_id', 'provider', 'audience_reference', 'active_snapshot_id', 'created_at', 'updated_at', 'scheduled_for', 'approved_by_user_id', 'actions' ),
			'properties'           => array(
				'id'                  => self::field( self::campaign_id() ),
				'state'               => array(
					'type' => 'string',
					'enum' => Campaign_State::all(),
				),
				'version'             => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'owner_user_id'       => self::field( self::owner_user_id() ),
				'template_id'         => self::field( self::template_id() ),
				'provider'            => self::field( self::provider() ),
				'audience_reference'  => self::field( self::audience_reference() ),
				'active_snapshot_id'  => array(
					'type'    => array( 'string', 'null' ),
					'pattern' => self::IDENTIFIER_PATTERN,
				),
				'created_at'          => self::timestamp(),
				'updated_at'          => self::timestamp(),
				'scheduled_for'       => array(
					'type'   => array( 'string', 'null' ),
					'format' => 'date-time',
				),
				'approved_by_user_id' => array(
					'type'    => array( 'integer', 'null' ),
					'minimum' => 1,
				),
				'actions'             => array(
					'description' => __( 'Actions the current user may take on the campaign now.', 'campaignbridge' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => Campaign_Actions::all(),
					),
				),
			),
		);
	}

	/**
	 * Response schema for a single campaign.
	 *
	 * @return array<string, mixed>
	 */
	public static function campaign_result(): array {
		return self::document( 'campaignbridge-campaign-result', array( 'campaign' => self::campaign() ) );
	}

	/**
	 * Response schema for a page of campaigns.
	 *
	 * @return array<string, mixed>
	 */
	public static function collection(): array {
		$count = array(
			'type'    => 'integer',
			'minimum' => 0,
		);

		return self::document(
			'campaignbridge-campaign-collection',
			array(
				'items'      => array(
					'type'  => 'array',
					'items' => self::campaign(),
				),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'page', 'per_page', 'total', 'total_pages' ),
					'properties'           => array(
						'page'        => $count,
						'per_page'    => $count,
						'total'       => $count,
						'total_pages' => $count,
					),
				),
			)
		);
	}

	/**
	 * Response schema for a new snapshot and its validation.
	 *
	 * @return array<string, mixed>
	 */
	public static function snapshot_result(): array {
		return self::document(
			'campaignbridge-campaign-snapshot-result',
			array(
				'campaign'   => self::campaign(),
				'snapshot'   => self::snapshot_object(),
				'validation' => self::validation(),
			)
		);
	}

	/**
	 * The campaign's active snapshot and its stored, integrity-checked artifact.
	 *
	 * @return array<string, mixed>
	 */
	public static function reviewed_snapshot_result(): array {
		return self::document(
			'campaignbridge-campaign-reviewed-snapshot',
			array(
				'campaign' => self::campaign(),
				'snapshot' => self::snapshot_object(),
				'artifact' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'html', 'text', 'fingerprint', 'compiler_version', 'profile_version', 'sample' ),
					'properties'           => array(
						'html'             => array( 'type' => 'string' ),
						'text'             => array( 'type' => 'string' ),
						'fingerprint'      => array(
							'type'    => 'string',
							'pattern' => '^sha256:[0-9a-f]{64}$',
						),
						'compiler_version' => array( 'type' => 'string' ),
						'profile_version'  => array( 'type' => 'string' ),
						'sample'           => array(
							'type'       => array( 'object', 'null' ),
							'properties' => array(
								'html' => array( 'type' => 'string' ),
								'text' => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * A campaign and its provider reference, which is null before handoff.
	 *
	 * @return array<string, mixed>
	 */
	public static function remote_view_result(): array {
		return self::document(
			'campaignbridge-campaign-remote',
			array(
				'campaign' => self::campaign(),
				'remote'   => array_merge( self::remote(), array( 'type' => array( 'object', 'null' ) ) ),
				'watch'    => array(
					'description'          => __( 'Whether CampaignBridge checks the provider automatically: watching (next_check_at), stopped until the campaign changes (reason, since), or idle.', 'campaignbridge' ),
					'type'                 => array( 'object', 'null' ),
					'additionalProperties' => false,
					'required'             => array( 'state', 'next_check_at', 'reason', 'since' ),
					'properties'           => array(
						'state'         => array(
							'type' => 'string',
							'enum' => array( 'watching', 'stopped', 'idle' ),
						),
						'next_check_at' => array( 'type' => array( 'string', 'null' ) ),
						'reason'        => array( 'type' => array( 'string', 'null' ) ),
						'since'         => array( 'type' => array( 'string', 'null' ) ),
					),
				),
			)
		);
	}

	/**
	 * One immutable snapshot's identity and frozen envelope.
	 *
	 * @return array<string, mixed>
	 */
	private static function snapshot_object(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'revision', 'fingerprint', 'created_at', 'envelope' ),
			'properties'           => array(
				'envelope'    => self::envelope(),
				'id'          => array(
					'type'    => 'string',
					'pattern' => self::IDENTIFIER_PATTERN,
				),
				'revision'    => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'fingerprint' => array(
					'type'    => 'string',
					'pattern' => '^sha256:[0-9a-f]{64}$',
				),
				'created_at'  => self::timestamp(),
			),
		);
	}

	/**
	 * Response schema for validating a campaign's live template.
	 *
	 * @return array<string, mixed>
	 */
	public static function validation_result(): array {
		return self::document(
			'campaignbridge-campaign-validation-result',
			array(
				'campaign'   => self::campaign(),
				'validation' => self::validation(),
			)
		);
	}

	/**
	 * Response schema for previewing a campaign's live template.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview_result(): array {
		return self::document(
			'campaignbridge-campaign-preview-result',
			array(
				'campaign' => self::campaign(),
				'preview'  => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'html', 'text', 'diagnostics', 'assets', 'compiler_version', 'profile_version', 'fingerprint', 'sample' ),
					'properties'           => array(
						'html'             => array( 'type' => 'string' ),
						'text'             => array( 'type' => 'string' ),
						'diagnostics'      => self::diagnostics(),
						'assets'           => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'compiler_version' => array( 'type' => 'string' ),
						'profile_version'  => array( 'type' => 'string' ),
						'fingerprint'      => array( 'type' => 'string' ),
						'sample'           => array(
							'type'       => array( 'object', 'null' ),
							'properties' => array(
								'html' => array( 'type' => 'string' ),
								'text' => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Response schema for a duplicated campaign.
	 *
	 * @return array<string, mixed>
	 */
	public static function duplicate_result(): array {
		return self::document(
			'campaignbridge-campaign-duplicate-result',
			array(
				'campaign'          => self::campaign(),
				'idempotent_replay' => array( 'type' => 'boolean' ),
			)
		);
	}

	/**
	 * Response schema for creating the provider draft.
	 *
	 * @return array<string, mixed>
	 */
	public static function provider_draft_result(): array {
		return self::remote_result( 'campaignbridge-campaign-provider-draft-result' );
	}

	/**
	 * Response schema for schedule, unschedule, and send.
	 *
	 * @return array<string, mixed>
	 */
	public static function delivery_result(): array {
		return self::remote_result( 'campaignbridge-campaign-delivery-result' );
	}

	/**
	 * Response schema shared by operations that change the provider campaign.
	 *
	 * @param string $title Schema title.
	 * @return array<string, mixed>
	 */
	private static function remote_result( string $title ): array {
		return self::document(
			$title,
			array(
				'campaign'          => self::campaign(),
				'remote'            => self::remote(),
				'attempt'           => array_merge( self::attempt(), array( 'type' => array( 'object', 'null' ) ) ),
				'idempotent_replay' => array( 'type' => 'boolean' ),
			)
		);
	}

	/**
	 * Response schema for reconciliation.
	 *
	 * @return array<string, mixed>
	 */
	public static function reconcile_result(): array {
		return self::document(
			'campaignbridge-campaign-reconcile-result',
			array(
				'campaign'          => self::campaign(),
				'remote'            => array_merge( self::remote(), array( 'type' => array( 'object', 'null' ) ) ),
				'resolved_attempts' => array(
					'type'  => 'array',
					'items' => self::attempt(),
				),
			)
		);
	}

	/**
	 * Response schema for a test send.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_send_result(): array {
		return self::document(
			'campaignbridge-campaign-test-send-result',
			array(
				'campaign'          => self::campaign(),
				'remote'            => array_merge( self::remote(), array( 'type' => array( 'object', 'null' ) ) ),
				'attempt'           => self::attempt(),
				'test'              => array(
					'type'                 => array( 'object', 'null' ),
					'additionalProperties' => false,
					'required'             => array( 'format', 'recipient_count', 'snapshot_id', 'fingerprint' ),
					'properties'           => array(
						'format'          => self::field( self::test_format() ),
						'recipient_count' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => Test_Delivery::MAX_RECIPIENTS,
						),
						'snapshot_id'     => array(
							'type'    => 'string',
							'pattern' => self::IDENTIFIER_PATTERN,
						),
						'fingerprint'     => array(
							'type'    => 'string',
							'pattern' => '^sha256:[0-9a-f]{64}$',
						),
					),
				),
				'idempotent_replay' => array( 'type' => 'boolean' ),
			)
		);
	}

	/**
	 * The WordPress REST error envelope used by every campaign route.
	 *
	 * @return array<string, mixed>
	 */
	public static function error(): array {
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'campaignbridge-campaign-error',
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'code', 'message', 'data' ),
			'properties'           => array(
				'code'    => array(
					'type'    => 'string',
					'pattern' => '^[a-z0-9_]+$',
				),
				'message' => array( 'type' => 'string' ),
				'data'    => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'status' ),
					'properties'           => array(
						'status'          => array( 'type' => 'integer' ),
						'current_version' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'diagnostics'     => self::diagnostics(),
						'remote'          => self::remote(),
						'attempt'         => self::attempt(),
						'reason'          => array(
							'type' => 'string',
							'enum' => Campaign_Workflow_Error::reasons(),
						),
						'retry_after'     => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'provider_error'  => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => array( 'code', 'category', 'retryable' ),
							'properties'           => array(
								'code'        => array( 'type' => 'string' ),
								'category'    => array( 'type' => 'string' ),
								'retryable'   => array( 'type' => 'boolean' ),
								'retry_after' => array(
									'description' => __( 'Seconds the provider asked callers to wait before trying again.', 'campaignbridge' ),
									'type'        => 'integer',
									'minimum'     => 0,
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Schema for the normalized remote campaign reference.
	 *
	 * @return array<string, mixed>
	 */
	private static function remote(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'provider', 'remote_id', 'observed_state', 'observed_at', 'reconciled_at' ),
			'properties'           => array(
				'provider'       => array(
					'type'    => 'string',
					'pattern' => self::IDENTIFIER_PATTERN,
				),
				'remote_id'      => array( 'type' => 'string' ),
				'observed_state' => array(
					'type'    => 'string',
					'pattern' => '^[a-z0-9][a-z0-9_-]{0,31}$',
				),
				'observed_at'    => self::timestamp(),
				'reconciled_at'  => array_merge( self::timestamp(), array( 'type' => array( 'string', 'null' ) ) ),
			),
		);
	}

	/**
	 * Schema for one delivery attempt.
	 *
	 * @return array<string, mixed>
	 */
	private static function attempt(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'status', 'retryability' ),
			'properties'           => array(
				'id'           => array(
					'type'    => 'string',
					'pattern' => self::IDENTIFIER_PATTERN,
				),
				'status'       => array(
					'type' => 'string',
					'enum' => array( 'pending', 'succeeded', 'failed', 'unknown' ),
				),
				'retryability' => array(
					'type' => 'string',
					'enum' => array( 'unknown', 'retryable', 'not_retryable' ),
				),
			),
		);
	}

	/**
	 * The frozen envelope reviewed with a snapshot.
	 *
	 * @return array<string, mixed>
	 */
	private static function envelope(): array {
		$text = array( 'type' => 'string' );

		return array(
			'type'                 => array( 'object', 'null' ),
			'additionalProperties' => false,
			'required'             => array( 'subject', 'preview_text', 'from_name', 'from_email', 'complete', 'problems' ),
			'properties'           => array(
				'subject'      => $text,
				'preview_text' => $text,
				'from_name'    => $text,
				'from_email'   => $text,
				'complete'     => array( 'type' => 'boolean' ),
				'problems'     => array(
					'type'  => 'array',
					'items' => array(
						'type'    => 'string',
						'pattern' => '^[a-z_]+$',
					),
				),
			),
		);
	}

	/**
	 * Schema for a compiler validation outcome.
	 *
	 * @return array<string, mixed>
	 */
	private static function validation(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'valid', 'diagnostics', 'compiler_version', 'profile_version', 'fingerprint' ),
			'properties'           => array(
				'valid'            => array( 'type' => 'boolean' ),
				'diagnostics'      => self::diagnostics(),
				'compiler_version' => array( 'type' => 'string' ),
				'profile_version'  => array( 'type' => 'string' ),
				'fingerprint'      => array(
					'type'    => array( 'string', 'null' ),
					'pattern' => '^sha256:[0-9a-f]{64}$',
				),
			),
		);
	}

	/**
	 * Schema for compiler diagnostics.
	 *
	 * @return array<string, mixed>
	 */
	private static function diagnostics(): array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'severity', 'code', 'path', 'message' ),
				'properties'           => array(
					'severity' => array(
						'type' => 'string',
						'enum' => array( 'error', 'warning' ),
					),
					'code'     => array( 'type' => 'string' ),
					'path'     => array( 'type' => 'string' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
		);
	}

	/**
	 * Wrap properties in a closed top-level JSON schema document.
	 *
	 * @param string                              $title      Schema title.
	 * @param array<string, array<string, mixed>> $properties Top-level fields.
	 * @return array<string, mixed>
	 */
	private static function document( string $title, array $properties ): array {
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => $title,
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array_keys( $properties ),
			'properties'           => $properties,
		);
	}

	/**
	 * Schema for an RFC 3339 timestamp.
	 *
	 * @return array<string, string>
	 */
	private static function timestamp(): array {
		return array(
			'type'   => 'string',
			'format' => 'date-time',
		);
	}

	/**
	 * Remove request-only metadata before reusing an argument as a response field.
	 *
	 * @param array<string, mixed> $schema Argument schema.
	 * @return array<string, mixed>
	 */
	private static function field( array $schema ): array {
		unset( $schema['required'], $schema['description'] );
		return $schema;
	}
}
