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
