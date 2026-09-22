<?php
/**
 * One bounded Core Social Icon link renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Social_Icon_Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Emits one linked raster icon and its plain-text URL. */
final class Social_Link_Renderer extends Abstract_Renderer {
	public const MAX_LABEL_LENGTH = 40;

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'core/social-link';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'service', 'url', 'label' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When a service, URL, or label is invalid.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();
		$service    = $attributes['service'] ?? null;
		$url        = $attributes['url'] ?? null;
		$label      = $attributes['label'] ?? null;

		if ( ! is_string( $service ) || null === Social_Icon_Assets::label( $service ) ) {
			throw new Invalid_Block_Attribute( 'service', 'must name a supported social network.' );
		}
		if ( ! is_string( $url ) || null === Renderer_Support::https_url( $url ) || 'https' !== strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ) || null !== parse_url( $url, PHP_URL_USER ) || null !== parse_url( $url, PHP_URL_PASS ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure renderer validates scheme and rejects userinfo without WordPress state.
			throw new Invalid_Block_Attribute( 'url', 'must be an absolute HTTPS URL.' );
		}
		if ( ! is_string( $label ) ) {
			throw new Invalid_Block_Attribute( 'label', 'must be text.' );
		}
		$label = trim( preg_replace( '/\s+/u', ' ', $label ) ?? '' );
		if ( '' === $label ) {
			$label = (string) Social_Icon_Assets::label( $service );
		}
		if ( self::MAX_LABEL_LENGTH < mb_strlen( $label )
			|| 1 === preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $label )
			|| 1 === preg_match( '/<[^>]*>/', $label ) ) {
			throw new Invalid_Block_Attribute( 'label', 'must be no more than 40 plain-text characters.' );
		}

		return $block->with_attributes(
			array(
				'service' => $service,
				'url'     => trim( $url ),
				'label'   => $label,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Render context.
	 */
	public function referenced_assets( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$binding = $this->parent_binding( $context );
		$url     = Social_Icon_Assets::url( $block->attributes()['service'] );
		if ( null === $url ) {
			return array();
		}

		return array(
			array(
				'type'   => 'image',
				'url'    => $url,
				'width'  => $binding['iconSize'],
				'height' => $binding['iconSize'],
				'alt'    => '',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Rendered child HTML.
	 * @param Render_Context $context  Render context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();
		$binding    = $this->parent_binding( $context );
		$icon_url   = (string) Social_Icon_Assets::url( $attributes['service'] );
		$target     = $binding['openInNewTab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
		$label      = $binding['showLabels']
			? '<span style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;vertical-align:middle">' . Renderer_Support::html( $attributes['label'] ) . '</span>'
			: '';

		return sprintf(
			'<td align="center" style="padding:0 %1$dpx"><a href="%2$s"%3$s aria-label="%4$s" style="color:#111111;font-family:Arial,Helvetica,sans-serif;text-decoration:none;white-space:nowrap"><img src="%5$s" width="%6$d" height="%6$d" alt="" role="presentation" border="0" style="display:inline-block;width:%6$dpx;height:%6$dpx;max-width:100%%;border:0;vertical-align:middle">%7$s</a></td>',
			(int) floor( $binding['gap'] / 2 ),
			Renderer_Support::html( $attributes['url'] ),
			$target,
			Renderer_Support::html( $attributes['label'] ),
			Renderer_Support::html( $icon_url ),
			$binding['iconSize'],
			$label
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Rendered child text.
	 * @param Render_Context $context  Render context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $block->attributes()['label'] . ': ' . $block->attributes()['url'] . "\n";
	}

	/**
	 * Read the required parent Social Icons scope.
	 *
	 * @param Render_Context $context Render context.
	 * @return array{align: string, gap: int, iconSize: int, openInNewTab: bool, showLabels: bool}
	 * @throws Invalid_Block_Attribute When the child is compiled outside its parent.
	 */
	private function parent_binding( Render_Context $context ): array {
		$binding = $context->binding( 'social_links' );
		if ( ! is_array( $binding )
			|| ! is_int( $binding['gap'] ?? null )
			|| ! is_int( $binding['iconSize'] ?? null )
			|| ! is_bool( $binding['openInNewTab'] ?? null )
			|| ! is_bool( $binding['showLabels'] ?? null )
			|| ! is_string( $binding['align'] ?? null ) ) {
			throw new Invalid_Block_Attribute( 'service', 'must be nested inside Core Social Icons.' );
		}

		return $binding;
	}
}
