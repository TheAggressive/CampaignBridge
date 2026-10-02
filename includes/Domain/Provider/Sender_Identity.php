<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Normalized sender identity.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The organization's from-name and from-address a provider will send as.
 *
 * This is sender configuration owned by the organization, never subscriber
 * data. Only the display name and address are kept.
 */
final class Sender_Identity {
	private function __construct(
		private readonly string $from_name,
		private readonly string $from_email
	) {}

	public static function create( mixed $from_name, mixed $from_email ): self {
		$email = is_string( $from_email ) ? trim( $from_email ) : '';
		if ( 254 < strlen( $email ) || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( 'Sender address must be a valid email address.' );
		}

		return new self( Discovery_Values::label( $from_name, 'Sender name' ), strtolower( $email ) );
	}

	/** @param array<string, mixed> $data Stored sender. */
	public static function from_array( array $data ): self {
		return self::create( $data['from_name'] ?? null, $data['from_email'] ?? null );
	}

	public function from_name(): string {
		return $this->from_name;
	}

	public function from_email(): string {
		return $this->from_email;
	}

	/** @return array{from_name: string, from_email: string} */
	public function to_array(): array {
		return array(
			'from_name'  => $this->from_name,
			'from_email' => $this->from_email,
		);
	}
}
