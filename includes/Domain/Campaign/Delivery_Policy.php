<?php
/**
 * Site delivery governance policy.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opt-in rules that narrow who may deliver and where tests may go.
 *
 * - Separate delivery: the person who approved a campaign may not schedule
 *   or send it, so delivery needs a second person.
 * - Test-recipient domains: when configured, tests may go only to these
 *   exact domains. A configured list with no valid domain allows nothing,
 *   so a mistyped policy fails closed instead of allowing everyone.
 *
 * Both are off by default. Policies only ever add refusals.
 */
final class Delivery_Policy {
	private const DOMAIN_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

	/**
	 * Build the delivery policy.
	 *
	 * @param bool                    $separate_delivery Whether the approver may not deliver.
	 * @param array<int, string>|null $test_domains      Allowed test domains; null means unrestricted.
	 */
	private function __construct(
		private readonly bool $separate_delivery,
		private readonly ?array $test_domains
	) {}

	/**
	 * A policy with no separation of duties and no test-recipient restriction.
	 */
	public static function unrestricted(): self {
		return new self( false, null );
	}

	/**
	 * Build the policy from stored settings.
	 *
	 * @param bool   $separate_delivery Whether the approver may not deliver.
	 * @param string $test_domains      Domains separated by commas, spaces, or lines; empty means unrestricted.
	 */
	public static function from_settings( bool $separate_delivery, string $test_domains ): self {
		$entries = preg_split( '/[\s,;]+/', strtolower( trim( $test_domains ) ), -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $entries || array() === $entries ) {
			return new self( $separate_delivery, null );
		}

		$domains = array();
		foreach ( $entries as $entry ) {
			$domain = ltrim( $entry, '@' );
			if ( 1 === preg_match( self::DOMAIN_PATTERN, $domain ) ) {
				$domains[ $domain ] = $domain;
			}
		}

		return new self( $separate_delivery, array_values( $domains ) );
	}

	/**
	 * Whether the policy is separate delivery.
	 */
	public function requires_separate_delivery(): bool {
		return $this->separate_delivery;
	}

	/**
	 * Whether the person delivering is independent of the recorded approver.
	 *
	 * An unrecorded approver cannot be shown to be independent, so it fails
	 * closed while the policy is on.
	 *
	 * @param int      $user_id     User ID.
	 * @param int|null $approved_by Approver's user ID.
	 */
	public function allows_delivery_by( int $user_id, ?int $approved_by ): bool {
		return ! $this->separate_delivery || ( null !== $approved_by && $approved_by !== $user_id );
	}

	/**
	 * The policy's test domains.
	 *
	 * @return array<int, string>|null Allowed test domains, or null when unrestricted.
	 */
	public function test_domains(): ?array {
		return $this->test_domains;
	}

	/**
	 * Whether a normalized address may receive a test.
	 *
	 * @param string $address Email address.
	 */
	public function allows_test_recipient( string $address ): bool {
		if ( null === $this->test_domains ) {
			return true;
		}
		$at = strrpos( $address, '@' );

		return false !== $at && in_array( strtolower( substr( $address, $at + 1 ) ), $this->test_domains, true );
	}
}
