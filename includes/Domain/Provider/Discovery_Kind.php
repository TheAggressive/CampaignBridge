<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Discovery kinds and their operations.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The reference lists a provider can discover.
 *
 * Audiences are account-wide; merge fields and segments are scoped to one
 * audience. Sender identities travel with audiences as their defaults.
 */
final class Discovery_Kind {
	public const AUDIENCES    = 'audiences';
	public const MERGE_FIELDS = 'merge_fields';
	public const SEGMENTS     = 'segments';

	/** @return array<int, string> */
	public static function all(): array {
		return array( self::AUDIENCES, self::MERGE_FIELDS, self::SEGMENTS );
	}

	public static function is_valid( string $kind ): bool {
		return in_array( $kind, self::all(), true );
	}

	/** The capability that must be advertised before this kind is discovered. */
	public static function operation( string $kind ): string {
		return match ( $kind ) {
			self::AUDIENCES    => Provider_Operation::DISCOVER_AUDIENCES,
			self::MERGE_FIELDS => Provider_Operation::DISCOVER_MERGE_FIELDS,
			self::SEGMENTS     => Provider_Operation::DISCOVER_SEGMENTS,
			default            => throw new \InvalidArgumentException( 'Unknown discovery kind.' ),
		};
	}

	/** Whether this kind is scoped to one audience rather than the account. */
	public static function is_audience_scoped( string $kind ): bool {
		self::operation( $kind );

		return self::AUDIENCES !== $kind;
	}

	/**
	 * Hydrate one stored item of this kind.
	 *
	 * @param array<string, mixed> $data Stored item.
	 */
	public static function item( string $kind, array $data ): Discovered_Item {
		return match ( $kind ) {
			self::AUDIENCES    => Discovered_Audience::from_array( $data ),
			self::MERGE_FIELDS => Discovered_Merge_Field::from_array( $data ),
			self::SEGMENTS     => Discovered_Segment::from_array( $data ),
			default            => throw new \InvalidArgumentException( 'Unknown discovery kind.' ),
		};
	}

	/** Whether an item value belongs to this kind. */
	public static function accepts( string $kind, Discovered_Item $item ): bool {
		return match ( $kind ) {
			self::AUDIENCES    => $item instanceof Discovered_Audience,
			self::MERGE_FIELDS => $item instanceof Discovered_Merge_Field,
			self::SEGMENTS     => $item instanceof Discovered_Segment,
			default            => false,
		};
	}
}
