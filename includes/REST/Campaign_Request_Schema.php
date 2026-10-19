<?php
/**
 * Campaign REST request argument schemas.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Domain\Provider\Test_Delivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validates campaign transport input before it reaches the workflow. */
final class Campaign_Request_Schema {
	/** Opaque lowercase identifier shared by campaigns, snapshots, and providers. */
	public const IDENTIFIER_PATTERN = '^[a-z0-9][a-z0-9_-]{0,63}$';

	/** Opaque retry key; the workflow bounds it to 191 bytes. */
	public const IDEMPOTENCY_KEY_PATTERN = '^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$';

	public const MAX_PER_PAGE = 100;

	/**
	 * Argument schema for a campaign ID in the route.
	 *
	 * @return array<string, mixed>
	 */
	public static function campaign_id(): array {
		return array(
			'description' => __( 'Opaque campaign identifier.', 'campaignbridge' ),
			'type'        => 'string',
			'required'    => true,
			'pattern'     => self::IDENTIFIER_PATTERN,
		);
	}

	/**
	 * Argument schema for an email template post ID.
	 *
	 * @return array<string, mixed>
	 */
	public static function template_id(): array {
		return self::positive_integer( __( 'Email template post ID.', 'campaignbridge' ) );
	}

	/**
	 * Argument schema for the campaign owner on create.
	 *
	 * @return array<string, mixed>
	 */
	public static function owner_user_id(): array {
		return self::positive_integer( __( 'Campaign owner user ID. Defaults to the current user.', 'campaignbridge' ), false );
	}

	/**
	 * Argument schema for the version the client last read.
	 *
	 * @return array<string, mixed>
	 */
	public static function expected_version(): array {
		return self::positive_integer( __( 'Campaign version the client last read. Stale versions are refused with 409.', 'campaignbridge' ) );
	}

	/**
	 * Argument schema for an optional provider reference.
	 *
	 * @return array<string, mixed>
	 */
	public static function provider(): array {
		return array(
			'description' => __( 'Optional opaque provider reference. Null clears it.', 'campaignbridge' ),
			'type'        => array( 'string', 'null' ),
			'minLength'   => 1,
			'maxLength'   => 64,
			'pattern'     => self::IDENTIFIER_PATTERN,
		);
	}

	/**
	 * Argument schema for an optional audience reference.
	 *
	 * @return array<string, mixed>
	 */
	public static function audience_reference(): array {
		return array(
			'description' => __( 'Optional opaque audience reference. Null clears it.', 'campaignbridge' ),
			'type'        => array( 'string', 'null' ),
			'minLength'   => 1,
			'maxLength'   => 191,
		);
	}

	/**
	 * Argument schema for a client-generated retry key.
	 *
	 * @return array<string, mixed>
	 */
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

	/**
	 * Argument schema for test-send recipients.
	 *
	 * @return array<string, mixed>
	 */
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

	/**
	 * Argument schema for a scheduled delivery time.
	 *
	 * @return array<string, mixed>
	 */
	public static function scheduled_for(): array {
		return array(
			'description' => __( 'Delivery time as an RFC 3339 date-time with an explicit offset, on the provider\'s scheduling interval.', 'campaignbridge' ),
			'type'        => 'string',
			'required'    => true,
			'format'      => 'date-time',
			'pattern'     => '(Z|[+-][0-9]{2}:[0-9]{2})$',
		);
	}

	/**
	 * Argument schema for the audience reference repeated as confirmation.
	 *
	 * @return array<string, mixed>
	 */
	public static function confirm_audience_reference(): array {
		return array(
			'description' => __( 'The campaign\'s audience reference, repeated to confirm who will receive it.', 'campaignbridge' ),
			'type'        => 'string',
			'required'    => true,
			'minLength'   => 1,
			'maxLength'   => 191,
		);
	}

	/**
	 * Argument schema for the part of the draft a test sends.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_format(): array {
		return array(
			'description' => __( 'Which part of the draft to test.', 'campaignbridge' ),
			'type'        => 'string',
			'default'     => Test_Delivery::FORMAT_HTML,
			'enum'        => array( Test_Delivery::FORMAT_HTML, Test_Delivery::FORMAT_TEXT ),
		);
	}

	/**
	 * Argument schemas for the campaign collection: owner, page, size, and filters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
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

	/**
	 * Argument schema for a positive integer.
	 *
	 * @param string $description Field description.
	 * @param bool   $required    Whether the value is required.
	 * @return array<string, mixed>
	 */
	private static function positive_integer( string $description, bool $required = true ): array {
		return array(
			'description' => $description,
			'type'        => 'integer',
			'required'    => $required,
			'minimum'     => 1,
		);
	}
}
