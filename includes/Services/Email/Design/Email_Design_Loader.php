<?php
/**
 * Packaged email design loader.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Design;

use CampaignBridge\Domain\Email\Email_Design_Error;
use JsonException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Loads bounded repository-owned JSON without interpreting design policy. */
final class Email_Design_Loader {
	private const MAX_BYTES = 131072;

	/**
	 * Load the packaged manifest.
	 *
	 * @return array<string, mixed>
	 */
	public function manifest(): array {
		return $this->decode( dirname( __DIR__, 3 ) . '/Email_Design/email.json' );
	}

	/**
	 * Load the packaged v1 schema.
	 *
	 * @return array<string, mixed>
	 */
	public function schema(): array {
		return $this->decode( dirname( __DIR__, 3 ) . '/Email_Design/email-design-v1.schema.json' );
	}

	/**
	 * Decode one bounded local JSON file.
	 *
	 * @param string $path Absolute packaged path.
	 * @return array<string, mixed>
	 * @throws Email_Design_Error When the file is absent, unsafe, or invalid.
	 */
	private function decode( string $path ): array {
		$size = is_file( $path ) ? filesize( $path ) : false;
		if ( false === $size || 0 === $size || self::MAX_BYTES < $size ) {
			throw new Email_Design_Error( 'design.unsafe_value', '$', 'Packaged email design file is missing or exceeds its size limit.' );
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Fixed repository-owned local file.
		if ( false === $content ) {
			throw new Email_Design_Error( 'design.invalid_property', '$', 'Packaged email design file could not be read.' );
		}

		try {
			$value = json_decode( $content, true, 64, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new Email_Design_Error( 'design.invalid_property', '$', 'Packaged email design file contains invalid JSON.' );
		}
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new Email_Design_Error( 'design.invalid_property', '$', 'Packaged email design root must be an object.' );
		}
		return $value;
	}
}
