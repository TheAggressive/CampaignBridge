<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Which provider requests may be repeated.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Domain\Provider\Provider_Operation;
use CampaignBridge\Domain\Provider\Provider_Retry_Policy;
use WP_UnitTestCase;

/** Proves every operation is classified on purpose and nothing that reaches recipients is ever replayed. */
final class Provider_Retry_Policy_Test extends WP_UnitTestCase {
	public function test_every_provider_operation_is_classified_explicitly(): void {
		$unclassified = array_diff( Provider_Operation::all(), Provider_Retry_Policy::classified() );

		self::assertSame( array(), array_values( $unclassified ), 'A new operation must be classified, not left to the default.' );
	}

	public function test_nothing_that_reaches_recipients_or_changes_delivery_is_replayed(): void {
		$never = array_values( array_filter( Provider_Operation::all(), static fn ( string $operation ): bool => Provider_Retry_Policy::NEVER === Provider_Retry_Policy::for( $operation ) ) );

		self::assertSame(
			array( Provider_Operation::SEND_TEST, Provider_Operation::SCHEDULE, Provider_Operation::UNSCHEDULE, Provider_Operation::SEND, Provider_Operation::CANCEL ),
			$never
		);
		foreach ( $never as $operation ) {
			self::assertFalse( Provider_Retry_Policy::may_replay( $operation ), $operation );
		}
	}

	public function test_draft_creation_is_created_once_and_never_replayed(): void {
		self::assertSame( Provider_Retry_Policy::CREATE_ONCE, Provider_Retry_Policy::for( Provider_Operation::CREATE_DRAFT ) );
		self::assertFalse( Provider_Retry_Policy::may_replay( Provider_Operation::CREATE_DRAFT ) );
	}

	public function test_webhook_creation_is_created_once_and_lookups_are_repeatable(): void {
		self::assertSame( Provider_Retry_Policy::CREATE_ONCE, Provider_Retry_Policy::for( Provider_Operation::WEBHOOKS ) );
		self::assertFalse( Provider_Retry_Policy::may_replay( Provider_Operation::WEBHOOKS ), 'A replayed create could leave a webhook whose secret was never stored.' );
		self::assertTrue( Provider_Retry_Policy::may_replay( Provider_Retry_Policy::WEBHOOK_LOOKUP ) );
	}

	public function test_reads_and_exact_updates_are_repeatable(): void {
		foreach ( array( Provider_Operation::VERIFY_CONNECTION, Provider_Operation::DISCOVER_AUDIENCES, Provider_Operation::DISCOVER_MERGE_FIELDS, Provider_Operation::DISCOVER_SEGMENTS, Provider_Operation::RECONCILE, Provider_Operation::REPORTS, Provider_Retry_Policy::DRAFT_UPDATE ) as $operation ) {
			self::assertTrue( Provider_Retry_Policy::may_replay( $operation ), $operation );
		}
	}

	public function test_an_unknown_operation_is_never_replayed(): void {
		self::assertSame( Provider_Retry_Policy::NEVER, Provider_Retry_Policy::for( 'refund_everyone' ) );
		self::assertFalse( Provider_Retry_Policy::may_replay( '' ) );
	}
}
