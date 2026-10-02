<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Canonical token to provider representation mapping.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Email\Token\Token_Definition;
use CampaignBridge\Domain\Email\Token\Token_Diagnostic;
use CampaignBridge\Domain\Email\Token\Token_Parser;
use CampaignBridge\Domain\Email\Token\Token_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How one provider audience represents each canonical provider token.
 *
 * Every provider-resolved token in the registry is either mapped to the
 * provider's representation or listed as unsupported with a reason code;
 * nothing is guessed. Translation returns new content and never alters the
 * canonical artifact it was given.
 */
final class Token_Mapping {
	/** The audience lacks the merge field this token needs. */
	public const REASON_MERGE_FIELD_MISSING = 'merge_field_missing';

	/** The merge-field list is truncated, so absence cannot be proven. */
	public const REASON_MERGE_FIELDS_INCOMPLETE = 'merge_fields_incomplete';

	/** The provider has no representation for this token. */
	public const REASON_UNSUPPORTED = 'unsupported';

	/**
	 * @param array<string, string> $mapped      Token ID => provider representation.
	 * @param array<string, string> $unsupported Token ID => reason code.
	 */
	private function __construct(
		private readonly string $provider,
		private readonly string $scope,
		private readonly array $mapped,
		private readonly array $unsupported
	) {}

	/**
	 * @param array<string, string> $mapped      Token ID => provider representation.
	 * @param array<string, string> $unsupported Token ID => reason code.
	 */
	public static function create( string $provider, string $scope, array $mapped, array $unsupported, Token_Registry $registry ): self {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $provider ) ) {
			throw new \InvalidArgumentException( 'Provider slug is invalid.' );
		}
		$scope   = Discovery_Values::scope( $scope );
		$reasons = array( self::REASON_MERGE_FIELD_MISSING, self::REASON_MERGE_FIELDS_INCOMPLETE, self::REASON_UNSUPPORTED );
		foreach ( $mapped as $id => $representation ) {
			if ( ! self::provider_token( $registry, $id ) || ! is_string( $representation ) || '' === $representation || 128 < strlen( $representation ) || str_contains( $representation, '{{' ) ) {
				throw new \InvalidArgumentException( 'Token mapping entry is invalid.' );
			}
		}
		foreach ( $unsupported as $id => $reason ) {
			if ( ! self::provider_token( $registry, $id ) || isset( $mapped[ $id ] ) || ! in_array( $reason, $reasons, true ) ) {
				throw new \InvalidArgumentException( 'Unsupported token entry is invalid.' );
			}
		}
		foreach ( $registry->all() as $definition ) {
			if ( $definition->requires_provider_resolution() && ! isset( $mapped[ $definition->get_id() ] ) && ! isset( $unsupported[ $definition->get_id() ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'Provider token %s must be mapped or declared unsupported.', $definition->get_id() ) );
			}
		}

		return new self( $provider, $scope, $mapped, $unsupported );
	}

	public function provider(): string {
		return $this->provider;
	}

	/** Audience the mapping was proven against, or '' when it is account-wide. */
	public function scope(): string {
		return $this->scope;
	}

	public function representation( string $token_id ): ?string {
		return $this->mapped[ $token_id ] ?? null;
	}

	/** @return array<string, string> Token ID => reason code. */
	public function unsupported(): array {
		return $this->unsupported;
	}

	/**
	 * Translate canonical tokens in one compiled field.
	 *
	 * The canonical parser validates the content first; any parse error, or
	 * any token without a provider representation, makes the translation
	 * incomplete. CampaignBridge-owned tokens must already be resolved by the
	 * compiler, so one left in the content is reported as unmapped.
	 */
	public function translate( string $content, Token_Registry $registry, Token_Parser $parser ): Token_Translation {
		$parsed = $parser->parse( $content, $registry );
		if ( ! $parsed->is_successful() ) {
			return new Token_Translation(
				$content,
				array(),
				array_values( array_unique( array_map( static fn ( Token_Diagnostic $error ): string => $error->get_code(), $parsed->get_errors() ) ) )
			);
		}

		$replacements = array();
		$unmapped     = array();
		foreach ( $parsed->get_tokens() as $definition ) {
			$id             = $definition->get_id();
			$representation = $this->mapped[ $id ] ?? null;
			if ( null === $representation ) {
				$unmapped[ $id ] = $id;
				continue;
			}
			$replacements[ '{{' . $id . '}}' ] = $representation;
		}

		return new Token_Translation( strtr( $content, $replacements ), array_values( $unmapped ), array() );
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return array(
			'provider'    => $this->provider,
			'scope'       => $this->scope,
			'mapped'      => $this->mapped,
			'unsupported' => $this->unsupported,
		);
	}

	private static function provider_token( Token_Registry $registry, mixed $id ): bool {
		$definition = is_string( $id ) ? $registry->get( $id ) : null;

		return $definition instanceof Token_Definition && $definition->requires_provider_resolution();
	}
}
