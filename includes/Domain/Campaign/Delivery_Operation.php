<?php
/**
 * Provider-neutral remote mutation operation names.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stable operation vocabulary for delivery attempt identities. */
final class Delivery_Operation {
	public const CREATE_DRAFT = 'create_draft';
	public const UPDATE_DRAFT = 'update_draft';
	public const TEST_SEND    = 'test_send';
	public const SCHEDULE     = 'schedule';
	public const UNSCHEDULE   = 'unschedule';
	public const SEND         = 'send';
	public const CANCEL       = 'cancel';
	public const RECONCILE    = 'reconcile';

	/**
	 * Every delivery operation.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::CREATE_DRAFT,
			self::UPDATE_DRAFT,
			self::TEST_SEND,
			self::SCHEDULE,
			self::UNSCHEDULE,
			self::SEND,
			self::CANCEL,
			self::RECONCILE,
		);
	}
}
