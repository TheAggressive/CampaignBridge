<?php
/**
 * Bounded email navigation renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Email_Block_Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders explicit links without reading WordPress menus or frontend markup. */
final class Navigation_Renderer extends Abstract_Renderer {
	public const MAX_ITEMS        = 5;
	public const MAX_LABEL_LENGTH = 24;

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/navigation';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'items' );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return Email_Block_Contract::children( $this->block_name() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 * @throws Invalid_Block_Attribute When a link is malformed or out of bounds.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$items = $block->attributes()['items'] ?? null;
		if ( ! is_array( $items ) || ! array_is_list( $items ) || 1 > count( $items ) || self::MAX_ITEMS < count( $items ) ) {
			throw new Invalid_Block_Attribute( 'items', 'must contain one through five links.' );
		}

		$normalized = array();
		foreach ( $items as $index => $item ) {
			$path = 'items.' . $index;
			if ( ! is_array( $item ) || array() !== array_diff( array_keys( $item ), array( 'label', 'url' ) ) ) {
				throw new Invalid_Block_Attribute( $path, 'must contain only a label and URL.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic path is generated from the bounded index.
			}

			$label = $item['label'] ?? null;
			$url   = $item['url'] ?? null;
			if ( ! is_string( $label ) ) {
				throw new Invalid_Block_Attribute( $path . '.label', 'must be text.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic path is generated from the bounded index.
			}
			$label = trim( preg_replace( '/\s+/u', ' ', $label ) ?? '' );
			if ( '' === $label || self::MAX_LABEL_LENGTH < mb_strlen( $label ) || 1 === preg_match( '/<[^>]*>/', $label ) ) {
				throw new Invalid_Block_Attribute( $path . '.label', 'must be one through 24 plain-text characters.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic path is generated from the bounded index.
			}
			if ( ! is_string( $url ) || null === Renderer_Support::https_url( $url ) || 'https' !== strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ) || null !== parse_url( $url, PHP_URL_USER ) || null !== parse_url( $url, PHP_URL_PASS ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure renderer validates the scheme and rejects userinfo without WordPress state.
				throw new Invalid_Block_Attribute( $path . '.url', 'must be an absolute HTTPS URL.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic path is generated from the bounded index.
			}
			$normalized[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $block->with_attributes( array( 'items' => $normalized ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child HTML.
	 * @param Render_Context $context  Immutable context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$kit   = Renderer_Support::brand_kit( $context );
		$color = $kit->color( Brand_Kit::SLOT_TEXT ) ?? '#111111';
		$font  = Renderer_Support::resolve_font( array(), $kit )['family'];
		$cells = array();
		$width = (string) intdiv( 100, count( $block->attributes()['items'] ) ) . '%';
		foreach ( $block->attributes()['items'] as $item ) {
			$cells[] = sprintf(
				'<td class="cb-nav-item" width="%5$s" align="center" style="width:%5$s;padding:8px 12px;text-align:center;overflow-wrap:break-word"><a href="%1$s" style="color:%3$s;font-family:%4$s;font-size:14px;line-height:20px;text-decoration:underline">%2$s</a></td>',
				Renderer_Support::html( $item['url'] ),
				Renderer_Support::html( $item['label'] ),
				$color,
				$font,
				$width
			);
		}

		return '<style>@media only screen and (max-width:480px){.cb-nav>tbody>tr>.cb-nav-item{display:block!important;width:100%!important;box-sizing:border-box}}</style><div role="navigation" aria-label="Email navigation"><table role="presentation" class="cb-nav" width="100%" cellpadding="0" cellspacing="0" border="0" align="center" style="width:100%;border-collapse:collapse"><tr>' . implode( '', $cells ) . '</tr></table></div>';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$lines = array();
		foreach ( $block->attributes()['items'] as $item ) {
			$lines[] = $item['label'] . ': ' . $item['url'];
		}

		return implode( "\n", $lines ) . "\n";
	}
}
