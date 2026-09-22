<?php
/**
 * Linked email video-poster renderer.
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

/** Renders a poster and explicit play callout without playable media markup. */
final class Video_Renderer extends Abstract_Renderer {
	public const MAX_ALT_LENGTH   = 200;
	public const MAX_LABEL_LENGTH = 80;
	public const MIN_WIDTH        = 160;
	public const MAX_WIDTH        = 900;
	public const MIN_HEIGHT       = 90;
	public const MAX_HEIGHT       = 1200;

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/video';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'posterUrl', 'posterAlt', 'videoUrl', 'label', 'width', 'height' );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return Email_Block_Contract::children( $this->block_name() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 * @throws Invalid_Block_Attribute When persisted poster data is unsafe or out of bounds.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();
		$poster_url = trim( Renderer_Support::string_attribute( $attributes, 'posterUrl', '' ) );
		$poster_alt = self::plain_text( $attributes, 'posterAlt', self::MAX_ALT_LENGTH );
		$video_url  = trim( Renderer_Support::string_attribute( $attributes, 'videoUrl', '' ) );
		$label      = self::plain_text( $attributes, 'label', self::MAX_LABEL_LENGTH );

		self::assert_https_url( $poster_url, 'posterUrl' );
		self::assert_https_url( $video_url, 'videoUrl' );

		return $block->with_attributes(
			array(
				'posterUrl' => $poster_url,
				'posterAlt' => $poster_alt,
				'videoUrl'  => $video_url,
				'label'     => $label,
				'width'     => Renderer_Support::integer_attribute( $attributes, 'width', 600, self::MIN_WIDTH, self::MAX_WIDTH ),
				'height'    => Renderer_Support::integer_attribute( $attributes, 'height', 338, self::MIN_HEIGHT, self::MAX_HEIGHT ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child HTML.
	 * @param Render_Context $context  Immutable context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();
		$kit        = Renderer_Support::brand_kit( $context );
		$background = $kit->color( Brand_Kit::SLOT_BRAND ) ?? '#1a6dcc';
		$text       = $kit->color( Brand_Kit::SLOT_ON_BRAND ) ?? '#ffffff';
		$font       = Renderer_Support::resolve_font( array(), $kit, 'button' )['family'];
		$url        = Renderer_Support::html( $attributes['videoUrl'] );

		$image   = sprintf(
			'<a href="%1$s" style="text-decoration:none"><img src="%2$s" width="%3$d" height="%4$d" alt="%5$s" border="0" style="display:block;width:100%%;max-width:%3$dpx;height:auto;border:0"></a>',
			$url,
			Renderer_Support::html( $attributes['posterUrl'] ),
			$attributes['width'],
			$attributes['height'],
			Renderer_Support::html( $attributes['posterAlt'] )
		);
		$callout = sprintf(
			'<a href="%1$s" aria-label="%2$s" style="display:block;padding:12px 16px;color:%3$s;font-family:%4$s;font-size:16px;line-height:20px;font-weight:bold;text-align:center;text-decoration:none"><span aria-hidden="true">&#9654;</span>&nbsp;%2$s</a>',
			$url,
			Renderer_Support::html( $attributes['label'] ),
			$text,
			$font
		);

		return sprintf(
			'<table role="presentation" width="%1$d" cellpadding="0" cellspacing="0" border="0" align="center" style="width:100%%;max-width:%1$dpx;border-collapse:collapse"><tr><td>%2$s</td></tr><tr><td bgcolor="%3$s" style="background-color:%3$s">%4$s</td></tr></table>',
			$attributes['width'],
			$image,
			$background,
			$callout
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();

		return $attributes['label'] . ': ' . $attributes['videoUrl'] . "\n";
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable context.
	 */
	public function referenced_assets( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();

		return array(
			array(
				'type'   => 'image',
				'url'    => $attributes['posterUrl'],
				'width'  => $attributes['width'],
				'height' => $attributes['height'],
				'alt'    => $attributes['posterAlt'],
			),
		);
	}

	/**
	 * Normalize one required bounded plain-text attribute.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param int                  $maximum    Maximum character count.
	 * @throws Invalid_Block_Attribute When the value is empty, markup, or too long.
	 */
	private static function plain_text( array $attributes, string $name, int $maximum ): string {
		$value = trim( preg_replace( '/\s+/u', ' ', Renderer_Support::string_attribute( $attributes, $name, '' ) ) ?? '' );
		if ( '' === $value || $maximum < mb_strlen( $value ) || 1 === preg_match( '/<[^>]*>/', $value ) ) {
			throw new Invalid_Block_Attribute( $name, sprintf( 'must be one through %d plain-text characters.', $maximum ) );
		}

		return $value;
	}

	/**
	 * Require an absolute HTTPS URL without embedded credentials.
	 *
	 * @param string $url       Candidate URL.
	 * @param string $attribute Stable attribute name for diagnostics.
	 * @throws Invalid_Block_Attribute When the URL is not safe email input.
	 */
	private static function assert_https_url( string $url, string $attribute ): void {
		if (
			null === Renderer_Support::https_url( $url )
			|| 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) )
			|| null !== wp_parse_url( $url, PHP_URL_USER )
			|| null !== wp_parse_url( $url, PHP_URL_PASS )
		) {
			throw new Invalid_Block_Attribute( $attribute, 'must be an absolute HTTPS URL.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Attribute comes from a private bounded caller.
		}
	}
}
