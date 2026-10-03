<?php
/**
 * Provider operation vocabulary.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every operation a provider adapter may advertise.
 *
 * Operations stay separate so reversible discovery is never conflated with
 * irreversible delivery. A provider advertises only what it implements;
 * every other operation is explicitly unsupported.
 */
final class Provider_Operation {
	public const VERIFY_CONNECTION          = 'verify_connection';
	public const DISCOVER_AUDIENCES         = 'discover_audiences';
	public const DISCOVER_MERGE_FIELDS      = 'discover_merge_fields';
	public const DISCOVER_SEGMENTS          = 'discover_segments';
	public const DISCOVER_SENDERS           = 'discover_senders';
	public const DISCOVER_TEMPLATE_SECTIONS = 'discover_template_sections';
	public const EXPORT                     = 'export';
	public const CREATE_DRAFT               = 'create_draft';
	public const SEND_TEST                  = 'send_test';
	public const SCHEDULE                   = 'schedule';
	public const UNSCHEDULE                 = 'unschedule';
	public const SEND                       = 'send';
	public const CANCEL                     = 'cancel';
	public const RECONCILE                  = 'reconcile';
	public const REPORTS                    = 'reports';

	/**
	 * All operations in stable display order.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::VERIFY_CONNECTION,
			self::DISCOVER_AUDIENCES,
			self::DISCOVER_MERGE_FIELDS,
			self::DISCOVER_SEGMENTS,
			self::DISCOVER_SENDERS,
			self::DISCOVER_TEMPLATE_SECTIONS,
			self::EXPORT,
			self::CREATE_DRAFT,
			self::SEND_TEST,
			self::SCHEDULE,
			self::UNSCHEDULE,
			self::SEND,
			self::CANCEL,
			self::RECONCILE,
			self::REPORTS,
		);
	}

	/**
	 * Whether a value names a known operation.
	 *
	 * @param string $operation Candidate operation.
	 */
	public static function is_valid( string $operation ): bool {
		return in_array( $operation, self::all(), true );
	}
}
