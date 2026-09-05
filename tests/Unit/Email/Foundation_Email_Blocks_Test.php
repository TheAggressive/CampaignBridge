<?php
/**
 * Preheader, columns, and compliance-footer compiler contract tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Foundation_Email_Blocks_Test extends TestCase {
	public function test_compiles_the_v1_foundation_to_golden_html_and_text(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertSame( $this->fixture( 'foundation.html' ), $result->html() );
		self::assertSame( $this->fixture( 'foundation.txt' ), $result->text() );
	}

	public function test_hides_preview_text_from_the_body_and_from_plain_text(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertStringContainsString(
			'<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">Your November digest is here',
			$result->html()
		);
		self::assertStringNotContainsString( 'Your November digest is here', $result->text() );
	}

	public function test_rejects_empty_preview_text(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['attrs']['content'] = '   ';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'preheader.content.missing', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
	}

	public function test_rejects_preview_text_beyond_the_documented_limit(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['attrs']['content'] = str_repeat( 'a', 151 );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'preheader.content.too_long', $result->diagnostics()[0]->code() );
	}

	public function test_rejects_a_second_preheader(): void {
		$document = $this->document();
		array_splice( $document[0]['innerBlocks'], 1, 0, array( $this->preheader( 'Duplicate' ) ) );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'preheader.duplicated', $result->diagnostics()[0]->code() );
	}

	public function test_rejects_a_preheader_that_is_not_first(): void {
		$document                      = $this->document();
		$preheader                     = $document[0]['innerBlocks'][0];
		$document[0]['innerBlocks'][0] = $document[0]['innerBlocks'][1];
		$document[0]['innerBlocks'][1] = $preheader;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'preheader.misplaced', $result->diagnostics()[0]->code() );
	}

	public function test_rejects_a_compliance_footer_that_is_not_last(): void {
		$document = $this->document();
		$footer   = array_pop( $document[0]['innerBlocks'] );
		array_splice( $document[0]['innerBlocks'], 1, 0, array( $footer ) );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'compliance_footer.misplaced', $result->diagnostics()[0]->code() );
	}

	public function test_divides_a_single_column_row_across_the_full_width(): void {
		$document = $this->document();
		array_pop( $document[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'] );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'width="100%" style="width:100%;vertical-align:middle"', $result->html() );
	}

	public function test_rejects_more_columns_than_the_profile_supports(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][] = $this->column( array() );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'columns.count.invalid', $result->diagnostics()[0]->code() );
	}

	public function test_rejects_widths_declared_on_only_some_columns(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['width'] = 60;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'columns.width.partial', $result->diagnostics()[0]->code() );
	}

	public function test_rejects_column_widths_that_do_not_total_one_hundred(): void {
		$document                     = $this->document();
		$columns                      = &$document[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'];
		$columns[0]['attrs']['width'] = 60;
		$columns[1]['attrs']['width'] = 60;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'columns.width.total', $result->diagnostics()[0]->code() );
	}

	public function test_honours_explicit_column_widths_that_total_one_hundred(): void {
		$document                     = $this->document();
		$columns                      = &$document[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'];
		$columns[0]['attrs']['width'] = 65;
		$columns[1]['attrs']['width'] = 35;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'width="65%"', $result->html() );
		self::assertStringContainsString( 'width="35%"', $result->html() );
	}

	public function test_rejects_a_column_width_outside_the_documented_range(): void {
		$document                     = $this->document();
		$columns                      = &$document[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'];
		$columns[0]['attrs']['width'] = 90;
		$columns[1]['attrs']['width'] = 10;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
	}

	public function test_requires_a_postal_address_in_the_compliance_footer(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][2]['attrs']['address'] = '';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'compliance.address.missing', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
	}

	public function test_requires_an_https_unsubscribe_url_from_template_metadata(): void {
		$result = Compiler_Factory::create()->compile(
			$this->document(),
			new Render_Context( array( 'title' => 'Foundation fixture' ), array(), array(), 'universal@1' )
		);

		self::assertFalse( $result->is_success() );
		self::assertSame( 'compliance.unsubscribe.missing', $result->diagnostics()[0]->code() );
	}

	public function test_rejects_a_non_https_unsubscribe_url(): void {
		$result = Compiler_Factory::create()->compile(
			$this->document(),
			new Render_Context(
				array(
					'title'           => 'Foundation fixture',
					'unsubscribe_url' => 'http://example.com/unsubscribe',
				),
				array(),
				array(),
				'universal@1'
			)
		);

		self::assertFalse( $result->is_success() );
		self::assertSame( 'compliance.unsubscribe.missing', $result->diagnostics()[0]->code() );
	}

	public function test_keeps_the_unsubscribe_destination_out_of_the_block_source(): void {
		$footer = $this->document()[0]['innerBlocks'][2];

		self::assertArrayNotHasKey( 'unsubscribeUrl', $footer['attrs'] );
		self::assertStringNotContainsString( 'unsubscribe', wp_json_encode( $footer['attrs'] ) ?: '' );
	}

	/**
	 * Build the shared foundation document.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					$this->preheader( 'Your November digest is here' ),
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array(
							array(
								'blockName'   => 'campaignbridge/columns',
								'attrs'       => array(
									'gap'           => 24,
									'verticalAlign' => 'middle',
								),
								'innerBlocks' => array(
									$this->column(
										array(
											array(
												'blockName' => 'campaignbridge/heading',
												'attrs' => array(
													'content' => 'Left',
													'level'   => 2,
												),
												'innerBlocks' => array(),
											),
										)
									),
									$this->column(
										array(
											array(
												'blockName' => 'campaignbridge/text',
												'attrs' => array( 'content' => 'Right side copy.' ),
												'innerBlocks' => array(),
											),
										),
										array( 'backgroundColor' => '#f0f0f0' )
									),
								),
							),
						),
					),
					array(
						'blockName'   => 'campaignbridge/compliance-footer',
						'attrs'       => array(
							'businessName' => 'Example Co',
							'address'      => '123 Example St, Portland, OR 97201',
						),
						'innerBlocks' => array(),
					),
				),
			),
		);
	}

	/**
	 * Build a preheader block.
	 *
	 * @param string $content Preview text.
	 * @return array<string, mixed>
	 */
	private function preheader( string $content ): array {
		return array(
			'blockName'   => 'campaignbridge/preheader',
			'attrs'       => array( 'content' => $content ),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Build a column block.
	 *
	 * @param array<int, array<string, mixed>> $children Column children.
	 * @param array<string, mixed>             $attrs    Column attributes.
	 * @return array<string, mixed>
	 */
	private function column( array $children, array $attrs = array() ): array {
		return array(
			'blockName'   => 'campaignbridge/column',
			'attrs'       => $attrs,
			'innerBlocks' => $children,
		);
	}

	/** Build the shared render context. */
	private function context(): Render_Context {
		return new Render_Context(
			array(
				'title'           => 'Foundation fixture',
				'unsubscribe_url' => 'https://example.com/unsubscribe',
			),
			array(),
			array(),
			'universal@1'
		);
	}

	/**
	 * Read a golden fixture.
	 *
	 * @param string $name Fixture file name.
	 */
	private function fixture( string $name ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/Email/golden/' . $name );
	}
}
