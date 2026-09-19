<?php
/**
 * Synthetic personalization for compiled email previews.
 *
 * @package CampaignBridge
 * @since   1.0.0
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email\Token;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a clearly labeled sample view of a compiled artifact.
 *
 * Provider-resolved tokens stay canonical in the artifact, so a preview of the
 * artifact alone shows raw `{{cb:...}}` text. This class derives a separate
 * sample view from a successful artifact by applying each token's preview
 * behavior:
 *
 * - `sample`: replaced by a fixed synthetic value from SAMPLE_VALUES;
 * - `omit`: removed, so no realistic-looking private value is ever shown;
 * - `literal`: left as the canonical token.
 *
 * The artifact itself is never changed: preview, source, download, and review
 * fingerprints keep using the canonical output. Sample values are static
 * constants; nothing here reads subscriber data, options, or providers.
 * Replacement is exact because compiler provenance guarantees every token left
 * in a successful artifact is a registered, authored canonical token.
 */
final class Token_Preview {

	/**
	 * Synthetic values for provider-resolved tokens that preview as `sample`.
	 *
	 * URLs use the reserved example.com domain so a sample link can never
	 * reach a real subscriber or provider endpoint.
	 *
	 * @var array<string, string>
	 */
	public const SAMPLE_VALUES = array(
		'cb:subscriber.first_name'    => 'Alex',
		'cb:subscriber.last_name'     => 'Sample',
		'cb:campaign.view_online_url' => 'https://example.com/campaignbridge-preview/view-online',
		'cb:campaign.unsubscribe_url' => 'https://example.com/campaignbridge-preview/unsubscribe',
	);

	/**
	 * Canonical expression => plain-text preview value.
	 *
	 * @var array<string, string>
	 */
	private array $replacements = array();

	/**
	 * Build the preview map for a registry.
	 *
	 * @param Token_Registry $registry Token vocabulary.
	 *
	 * @throws \LogicException When a sample-previewed provider token has no sample.
	 */
	public function __construct( Token_Registry $registry ) {
		foreach ( $registry->all() as $definition ) {
			if ( ! $definition->requires_provider_resolution() ) {
				continue;
			}

			$expression = '{{' . $definition->get_id() . '}}';
			switch ( $definition->get_preview_behavior() ) {
				case Token_Definition::PREVIEW_SAMPLE:
					if ( ! isset( self::SAMPLE_VALUES[ $definition->get_id() ] ) ) {
						throw new \LogicException( sprintf( 'Token %s previews as a sample but has no sample value.', $definition->get_id() ) );
					}
					$this->replacements[ $expression ] = self::SAMPLE_VALUES[ $definition->get_id() ];
					break;
				case Token_Definition::PREVIEW_OMIT:
					$this->replacements[ $expression ] = '';
					break;
			}
		}
	}

	/**
	 * Create the preview for the default v1 registry.
	 *
	 * @return static
	 */
	public static function default(): self {
		return new self( Token_Registry::default() );
	}

	/**
	 * Check whether an artifact contains a token this preview replaces.
	 *
	 * @param string $artifact Compiled HTML or plain text.
	 *
	 * @return bool
	 */
	public function applies_to( string $artifact ): bool {
		foreach ( array_keys( $this->replacements ) as $expression ) {
			if ( str_contains( $artifact, $expression ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Derive the sample view of a compiled HTML artifact.
	 *
	 * Values are HTML-escaped, which is correct for both text content and the
	 * quoted `href` attributes where URL tokens appear.
	 *
	 * @param string $html Compiled HTML artifact.
	 *
	 * @return string
	 */
	public function html( string $html ): string {
		return strtr(
			$html,
			array_map(
				static fn ( string $value ): string => htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' ),
				$this->replacements
			)
		);
	}

	/**
	 * Derive the sample view of a compiled plain-text artifact.
	 *
	 * @param string $text Compiled plain-text artifact.
	 *
	 * @return string
	 */
	public function text( string $text ): string {
		return strtr( $text, $this->replacements );
	}
}
