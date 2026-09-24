<?php
/**
 * Resolved email design compiler integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Resolved_Email_Design;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Admin\Editor_Design_Settings;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Design\Email_Design_Factory;
use CampaignBridge\Workflow\Email\Email_Compiler;
use PHPUnit\Framework\TestCase;

/** Proves the compiler consumes normalized defaults and preserves precedence. */
final class Email_Design_Compiler_Test extends TestCase {
	/** Resolved layout defaults reach canonical HTML without preset references. */
	public function test_resolved_design_supplies_compiler_defaults(): void {
		$design = $this->design_with_width( 640 );
		$result = Compiler_Factory::create( $design )->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'class="cb-email-container" width="640"', $result->html() );
		self::assertStringNotContainsString( 'var:preset|', $result->html() );
	}

	/** Explicit Gutenberg block values retain the highest precedence. */
	public function test_explicit_block_value_wins_over_resolved_default(): void {
		$document                         = $this->document();
		$document[0]['attrs']['maxWidth'] = 550;

		$result = Compiler_Factory::create( $this->design_with_width( 640 ) )->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'class="cb-email-container" width="550"', $result->html() );
	}

	/** Design identity participates in the artifact fingerprint. */
	public function test_design_changes_artifact_fingerprint(): void {
		$default = Compiler_Factory::create()->compile( $this->document(), $this->context() );
		$wide    = Compiler_Factory::create( $this->design_with_width( 640 ) )->compile( $this->document(), $this->context() );

		self::assertTrue( $default->is_success() );
		self::assertTrue( $wide->is_success() );
		self::assertNotSame( $default->fingerprint(), $wide->fingerprint() );
	}

	/** Editor and compiler render the same resolved text defaults. */
	public function test_editor_and_compiler_share_text_defaults(): void {
		$design   = Email_Design_Factory::resolve();
		$settings = Editor_Design_Settings::apply( array(), $design );
		$result   = Compiler_Factory::create( $design )->compile( $this->text_document(), $this->context() );
		$css      = $settings['styles'][1]['css'];

		self::assertTrue( $result->is_success() );
		foreach ( array( 'font-family:Arial,Helvetica,sans-serif', 'font-size:16px', 'line-height:1.6', 'color:#111111', 'margin:0 0 16px' ) as $declaration ) {
			self::assertStringContainsString( $declaration, $css );
			self::assertStringContainsString( $declaration, $result->html() );
		}
		self::assertStringNotContainsString( 'var:preset|', $result->html() );
	}

	/** Inherited and explicit Core font choices have editor/compiler parity. */
	public function test_brand_heading_default_and_explicit_override_share_one_design(): void {
		$kit      = Brand_Kit::from_colors(
			array(),
			Brand_Kit::SOURCE_CUSTOM,
			null,
			array(
				'heading' => 'playfair',
				'body'    => 'inter',
				'button'  => 'inter',
			)
		);
		$design   = Email_Design_Factory::resolve( $kit );
		$settings = Editor_Design_Settings::apply( array(), $design );
		$document = $this->heading_document();
		$document[0]['innerBlocks'][0]['innerBlocks'][] = array(
			'blockName'   => 'core/heading',
			'attrs'       => array(
				'content'    => 'Explicit heading',
				'fontFamily' => 'montserrat',
			),
			'innerBlocks' => array(),
		);

		$result = Compiler_Factory::create( $design )->compile( $document, $this->context() );
		$css    = implode( '', array_column( $settings['styles'], 'css' ) );

		self::assertTrue( $result->is_success() );
		self::assertArrayNotHasKey( 'fontFamily', $document[0]['innerBlocks'][0]['innerBlocks'][0]['attrs'] );
		self::assertStringContainsString( 'font-family:Playfair Display,Georgia,serif', $css );
		self::assertStringContainsString( 'font-family:Playfair Display,Georgia,serif', $result->html() );
		self::assertStringContainsString( 'font-family:Montserrat,Arial,Helvetica,sans-serif', $result->html() );
		self::assertStringContainsString( 'family=Playfair+Display', $result->html() );
		self::assertStringContainsString( 'family=Montserrat', $result->html() );
	}

	/** Unknown explicit preset references fail with the stable compiler model. */
	public function test_unknown_explicit_font_preset_fails_closed(): void {
		$document = $this->heading_document();
		$document[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['fontFamily'] = 'unknown-font';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertStringEndsWith( '.attrs.fontFamily', $result->diagnostics()[0]->path() );
	}

	/**
	 * Build the smallest valid compiler document.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(),
			),
		);
	}

	/** @return array<int, array<string, mixed>> */
	private function text_document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array(
							array(
								'blockName'   => 'core/paragraph',
								'attrs'       => array( 'content' => 'Shared defaults' ),
								'innerBlocks' => array(),
							),
						),
					),
				),
			),
		);
	}

	/** @return array<int, array<string, mixed>> */
	private function heading_document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array(
							array(
								'blockName'   => 'core/heading',
								'attrs'       => array( 'content' => 'Inherited heading' ),
								'innerBlocks' => array(),
							),
						),
					),
				),
			),
		);
	}

	/** Build the universal render context. */
	private function context(): Render_Context {
		return new Render_Context( array(), array(), array(), Email_Compiler::PROFILE_VERSION );
	}

	/**
	 * Create a resolved design variant for dependency-injection coverage.
	 *
	 * @param int $width Content width in pixels.
	 */
	private function design_with_width( int $width ): Resolved_Email_Design {
		$resolved                                     = Email_Design_Factory::resolve();
		$design                                       = $resolved->to_array();
		$design['settings']['layout']['contentWidth'] = $width;

		return new Resolved_Email_Design( $design, hash( 'sha256', (string) $width ) );
	}
}
