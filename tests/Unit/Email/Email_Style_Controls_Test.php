<?php
/**
 * Native border, corner radius, margin, and padding controls in compiled email.
 *
 * Fixtures are real Core and CampaignBridge block serialization, compiled with
 * the packaged email design, so design defaults and authored values meet
 * exactly as they do for a saved template.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Email_Style_Controls_Test extends TestCase {
	private const LINK = '<a class="wp-block-button__link wp-element-button" href="https://example.com/go">Go now</a>';

	public function test_buttons_default_to_the_design_pill_in_html_and_outlook(): void {
		$html = $this->html( $this->button( '' ) );

		self::assertStringContainsString( '<td style="border-radius:999px;background-color:#1a6dcc">', $html );
		self::assertStringContainsString( 'padding:12px 24px 12px 24px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;line-height:20px;color:#ffffff;text-decoration:none;border-radius:999px', $html );
		self::assertStringContainsString( 'style="height:44px;v-text-anchor:middle;width:106px" arcsize="50%" stroke="f" fillcolor="#1a6dcc"', $html );
	}

	public function test_button_radius_border_padding_and_size_shape_both_renderings(): void {
		$html = $this->html( $this->button( ' {"fontSize":"large","style":{"border":{"radius":"6px","width":"2px","style":"dashed","color":"#222222"},"spacing":{"padding":{"top":"8px","right":"16px","bottom":"8px","left":"16px"}}}}' ) );

		self::assertStringContainsString( '<td style="border-radius:6px;background-color:#1a6dcc;border:2px dashed #222222">', $html );
		self::assertStringContainsString( 'padding:8px 16px 8px 16px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;line-height:25px', $html );
		// 8 + 8 padding, 25 line height, and 2px border on each edge.
		self::assertStringContainsString( 'style="height:45px;v-text-anchor:middle;width:108px" arcsize="13%" stroke="t" strokecolor="#222222" strokeweight="2px" fillcolor="#1a6dcc"><v:stroke dashstyle="dash"/>', $html );
	}

	public function test_unset_corners_and_sides_fall_back_to_the_design(): void {
		$html = $this->html( $this->button( ' {"style":{"border":{"radius":{"topLeft":"4px"}},"spacing":{"padding":{"left":"40px"}}}}' ) );

		self::assertStringContainsString( 'border-radius:4px 999px 999px 999px', $html );
		self::assertStringContainsString( 'padding:12px 24px 12px 40px', $html );
	}

	public function test_per_corner_radius_uses_the_largest_corner_in_outlook(): void {
		$html = $this->html( $this->button( ' {"style":{"border":{"radius":{"topLeft":"0px","topRight":"20px","bottomRight":"0px","bottomLeft":"20px"}}}}' ) );

		self::assertStringContainsString( 'border-radius:0px 20px 0px 20px', $html );
		self::assertStringContainsString( 'arcsize="45%"', $html );
	}

	public function test_a_square_button_has_no_radius(): void {
		$html = $this->html( $this->button( ' {"style":{"border":{"radius":"0px"}}}' ) );

		self::assertStringContainsString( '<td style="background-color:#1a6dcc">', $html );
		self::assertStringContainsString( 'arcsize="0%"', $html );
		self::assertStringNotContainsString( 'border-radius', $html );
	}

	public function test_palette_border_colour_resolves_through_the_email_palette(): void {
		$html = $this->html( $this->button( ' {"borderColor":"border","style":{"border":{"width":"1px"}}}' ) );

		self::assertStringContainsString( 'border:1px solid #e0e0e0', $html );
		self::assertStringContainsString( 'strokecolor="#e0e0e0" strokeweight="1px"', $html );
	}

	public function test_outline_buttons_keep_cores_two_pixel_outline_unless_a_border_is_authored(): void {
		$outline  = $this->html( $this->button( ' {"className":"is-style-outline"}', '<div class="wp-block-button is-style-outline">' ) );
		$authored = $this->html( $this->button( ' {"className":"is-style-outline","style":{"border":{"width":"4px","color":"#111111"}}}', '<div class="wp-block-button is-style-outline">' ) );

		self::assertStringContainsString( 'background-color:transparent;border:2px solid #1a6dcc', $outline );
		self::assertStringContainsString( 'background-color:transparent;border:4px solid #111111', $authored );
	}

	public function test_images_take_borders_and_rounded_corners(): void {
		$image   = static fn ( string $attributes ): string => '<!-- wp:image {"width":"300px"' . $attributes . '} --><figure class="wp-block-image is-resized"><img src="https://example.com/a.jpg" alt="A" style="width:300px"/></figure><!-- /wp:image -->';
		$rounded = $this->html( $image( ',"style":{"border":{"radius":"12px","width":"1px","color":"#e0e0e0"}}' ) );
		$sides   = $this->html( $image( ',"style":{"border":{"top":{"width":"2px","color":"#111111"},"bottom":{"width":"2px","color":"#111111"}}}' ) );
		$plain   = $this->html( $image( '' ) );

		self::assertStringContainsString( 'max-width:300px;height:auto;border:1px solid #e0e0e0;border-radius:12px', $rounded );
		self::assertStringContainsString( 'max-width:300px;height:auto;border-top:2px solid #111111;border-bottom:2px solid #111111', $sides );
		self::assertStringContainsString( 'max-width:300px;height:auto;border:0', $plain );
	}

	public function test_sections_take_borders_and_rounded_corners(): void {
		$html = $this->html_document( '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section {"style":{"border":{"radius":"8px","width":"1px","color":"#e0e0e0"}}} --><!-- wp:paragraph --><p>Boxed</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->' );

		self::assertStringContainsString( 'style="width:100%;border-collapse:separate;background-color:#ffffff;border:1px solid #e0e0e0;border-radius:8px"', $html );
	}

	public function test_columns_take_padding_borders_and_rounded_corners(): void {
		$html = $this->html( '<!-- wp:campaignbridge/columns --><!-- wp:campaignbridge/column {"style":{"spacing":{"padding":{"top":"16px","right":"16px","bottom":"16px","left":"16px"}},"border":{"radius":"8px","width":"1px","color":"#e0e0e0"}}} --><!-- wp:paragraph --><p>Card</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/column --><!-- /wp:campaignbridge/columns -->' );

		self::assertStringContainsString( '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:separate;border:1px solid #e0e0e0;border-radius:8px"><tr><td style="padding:16px 16px 16px 16px">', $html );
	}

	public function test_headings_take_padding_and_a_background(): void {
		$html = $this->html( '<!-- wp:heading {"backgroundColor":"card","style":{"spacing":{"padding":{"top":"8px","right":"12px","bottom":"8px","left":"12px"}}}} --><h2 class="wp-block-heading has-card-background-color has-background">Boxed</h2><!-- /wp:heading -->' );

		self::assertStringContainsString( ';padding:8px 12px 8px 12px;background-color:#f4f4f4', $html );
	}

	public function test_lists_take_margin_and_padding(): void {
		$html = $this->html( '<!-- wp:list {"style":{"spacing":{"margin":{"top":"16px"},"padding":{"left":"32px"}}}} --><ul class="wp-block-list" style="margin-top:16px;padding-left:32px"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --></ul><!-- /wp:list -->' );

		self::assertStringContainsString( '<td style="padding:16px 0px 0px 0px"><ul style="margin:0;padding:0 0 0 32px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#111111">', $html );
	}

	public function test_separators_social_icons_and_button_groups_take_a_margin(): void {
		$separator = $this->html( '<!-- wp:separator {"style":{"spacing":{"margin":{"top":"24px","bottom":"24px"}}}} --><hr class="wp-block-separator has-alpha-channel-opacity" style="margin-top:24px;margin-bottom:24px"/><!-- /wp:separator -->' );
		$social    = $this->html( '<!-- wp:social-links {"style":{"spacing":{"margin":{"top":"8px"}}}} --><ul class="wp-block-social-links"><!-- wp:social-link {"url":"https://instagram.com/example","service":"instagram"} /--></ul><!-- /wp:social-links -->' );
		$group     = $this->html( '<!-- wp:buttons {"style":{"spacing":{"margin":{"bottom":"32px"}}}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button">' . self::LINK . '</div><!-- /wp:button --></div><!-- /wp:buttons -->' );

		self::assertStringContainsString( '<td style="padding:24px 0px 24px 0px"><table role="presentation" width="100%" align="center"', $separator );
		self::assertStringContainsString( '<td style="padding:8px 0px 0px 0px"><div role="navigation" aria-label="Social links">', $social );
		self::assertStringContainsString( '<td style="padding:0px 0px 32px 0px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="left"><!--[if mso]>', $group );
	}

	public function test_blocks_without_border_support_reject_a_border(): void {
		$result = $this->compile( '<!-- wp:paragraph {"style":{"border":{"width":"1px"}}} --><p style="border-width:1px">Boxed</p><!-- /wp:paragraph -->' );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
	}

	public function test_unknown_spacing_sides_are_rejected_instead_of_ignored(): void {
		$result = $this->compile( '<!-- wp:list {"style":{"spacing":{"margin":{"middle":"8px"}}}} --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --></ul><!-- /wp:list -->' );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertStringEndsWith( 'attrs.style.spacing.margin.middle', $result->diagnostics()[0]->path() );
	}

	/** Serialized Core button group around one button. */
	private function button( string $attributes, string $wrapper = '<div class="wp-block-button">' ): string {
		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button' . $attributes . ' -->' . $wrapper . self::LINK . '</div><!-- /wp:button --></div><!-- /wp:buttons -->';
	}

	/** Compiled HTML of markup placed in one section, which must compile cleanly. */
	private function html( string $markup ): string {
		$result = $this->compile( $markup );
		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );

		return $result->html();
	}

	/** Compiled HTML of a whole document, which must compile cleanly. */
	private function html_document( string $serialized ): string {
		$result = Compiler_Factory::create()->compile( parse_blocks( $serialized ), $this->context() );
		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );

		return $result->html();
	}

	private function compile( string $markup ): Compile_Result {
		return Compiler_Factory::create()->compile(
			parse_blocks( '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->' . $markup . '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->' ),
			$this->context()
		);
	}

	private function context(): Render_Context {
		return new Render_Context(
			array(
				'title'    => 'Style controls',
				'language' => 'en',
			),
			array(),
			array(),
			'universal@1'
		);
	}

	private function diagnostics( Compile_Result $result ): string {
		return implode(
			'; ',
			array_map(
				static fn ( $diagnostic ): string => $diagnostic->code() . ' @ ' . $diagnostic->path() . ': ' . $diagnostic->to_array()['message'],
				$result->diagnostics()
			)
		);
	}
}
