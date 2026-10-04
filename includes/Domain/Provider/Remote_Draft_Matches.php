<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Remote drafts found by their correlation title.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The remote campaigns whose title exactly matched one draft request.
 *
 * `complete` is true only when the provider returned every campaign created
 * in the searched period, so an empty result proves absence. An incomplete
 * search proves nothing.
 */
final class Remote_Draft_Matches {
	/** @param array<int, string> $remote_ids Matching provider campaign IDs. */
	private function __construct( private readonly array $remote_ids, private readonly bool $complete ) {}

	/**
	 * Create a validated result.
	 *
	 * @param array<int, string> $remote_ids Matching provider campaign IDs.
	 * @throws \InvalidArgumentException When an ID is not a bounded string.
	 */
	public static function create( array $remote_ids, bool $complete ): self {
		foreach ( $remote_ids as $remote_id ) {
			if ( ! is_string( $remote_id ) || '' === $remote_id || 191 < strlen( $remote_id ) ) {
				throw new \InvalidArgumentException( 'Remote campaign IDs must be bounded strings.' );
			}
		}

		return new self( array_values( array_unique( $remote_ids ) ), $complete );
	}

	/** @return array<int, string> */
	public function remote_ids(): array {
		return $this->remote_ids;
	}

	public function is_complete(): bool {
		return $this->complete;
	}
}
