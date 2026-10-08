<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
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
	public const IDENTIFIER_PATTERN = '^[a-z0-9][a-z0-9_-]{0,63}$';

	/** Opaque retry key; the workflow bounds it to 191 bytes. */
	public const IDEMPOTENCY_KEY_PATTERN = '^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$';

	public const MAX_PER_PAGE = 100;

	/** @return array<string, mixed> */
	public static function campaign_id(): array {
		return array(
			'description' => __( 'Opaque campaign identifier.', 'campaignbridge' ),
			'type'        => 'string',
			'required'    => true,
			'pattern'     => self::IDENTIFIER_PATTERN,
		);
	}

	/** @return array<string, mixed> */
	public static function template_id(): array {
		return self::positive_integer( __( 'Email template post ID.', 'campaignbridge' ) );
	}

	/** @return array<string, mixed> */
	public static function owner_user_id(): array {
		return self::positive_integer( __( 'Campaign owner user ID. Defaults to the current user.', 'campaignbridge' ), false );
	}

	/** @return array<string, mixed> */
	public static function expected_version(): array {
		return self::positive_integer( __( 'Campaign version the client last read. Stale versions are refused with 409.', 'campaignbridge' ) );
	}

	/** @return array<string, mixed> */
	public static function provider(): array {
		return array(
			'description' => __( 'Optional opaque provider reference. Null clears it.', 'campaignbridge' ),
			'type'        => array( 'string', 'null' ),
			'minLength'   => 1,
			'maxLength'   => 64,
			'pattern'     => self::IDENTIFIER_PATTERN,
		);
	}

	/** @return array<string, mixed> */
	public static function audience_reference(): array {
		return array(
			'description' => __( 'Optional opaque audience reference. Null clears it.', 'campaignbridge' ),
			'type'        => array( 'string', 'null' ),
			'minLength'   => 1,
			'maxLength'   => 191,
		);
	}

	/** @return array<string, mixed> */
	public static function idempotency_key(): array {
		return array(
			'description' => __( 'Client-generated retry key. Replaying the same key returns the original duplicate.', 'campaignbridge' ),
			'type'        => 'string',
			'required'    => true,
			'minLength'   => 1,
			'maxLength'   => 191,
			'pattern'     => self::IDEMPOTENCY_KEY_PATTERN,
		);
	}

	/** @return array<string, mixed> */
	public static function test_recipients(): array {
		return array(
			'description' => __( 'Addresses that receive this test only. They are never stored.', 'campaignbridge' ),
			'type'        => 'array',
			'required'    => true,
			'minItems'    => 1,
			'maxItems'    => Test_Delivery::MAX_RECIPIENTS,
			'items'       => array(
				'type'      => 'string',
				'format'    => 'email',
				'maxLength' => 254,
			),
		);
	}

	/** @return array<string, mixed> */
	public static function scheduled_for(): array {
		return array(
			'description' => __( 'Delivery time as an RFC 3339 date-time with an explicit offset, on the provider\'s scheduling interval.', 'campaignbridge' ),
			'type'        => 'string',
			'required'    => true,
			'format'      => 'date-time',
			'pattern'     => '(Z|[+-][0-9]{2}:[0-9]{2})$',
		);
	}

	/** @return array<string, mixed> */
	public static function confirm_audience_reference(): array {
		return array(
			'description' => __( 'The campaign\'s audience reference, repeated to confirm who will receive it.', 'campaignbridge' ),
			'type'        => 'string',
			'required'    => true,
			'minLength'   => 1,
			'maxLength'   => 191,
		);
	}

	/** @return array<string, mixed> */
	public static function test_format(): array {
		return array(
			'description' => __( 'Which part of the draft to test.', 'campaignbridge' ),
			'type'        => 'string',
			'default'     => Test_Delivery::FORMAT_HTML,
			'enum'        => array( Test_Delivery::FORMAT_HTML, Test_Delivery::FORMAT_TEXT ),
		);
	}

	/** @return array<string, array<string, mixed>> */
	public static function collection_args(): array {
		return array(
			'owner_user_id' => self::positive_integer( __( 'Owner whose campaigns are listed. Defaults to the current user.', 'campaignbridge' ), false ),
			'page'          => array(
				'description' => __( 'Current page of the collection.', 'campaignbridge' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
				'maximum'     => 1000000,
			),
			'per_page'      => array(
				'description' => __( 'Maximum number of campaigns returned.', 'campaignbridge' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => self::MAX_PER_PAGE,
			),
			'state'         => array(
				'description' => __( 'Only campaigns in these lifecycle states.', 'campaignbridge' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => Campaign_State::all(),
				),
				'maxItems'    => count( Campaign_State::all() ),
			),
			'provider'      => array(
				'description' => __( 'Only campaigns for this provider, or "none" for campaigns without one.', 'campaignbridge' ),
				'type'        => 'string',
				'pattern'     => self::IDENTIFIER_PATTERN,
			),
		);
	}

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
	public static function campaign_result(): array {
		return self::document( 'campaignbridge-campaign-result', array( 'campaign' => self::campaign() ) );
	}

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
	public static function validation_result(): array {
		return self::document(
			'campaignbridge-campaign-validation-result',
			array(
				'campaign'   => self::campaign(),
				'validation' => self::validation(),
			)
		);
	}

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
	public static function duplicate_result(): array {
		return self::document(
			'campaignbridge-campaign-duplicate-result',
			array(
				'campaign'          => self::campaign(),
				'idempotent_replay' => array( 'type' => 'boolean' ),
			)
		);
	}

	/** @return array<string, mixed> */
	public static function provider_draft_result(): array {
		return self::remote_result( 'campaignbridge-campaign-provider-draft-result' );
	}

	/** @return array<string, mixed> */
	public static function delivery_result(): array {
		return self::remote_result( 'campaignbridge-campaign-delivery-result' );
	}

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
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
								'code'      => array( 'type' => 'string' ),
								'category'  => array( 'type' => 'string' ),
								'retryable' => array( 'type' => 'boolean' ),
							),
						),
					),
				),
			),
		);
	}

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
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

	/** @return array<string, mixed> */
	private static function positive_integer( string $description, bool $required = true ): array {
		return array(
			'description' => $description,
			'type'        => 'integer',
			'required'    => $required,
			'minimum'     => 1,
		);
	}

	/** @return array<string, string> */
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
