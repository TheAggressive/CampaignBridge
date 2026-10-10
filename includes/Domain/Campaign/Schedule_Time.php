<?php
/**
 * Validated campaign delivery time.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One unambiguous future delivery time, normalized to UTC.
 *
 * The input must carry an explicit offset (`Z` or `±hh:mm`), because a
 * local time without a zone could send at the wrong hour. The time must
 * fall on the provider's scheduling interval, at least the minimum lead
 * time ahead, and within the scheduling horizon.
 */
final class Schedule_Time {
	/** A schedule must leave time to unschedule before sending. */
	public const MIN_LEAD_SECONDS = 600;

	/** A bound that catches mistyped years without limiting real planning. */
	public const MAX_HORIZON_SECONDS = 366 * 86400;

	private const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/';

	/**
	 * Build the schedule time.
	 *
	 * @param string $utc UTC timestamp.
	 */
	private function __construct( private readonly string $utc ) {}

	/**
	 * Validate a requested time against the current time and provider interval.
	 *
	 * @param string $requested        ISO 8601 date-time with an explicit offset.
	 * @param string $now              Current canonical UTC timestamp.
	 * @param int    $interval_minutes Provider scheduling interval; 1 means any minute.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function parse( string $requested, string $now, int $interval_minutes ): self {
		if ( 1 !== preg_match( self::PATTERN, $requested ) ) {
			throw new \InvalidArgumentException( 'The delivery time must be an ISO 8601 date-time with an explicit time zone offset.' );
		}
		$time   = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:sP', str_ends_with( $requested, 'Z' ) ? substr( $requested, 0, -1 ) . '+00:00' : $requested );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( false === $time || ( false !== $errors && ( 0 < $errors['warning_count'] || 0 < $errors['error_count'] ) ) ) {
			throw new \InvalidArgumentException( 'The delivery time is not a valid calendar date and time.' );
		}

		$at = $time->getTimestamp();
		if ( 0 !== $at % 60 || 0 !== intdiv( $at, 60 ) % max( 1, $interval_minutes ) ) {
			throw new \InvalidArgumentException( sprintf( 'The delivery time must fall on a %d-minute boundary.', max( 1, $interval_minutes ) ) );
		}
		$current = (int) strtotime( $now );
		if ( $at < $current + self::MIN_LEAD_SECONDS ) {
			throw new \InvalidArgumentException( sprintf( 'The delivery time must be at least %d minutes in the future.', intdiv( self::MIN_LEAD_SECONDS, 60 ) ) );
		}
		if ( $at > $current + self::MAX_HORIZON_SECONDS ) {
			throw new \InvalidArgumentException( 'The delivery time must be within one year.' );
		}

		return new self( gmdate( 'Y-m-d\TH:i:s\Z', $at ) );
	}

	/** Canonical UTC timestamp, `Y-m-d\TH:i:s\Z`. */
	public function utc(): string {
		return $this->utc;
	}
}
