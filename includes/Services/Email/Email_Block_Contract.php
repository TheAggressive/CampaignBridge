<?php
/**
 * Authoritative email authoring block contract.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use JsonException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the one supported-block grammar shared by PHP and the editor.
 *
 * `includes/Email_Blocks/email-blocks.json` names every authoring block, its
 * origin (a WordPress Core block or a CampaignBridge block), the
 * CampaignBridge email semantics it compiles through, and its permitted
 * children. The editor allowlist, renderer nesting, and Core normalization
 * derive from this contract; the compiler registry is parity-tested against it.
 */
final class Email_Block_Contract {
	public const SOURCE_CORE           = 'core';
	public const SOURCE_CAMPAIGNBRIDGE = 'campaignbridge';

	private const MAX_BYTES = 65536;

	/**
	 * Decoded block entries keyed by block name.
	 *
	 * @var array<string, array{source: string, semantics: string, children: array<int, string>}>|null
	 */
	private static ?array $blocks = null;

	/**
	 * Every supported authoring block name in contract order.
	 *
	 * @return array<int, string>
	 */
	public static function names(): array {
		return array_keys( self::blocks() );
	}

	/**
	 * Supported WordPress Core authoring block names.
	 *
	 * @return array<int, string>
	 */
	public static function core_names(): array {
		return array_keys( array_filter( self::blocks(), static fn ( array $block ): bool => self::SOURCE_CORE === $block['source'] ) );
	}

	/**
	 * Whether a block belongs to the supported authoring grammar.
	 *
	 * @param string $name Block name.
	 */
	public static function has( string $name ): bool {
		return isset( self::blocks()[ $name ] );
	}

	/**
	 * Whether a supported block is a WordPress Core authoring block.
	 *
	 * @param string $name Block name.
	 */
	public static function is_core( string $name ): bool {
		return self::SOURCE_CORE === ( self::blocks()[ $name ]['source'] ?? null );
	}

	/**
	 * The CampaignBridge email semantics a block compiles through.
	 *
	 * @param string $name Block name.
	 */
	public static function semantics( string $name ): ?string {
		return self::blocks()[ $name ]['semantics'] ?? null;
	}

	/**
	 * Permitted child block names for one block.
	 *
	 * @param string $name Parent block name.
	 * @return array<int, string>
	 */
	public static function children( string $name ): array {
		return self::blocks()[ $name ]['children'] ?? array();
	}

	/**
	 * Decode and validate the contract once per request.
	 *
	 * @return array<string, array{source: string, semantics: string, children: array<int, string>}>
	 * @throws \DomainException When the packaged contract is missing or malformed.
	 */
	private static function blocks(): array {
		if ( null !== self::$blocks ) {
			return self::$blocks;
		}

		$path = dirname( __DIR__, 2 ) . '/Email_Blocks/email-blocks.json';
		$size = is_file( $path ) ? filesize( $path ) : false;
		if ( false === $size || 0 === $size || self::MAX_BYTES < $size ) {
			throw new \DomainException( 'Email block contract is missing or exceeds its size limit.' );
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Fixed repository-owned local file.
		try {
			$document = json_decode( (string) $content, true, 16, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new \DomainException( 'Email block contract contains invalid JSON.' );
		}

		$entries = is_array( $document ) && is_array( $document['blocks'] ?? null ) ? $document['blocks'] : array();
		$blocks  = array();
		foreach ( $entries as $name => $entry ) {
			$source    = is_array( $entry ) ? ( $entry['source'] ?? null ) : null;
			$semantics = is_array( $entry ) ? ( $entry['semantics'] ?? null ) : null;
			$children  = is_array( $entry ) ? ( $entry['children'] ?? null ) : null;
			if (
				! is_string( $name )
				|| ! in_array( $source, array( self::SOURCE_CORE, self::SOURCE_CAMPAIGNBRIDGE ), true )
				|| ! str_starts_with( $name, $source . '/' )
				|| ! is_string( $semantics )
				|| ! is_array( $children )
				|| ! array_is_list( $children )
				|| array() !== array_filter( $children, static fn ( mixed $child ): bool => ! is_string( $child ) )
			) {
				throw new \DomainException( 'Email block contract contains a malformed block entry.' );
			}

			$blocks[ $name ] = array(
				'source'    => $source,
				'semantics' => $semantics,
				'children'  => $children,
			);
		}

		foreach ( $blocks as $block ) {
			foreach ( $block['children'] as $child ) {
				if ( ! isset( $blocks[ $child ] ) ) {
					throw new \DomainException( 'Email block contract names an unsupported child block.' );
				}
			}
		}

		if ( array() === $blocks ) {
			throw new \DomainException( 'Email block contract declares no blocks.' );
		}

		self::$blocks = $blocks;

		return $blocks;
	}
}
