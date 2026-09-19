<?php
/**
 * WordPress Core authoring block normalization and compilation tests.
 *
 * Every fixture is real Core block serialization parsed by parse_blocks(), so
 * values Core sources from saved markup (paragraph content, image URL/alt/link,
 * button label/URL) travel through the known serialization contract.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Core_Block_Normalization_Test extends TestCase {
	private const DOCUMENT = '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
		. '<!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"center"}}} --><h1 class="wp-block-heading has-text-align-center">Core <em>authoring</em></h1><!-- /wp:heading -->'
		. '<!-- wp:paragraph --><p>Hello <strong>bold</strong>, <em>italic</em>, <s>struck</s> and <a href="https://example.com/docs" data-type="URL" data-id="https://example.com/docs">docs</a>.<br>Next line</p><!-- /wp:paragraph -->'
		. '<!-- wp:image {"id":42,"width":"320px","height":"auto","sizeSlug":"large","linkDestination":"custom","align":"center"} --><figure class="wp-block-image aligncenter size-large is-resized"><a href="https://example.com/story"><img src="https://example.com/wp-content/uploads/hero.jpg" alt="Hero &amp; friends" class="wp-image-42" style="width:320px;height:auto"/></a></figure><!-- /wp:image -->'
		. '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"brand","textColor":"on-brand"} --><div class="wp-block-button"><a class="wp-block-button__link has-on-brand-color has-brand-background-color has-text-color has-background wp-element-button" href="https://example.com/action">Take action</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>First</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Second</strong> item</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
		. '<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li>Two</li><!-- /wp:list-item --></ol><!-- /wp:list -->'
		. '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->'
		. '<!-- wp:spacer {"height":"48px"} --><div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->'
		. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

	public function test_core_document_compiles_to_golden_html_and_text(): void {
		$result = $this->compile_document( self::DOCUMENT );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertSame( $this->fixture( 'core-blocks.html' ), $result->html() );
		self::assertSame( $this->fixture( 'core-blocks.txt' ), $result->text() );
	}

	public function test_identical_core_input_produces_identical_artifacts(): void {
		$first  = $this->compile_document( self::DOCUMENT );
		$second = $this->compile_document( self::DOCUMENT );

		self::assertTrue( $first->is_success(), $this->diagnostics( $first ) );
		self::assertSame( $first->html(), $second->html() );
		self::assertSame( $first->text(), $second->text() );
		self::assertSame( $first->fingerprint(), $second->fingerprint() );
	}

	public function test_compilation_does_not_depend_on_frontend_block_rendering(): void {
		$calls  = 0;
		$filter = static function ( string $content ) use ( &$calls ): string {
			++$calls;

			return $content . '<div class="theme-frontend-marker"></div>';
		};
		add_filter( 'render_block', $filter );
		add_filter( 'the_content', $filter );

		try {
			$result = $this->compile_document( self::DOCUMENT );
		} finally {
			remove_filter( 'render_block', $filter );
			remove_filter( 'the_content', $filter );
		}

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertSame( 0, $calls );
		self::assertStringNotContainsString( 'theme-frontend-marker', $result->html() );
		self::assertStringNotContainsString( 'wp-block-', $result->html() );
		self::assertStringNotContainsString( 'wp-element-button', $result->html() );
	}

	public function test_paragraph_normalizes_rich_text_links_and_alignment(): void {
		$result = $this->compile( '<!-- wp:paragraph {"style":{"typography":{"textAlign":"right"}}} --><p class="has-text-align-right">Go <a href="https://example.com/a" target="_blank" rel="noreferrer noopener">there</a></p><!-- /wp:paragraph -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '<p align="right"', $result->html() );
		self::assertStringContainsString( 'text-align:right', $result->html() );
		self::assertStringContainsString( 'Go <a href="https://example.com/a" style="color:inherit;text-decoration:underline" target="_blank" rel="noopener noreferrer">there</a></p>', $result->html() );
		self::assertSame( "Go there (https://example.com/a)\n", $result->text() );
	}

	public function test_paragraph_supported_styles_reach_inline_css(): void {
		$result = $this->compile( '<!-- wp:paragraph {"backgroundColor":"brand","textColor":"on-brand","style":{"typography":{"fontSize":"20px","lineHeight":"1.4"},"spacing":{"padding":{"top":"8px","right":"8px","bottom":"8px","left":"8px"}}}} --><p class="has-on-brand-color has-brand-background-color has-text-color has-background" style="padding:8px;font-size:20px;line-height:1.4">Styled</p><!-- /wp:paragraph -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'font-size:20px', $result->html() );
		self::assertStringContainsString( 'line-height:1.4', $result->html() );
		self::assertStringContainsString( 'color:#ffffff', $result->html() );
		self::assertStringNotContainsString( 'has-text-color', $result->html() );
	}

	/**
	 * @dataProvider unsafe_paragraphs
	 */
	public function test_paragraph_rejects_unsafe_or_unsupported_markup( string $markup, string $code ): void {
		$result = $this->compile( $markup );

		self::assertFalse( $result->is_success() );
		self::assertSame( $code, $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
		self::assertSame( '', $result->text() );
	}

	/** @return array<string, array{string, string}> */
	public static function unsafe_paragraphs(): array {
		return array(
			'script link'       => array( '<!-- wp:paragraph --><p><a href="javascript:alert(1)">x</a></p><!-- /wp:paragraph -->', 'text.content.invalid' ),
			'event handler'     => array( '<!-- wp:paragraph --><p><strong onclick="alert(1)">x</strong></p><!-- /wp:paragraph -->', 'text.content.invalid' ),
			'inline image'      => array( '<!-- wp:paragraph --><p>Look <img src="https://example.com/x.png" alt=""></p><!-- /wp:paragraph -->', 'text.content.invalid' ),
			'highlight format'  => array( '<!-- wp:paragraph --><p><mark style="background-color:#ff0" class="has-inline-color">x</mark></p><!-- /wp:paragraph -->', 'text.content.invalid' ),
			'unknown link attr' => array( '<!-- wp:paragraph --><p><a href="https://example.com" onclick="x()">x</a></p><!-- /wp:paragraph -->', 'text.content.invalid' ),
			'wrong wrapper'     => array( '<!-- wp:paragraph --><div>x</div><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'drop cap'          => array( '<!-- wp:paragraph {"dropCap":true} --><p class="has-drop-cap">x</p><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'custom class'      => array( '<!-- wp:paragraph {"className":"hero-copy"} --><p class="hero-copy">x</p><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'wide alignment'    => array( '<!-- wp:paragraph {"align":"wide"} --><p class="alignwide">x</p><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'justify text'      => array( '<!-- wp:paragraph {"style":{"typography":{"textAlign":"justify"}}} --><p class="has-text-align-justify">x</p><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'letter spacing'    => array( '<!-- wp:paragraph {"style":{"typography":{"letterSpacing":"2px"}}} --><p style="letter-spacing:2px">x</p><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'border radius'     => array( '<!-- wp:paragraph {"style":{"border":{"radius":"4px"}}} --><p style="border-radius:4px">x</p><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'gradient'          => array( '<!-- wp:paragraph {"gradient":"vivid"} --><p class="has-vivid-gradient-background">x</p><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'block bindings'    => array( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta"}}}} --><p>x</p><!-- /wp:paragraph -->', 'block.attribute.invalid' ),
			'empty'             => array( '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->', 'text.content.empty' ),
		);
	}

	public function test_paragraph_accepts_editor_only_metadata(): void {
		$result = $this->compile( '<!-- wp:paragraph {"metadata":{"name":"Intro"},"lock":{"move":true}} --><p>Named</p><!-- /wp:paragraph -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '>Named</p>', $result->html() );
	}

	/**
	 * @dataProvider heading_levels
	 */
	public function test_heading_levels_follow_core_serialization( string $markup, string $tag ): void {
		$result = $this->compile( $markup );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '<' . $tag . ' align="left"', $result->html() );
		self::assertStringContainsString( '>Title</' . $tag . '>', $result->html() );
	}

	/** @return array<string, array{string, string}> */
	public static function heading_levels(): array {
		return array(
			'default level 2' => array( '<!-- wp:heading --><h2 class="wp-block-heading">Title</h2><!-- /wp:heading -->', 'h2' ),
			'level 1'         => array( '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Title</h1><!-- /wp:heading -->', 'h1' ),
			'level 3'         => array( '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Title</h3><!-- /wp:heading -->', 'h3' ),
			'level 4'         => array( '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Title</h4><!-- /wp:heading -->', 'h4' ),
		);
	}

	public function test_heading_supported_styles_and_alignment(): void {
		$result = $this->compile( '<!-- wp:heading {"textColor":"brand","style":{"typography":{"textAlign":"center","fontWeight":"400"}}} --><h2 class="wp-block-heading has-text-align-center has-brand-color has-text-color" style="font-weight:400">Styled</h2><!-- /wp:heading -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '<h2 align="center"', $result->html() );
		self::assertStringContainsString( 'text-align:center', $result->html() );
		self::assertStringContainsString( 'font-weight:400', $result->html() );
		self::assertStringContainsString( 'color:#1a6dcc', $result->html() );
	}

	/**
	 * @dataProvider invalid_headings
	 */
	public function test_heading_rejects_invalid_values( string $markup, string $code ): void {
		$result = $this->compile( $markup );

		self::assertFalse( $result->is_success() );
		self::assertSame( $code, $result->diagnostics()[0]->code() );
	}

	/** @return array<string, array{string, string}> */
	public static function invalid_headings(): array {
		return array(
			'level 5 is outside email grammar' => array( '<!-- wp:heading {"level":5} --><h5 class="wp-block-heading">x</h5><!-- /wp:heading -->', 'block.attribute.invalid' ),
			'level and markup disagree'        => array( '<!-- wp:heading {"level":3} --><h2 class="wp-block-heading">x</h2><!-- /wp:heading -->', 'block.attribute.invalid' ),
			'non-integer level'                => array( '<!-- wp:heading {"level":"2"} --><h2 class="wp-block-heading">x</h2><!-- /wp:heading -->', 'block.attribute.invalid' ),
			'background colour'                => array( '<!-- wp:heading {"backgroundColor":"brand"} --><h2 class="wp-block-heading has-brand-background-color has-background">x</h2><!-- /wp:heading -->', 'block.attribute.invalid' ),
			'unsafe markup'                    => array( '<!-- wp:heading --><h2 class="wp-block-heading"><span style="color:red">x</span></h2><!-- /wp:heading -->', 'heading.content.invalid' ),
			'empty'                            => array( '<!-- wp:heading --><h2 class="wp-block-heading"></h2><!-- /wp:heading -->', 'heading.content.empty' ),
		);
	}

	public function test_media_library_image_uses_the_saved_url_alt_and_intrinsic_scaling(): void {
		$result = $this->compile( '<!-- wp:image {"id":42,"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/photo-1024x512.jpg" alt="A photo" class="wp-image-42"/></figure><!-- /wp:image -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '<img src="https://example.com/wp-content/uploads/photo-1024x512.jpg" width="600" alt="A photo" border="0" style="display:block;width:100%;max-width:600px;height:auto;border:0">', $result->html() );
		self::assertStringNotContainsString( 'wp-image-42', $result->html() );
		self::assertSame(
			array(
				array(
					'type'   => 'image',
					'url'    => 'https://example.com/wp-content/uploads/photo-1024x512.jpg',
					'width'  => 600,
					'height' => null,
					'alt'    => 'A photo',
				),
			),
			$result->assets()
		);
	}

	public function test_external_image_with_dimensions_and_link(): void {
		$result = $this->compile( '<!-- wp:image {"width":"320px","height":"180px","linkDestination":"custom"} --><figure class="wp-block-image is-resized"><a href="https://example.com/story"><img src="https://cdn.example.net/banner.png" alt="Banner" style="width:320px;height:180px"/></a></figure><!-- /wp:image -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '<a href="https://example.com/story" style="text-decoration:none"><img src="https://cdn.example.net/banner.png" width="320" height="180" alt="Banner"', $result->html() );
		self::assertSame( "https://example.com/story\n", $result->text() );
	}

	public function test_decorative_image_renders_empty_alt(): void {
		$result = $this->compile( '<!-- wp:image {"isDecorative":true} --><figure class="wp-block-image"><img src="https://example.com/rule.png" alt=""/></figure><!-- /wp:image -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'alt="" role="presentation"', $result->html() );
	}

	public function test_image_alignment_renders_an_aligned_cell(): void {
		$result = $this->compile( '<!-- wp:image {"align":"right"} --><figure class="wp-block-image alignright"><img src="https://example.com/a.png" alt="A"/></figure><!-- /wp:image -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '<td align="right"><img src="https://example.com/a.png"', $result->html() );
		self::assertStringContainsString( 'margin:0 0 0 auto', $result->html() );
	}

	/**
	 * @dataProvider invalid_images
	 */
	public function test_image_rejects_unsafe_or_unsupported_values( string $markup, string $code ): void {
		$result = $this->compile( $markup );

		self::assertFalse( $result->is_success() );
		self::assertSame( $code, $result->diagnostics()[0]->code() );
		self::assertSame( array(), $result->assets() );
	}

	/** @return array<string, array{string, string}> */
	public static function invalid_images(): array {
		return array(
			'missing alt'       => array( '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.com/a.png" alt=""/></figure><!-- /wp:image -->', 'image.alt.missing' ),
			'script url'        => array( '<!-- wp:image --><figure class="wp-block-image"><img src="javascript:alert(1)" alt="A"/></figure><!-- /wp:image -->', 'image.url.invalid' ),
			'data url'          => array( '<!-- wp:image --><figure class="wp-block-image"><img src="data:image/png;base64,AAAA" alt="A"/></figure><!-- /wp:image -->', 'image.url.invalid' ),
			'relative url'      => array( '<!-- wp:image --><figure class="wp-block-image"><img src="/wp-content/uploads/a.png" alt="A"/></figure><!-- /wp:image -->', 'image.url.invalid' ),
			'unsafe link'       => array( '<!-- wp:image {"linkDestination":"custom"} --><figure class="wp-block-image"><a href="javascript:alert(1)"><img src="https://example.com/a.png" alt="A"/></a></figure><!-- /wp:image -->', 'image.link.invalid' ),
			'caption'           => array( '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.com/a.png" alt="A"/><figcaption class="wp-element-caption">Caption</figcaption></figure><!-- /wp:image -->', 'block.attribute.invalid' ),
			'percentage width'  => array( '<!-- wp:image {"width":"50%"} --><figure class="wp-block-image is-resized"><img src="https://example.com/a.png" alt="A" style="width:50%"/></figure><!-- /wp:image -->', 'block.attribute.invalid' ),
			'oversized width'   => array( '<!-- wp:image {"width":"5000px"} --><figure class="wp-block-image is-resized"><img src="https://example.com/a.png" alt="A" style="width:5000px"/></figure><!-- /wp:image -->', 'block.attribute.invalid' ),
			'cropped ratio'     => array( '<!-- wp:image {"aspectRatio":"16/9","scale":"cover"} --><figure class="wp-block-image"><img src="https://example.com/a.png" alt="A" style="aspect-ratio:16/9;object-fit:cover"/></figure><!-- /wp:image -->', 'block.attribute.invalid' ),
			'rounded style'     => array( '<!-- wp:image {"className":"is-style-rounded"} --><figure class="wp-block-image is-style-rounded"><img src="https://example.com/a.png" alt="A"/></figure><!-- /wp:image -->', 'block.attribute.invalid' ),
			'wide alignment'    => array( '<!-- wp:image {"align":"wide"} --><figure class="wp-block-image alignwide"><img src="https://example.com/a.png" alt="A"/></figure><!-- /wp:image -->', 'block.attribute.invalid' ),
			'duotone filter'    => array( '<!-- wp:image {"style":{"color":{"duotone":["#000","#fff"]}}} --><figure class="wp-block-image"><img src="https://example.com/a.png" alt="A"/></figure><!-- /wp:image -->', 'block.attribute.invalid' ),
			'unknown markup'    => array( '<!-- wp:image --><div class="wp-block-image"><img src="https://example.com/a.png" alt="A"/></div><!-- /wp:image -->', 'block.attribute.invalid' ),
			'upload in flight'  => array( '<!-- wp:image {"blob":"blob:https://example.com/1"} --><figure class="wp-block-image"><img src="https://example.com/a.png" alt="A"/></figure><!-- /wp:image -->', 'block.attribute.invalid' ),
		);
	}

	public function test_buttons_group_aligns_bulletproof_core_buttons(): void {
		$result = $this->compile( '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"right"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com/a">First</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline","style":{"color":{"background":"#123456"}}} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-background wp-element-button" href="https://example.com/b" style="background-color:#123456">Second</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertSame( 2, substr_count( $result->html(), '<td align="right"><!--[if mso]><v:roundrect' ) );
		self::assertStringContainsString( 'href="https://example.com/a" style="height:44px;v-text-anchor:middle;width:200px"', $result->html() );
		self::assertStringContainsString( 'border:2px solid #123456', $result->html() );
		self::assertSame( "First: https://example.com/a\nSecond: https://example.com/b\n", $result->text() );
	}

	public function test_button_label_is_plain_text_from_rich_text(): void {
		$result = $this->compile( '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com/a" target="_blank" rel="noreferrer noopener"><strong>Buy</strong> &amp; save</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '>Buy &amp; save</a>', $result->html() );
		self::assertStringNotContainsString( '<strong>Buy', $result->html() );
		self::assertStringContainsString( '<td align="left">', $result->html() );
	}

	/**
	 * @dataProvider invalid_buttons
	 */
	public function test_button_rejects_invalid_values( string $markup, string $code ): void {
		$result = $this->compile( $markup );

		self::assertFalse( $result->is_success() );
		self::assertSame( $code, $result->diagnostics()[0]->code() );
	}

	/** @return array<string, array{string, string}> */
	public static function invalid_buttons(): array {
		$button = static fn ( string $attributes, string $link ): string => '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button' . $attributes . ' --><div class="wp-block-button">' . $link . '</div><!-- /wp:button --></div><!-- /wp:buttons -->';

		return array(
			'script url'          => array( $button( '', '<a class="wp-block-button__link wp-element-button" href="javascript:alert(1)">Go</a>' ), 'button.url.invalid' ),
			'missing url'         => array( $button( '', '<a class="wp-block-button__link wp-element-button">Go</a>' ), 'button.url.invalid' ),
			'empty label'         => array( $button( '', '<a class="wp-block-button__link wp-element-button" href="https://example.com"></a>' ), 'button.label.empty' ),
			'button element'      => array( $button( ' {"tagName":"button"}', '<button type="button" class="wp-block-button__link wp-element-button">Go</button>' ), 'block.attribute.invalid' ),
			'gradient'            => array( $button( ' {"gradient":"vivid"}', '<a class="wp-block-button__link has-vivid-gradient-background wp-element-button" href="https://example.com">Go</a>' ), 'block.attribute.invalid' ),
			'font size'           => array( $button( ' {"fontSize":"large"}', '<a class="wp-block-button__link has-large-font-size wp-element-button" href="https://example.com">Go</a>' ), 'block.attribute.invalid' ),
			'custom width'        => array( $button( ' {"style":{"dimensions":{"width":"50%"}}}', '<a class="wp-block-button__link wp-element-button" href="https://example.com">Go</a>' ), 'block.attribute.invalid' ),
			'border radius'       => array( $button( ' {"style":{"border":{"radius":"20px"}}}', '<a class="wp-block-button__link wp-element-button" href="https://example.com">Go</a>' ), 'block.attribute.invalid' ),
			'unknown style'       => array( $button( ' {"className":"is-style-neon"}', '<a class="wp-block-button__link wp-element-button" href="https://example.com">Go</a>' ), 'block.attribute.invalid' ),
			'unsafe label'        => array( $button( '', '<a class="wp-block-button__link wp-element-button" href="https://example.com"><img src="x" onerror="y"></a>' ), 'block.attribute.invalid' ),
			'space-between group' => array( '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"space-between"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com">Go</a></div><!-- /wp:button --></div><!-- /wp:buttons -->', 'block.attribute.invalid' ),
			'styled group'        => array( '<!-- wp:buttons {"style":{"spacing":{"blockGap":"2rem"}}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com">Go</a></div><!-- /wp:button --></div><!-- /wp:buttons -->', 'block.attribute.invalid' ),
		);
	}

	public function test_ungrouped_core_button_is_invalid_nesting(): void {
		$result = $this->compile( '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com">Go</a></div><!-- /wp:button -->' );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );
	}

	public function test_lists_render_deterministic_html_and_plain_text(): void {
		$result = $this->compile(
			'<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Alpha <a href="https://example.com/a">link</a></li><!-- /wp:list-item --><!-- wp:list-item --><li><em>Beta</em><br>continued</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
			. '<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li>Two</li><!-- /wp:list-item --></ol><!-- /wp:list -->'
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString(
			'<ul style="margin:0;padding:0 0 0 24px"><li style="margin:0 0 8px 0">Alpha <a href="https://example.com/a" style="color:inherit;text-decoration:underline">link</a></li><li style="margin:0 0 8px 0"><em>Beta</em><br>continued</li></ul>'
			. '<ol style="margin:0;padding:0 0 0 24px"><li style="margin:0 0 8px 0">One</li><li style="margin:0 0 8px 0">Two</li></ol>',
			$result->html()
		);
		self::assertSame( "- Alpha link (https://example.com/a)\n- Beta continued\n1. One\n2. Two\n", $result->text() );
	}

	/**
	 * @dataProvider invalid_lists
	 */
	public function test_list_rejects_invalid_nesting_and_values( string $markup, string $code ): void {
		$result = $this->compile( $markup );

		self::assertFalse( $result->is_success() );
		self::assertSame( $code, $result->diagnostics()[0]->code() );
	}

	/** @return array<string, array{string, string}> */
	public static function invalid_lists(): array {
		return array(
			'nested list (outside v1)' => array( '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Outer<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Inner</li><!-- /wp:list-item --></ul><!-- /wp:list --></li><!-- /wp:list-item --></ul><!-- /wp:list -->', 'block.child.unsupported' ),
			'paragraph inside list'    => array( '<!-- wp:list --><ul class="wp-block-list"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></ul><!-- /wp:list -->', 'block.child.unsupported' ),
			'orphan list item'         => array( '<!-- wp:list-item --><li>x</li><!-- /wp:list-item -->', 'block.child.unsupported' ),
			'ordered flag mismatch'    => array( '<!-- wp:list {"ordered":true} --><ul class="wp-block-list"><!-- wp:list-item --><li>x</li><!-- /wp:list-item --></ul><!-- /wp:list -->', 'block.attribute.invalid' ),
			'start number'             => array( '<!-- wp:list {"ordered":true,"start":3} --><ol start="3" class="wp-block-list"><!-- wp:list-item --><li>x</li><!-- /wp:list-item --></ol><!-- /wp:list -->', 'block.attribute.invalid' ),
			'list styling'             => array( '<!-- wp:list {"textColor":"brand"} --><ul class="wp-block-list has-brand-color has-text-color"><!-- wp:list-item --><li>x</li><!-- /wp:list-item --></ul><!-- /wp:list -->', 'block.attribute.invalid' ),
			'unsafe item'              => array( '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li><script>x</script></li><!-- /wp:list-item --></ul><!-- /wp:list -->', 'list-item.content.invalid' ),
			'empty item'               => array( '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li></li><!-- /wp:list-item --></ul><!-- /wp:list -->', 'list-item.content.invalid' ),
		);
	}

	public function test_separator_renders_a_full_width_design_divider(): void {
		$default = $this->compile( '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->' );
		$wide    = $this->compile( '<!-- wp:separator {"className":"is-style-wide","backgroundColor":"brand"} --><hr class="wp-block-separator has-text-color has-brand-color has-alpha-channel-opacity has-brand-background-color has-background is-style-wide"/><!-- /wp:separator -->' );

		self::assertTrue( $default->is_success(), $this->diagnostics( $default ) );
		self::assertStringContainsString( '<table role="presentation" width="100%" align="center" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse"><tr><td style="border-top:1px solid #e0e0e0;font-size:0;line-height:0">&nbsp;</td></tr></table>', $default->html() );
		self::assertSame( "\n", $default->text(), 'A divider has no plain-text representation.' );
		self::assertTrue( $wide->is_success(), $this->diagnostics( $wide ) );
		self::assertStringContainsString( 'border-top:1px solid #1a6dcc', $wide->html() );
	}

	/**
	 * @dataProvider invalid_separators
	 */
	public function test_separator_rejects_unsupported_values( string $markup ): void {
		$result = $this->compile( $markup );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
	}

	/** @return array<string, array{string}> */
	public static function invalid_separators(): array {
		return array(
			'dots style'     => array( '<!-- wp:separator {"className":"is-style-dots"} --><hr class="wp-block-separator is-style-dots"/><!-- /wp:separator -->' ),
			'unknown colour' => array( '<!-- wp:separator {"backgroundColor":"neon"} --><hr class="wp-block-separator has-neon-background-color has-background"/><!-- /wp:separator -->' ),
			'margin'         => array( '<!-- wp:separator {"style":{"spacing":{"margin":{"top":"8px"}}}} --><hr class="wp-block-separator" style="margin-top:8px"/><!-- /wp:separator -->' ),
			'border'         => array( '<!-- wp:separator {"style":{"border":{"width":"3px"}}} --><hr class="wp-block-separator"/><!-- /wp:separator -->' ),
		);
	}

	public function test_spacer_uses_core_heights_within_email_bounds(): void {
		$default = $this->compile( '<!-- wp:spacer --><div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->' );
		$preset  = $this->compile( '<!-- wp:spacer {"height":"var:preset|spacing|40"} --><div style="height:var(--wp--preset--spacing--40)" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->' );

		self::assertTrue( $default->is_success(), $this->diagnostics( $default ) );
		self::assertStringContainsString( '<td height="100" style="height:100px;line-height:100px;font-size:0">&nbsp;</td>', $default->html() );
		self::assertTrue( $preset->is_success(), $this->diagnostics( $preset ) );
		self::assertMatchesRegularExpression( '/<td height="\d+" style="height:\d+px;/', $preset->html() );
	}

	/**
	 * @dataProvider invalid_spacers
	 */
	public function test_spacer_rejects_unbounded_or_unsupported_values( string $markup ): void {
		$result = $this->compile( $markup );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
	}

	/** @return array<string, array{string}> */
	public static function invalid_spacers(): array {
		return array(
			'too tall'   => array( '<!-- wp:spacer {"height":"900px"} --><div style="height:900px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->' ),
			'percentage' => array( '<!-- wp:spacer {"height":"10%"} --><div style="height:10%" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->' ),
			'width'      => array( '<!-- wp:spacer {"width":"40px"} --><div style="height:100px;width:40px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->' ),
		);
	}

	/**
	 * @dataProvider unsupported_core_blocks
	 */
	public function test_unsupported_core_blocks_fail_with_a_stable_diagnostic( string $markup, string $name ): void {
		$result = $this->compile( $markup );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );
		self::assertSame( 'blocks[0].innerBlocks[0].innerBlocks[0]', $result->diagnostics()[0]->path() );
		self::assertStringContainsString( $name, $result->diagnostics()[0]->to_array()['message'] );
		self::assertSame( '', $result->html() );
	}

	/** @return array<string, array{string, string}> */
	public static function unsupported_core_blocks(): array {
		return array(
			'group'     => array( '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->', 'core/group' ),
			'html'      => array( '<!-- wp:html --><div>raw</div><!-- /wp:html -->', 'core/html' ),
			'columns'   => array( '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"></div><!-- /wp:column --></div><!-- /wp:columns -->', 'core/columns' ),
			'cover'     => array( '<!-- wp:cover --><div class="wp-block-cover"></div><!-- /wp:cover -->', 'core/cover' ),
			'shortcode' => array( '<!-- wp:shortcode -->[gallery]<!-- /wp:shortcode -->', 'core/shortcode' ),
			'embed'     => array( '<!-- wp:embed {"url":"https://example.com"} --><figure class="wp-block-embed"></figure><!-- /wp:embed -->', 'core/embed' ),
			'quote'     => array( '<!-- wp:quote --><blockquote class="wp-block-quote"></blockquote><!-- /wp:quote -->', 'core/quote' ),
			'plugin'    => array( '<!-- wp:acme/widget /-->', 'acme/widget' ),
		);
	}

	public function test_core_blocks_share_columns_and_post_cards_keep_working(): void {
		$result = Compiler_Factory::create()->compile(
			parse_blocks(
				'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
				. '<!-- wp:campaignbridge/columns --><!-- wp:campaignbridge/column {"width":60} -->'
				. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Column heading</h3><!-- /wp:heading -->'
				. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Column item</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
				. '<!-- /wp:campaignbridge/column --><!-- wp:campaignbridge/column -->'
				. '<!-- wp:campaignbridge/post-card {"postId":42} --><!-- wp:campaignbridge/post-title /--><!-- /wp:campaignbridge/post-card -->'
				. '<!-- /wp:campaignbridge/column --><!-- /wp:campaignbridge/columns -->'
				. '<!-- wp:campaignbridge/post-card {"postId":42} --><!-- wp:campaignbridge/post-title /--><!-- wp:campaignbridge/post-excerpt /--><!-- /wp:campaignbridge/post-card -->'
				. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->'
			),
			$this->context()
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'Column heading</h3>', $result->html() );
		self::assertStringContainsString( 'Column item</li>', $result->html() );
		self::assertSame( 2, substr_count( $result->html(), 'Snapshot title' ) );
		self::assertStringContainsString( 'Snapshot excerpt', $result->text() );
		self::assertStringContainsString( 'width="60%"', $result->html() );
	}

	/** Compile Core markup inside the standard container and section. */
	private function compile( string $markup ): Compile_Result {
		return $this->compile_document( '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->' . $markup . '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->' );
	}

	private function compile_document( string $serialized ): Compile_Result {
		return Compiler_Factory::create()->compile( parse_blocks( $serialized ), $this->context() );
	}

	private function context(): Render_Context {
		return new Render_Context(
			array(
				'title'            => 'Core block fixture',
				'language'         => 'en',
				'background_color' => '#f4f4f4',
			),
			array(
				'posts' => array(
					'42' => Post_Snapshot::create(
						42,
						'post',
						array(
							'title'   => 'Snapshot title',
							'excerpt' => 'Snapshot excerpt',
							'url'     => 'https://example.com/post',
						)
					),
				),
			)
		);
	}

	private function fixture( string $name ): string {
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/Email/golden/' . $name );
		self::assertIsString( $contents );

		return $contents;
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
