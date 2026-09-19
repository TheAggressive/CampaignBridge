<?php
/**
 * Synthetic preview personalization tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email\Token;

use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Token\Token_Definition;
use CampaignBridge\Domain\Email\Token\Token_Preview;
use CampaignBridge\Domain\Email\Token\Token_Registry;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Token_Preview_Test extends TestCase {
	private const DOCUMENT = '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
		. '<!-- wp:paragraph --><p>Hello <strong>{{cb:subscriber.first_name}} {{cb:subscriber.last_name}}</strong>, this is for {{cb:subscriber.email}}. <a href="{{cb:campaign.unsubscribe_url}}">Unsubscribe</a></p><!-- /wp:paragraph -->'
		. '<!-- wp:heading --><h2 class="wp-block-heading">From {{cb:organization.name}}</h2><!-- /wp:heading -->'
		. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{cb:campaign.view_online_url}}">View online</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

	public function test_samples_cover_exactly_the_sample_previewed_provider_tokens(): void {
		$expected = array();
		foreach ( Token_Registry::default()->all() as $definition ) {
			if ( $definition->requires_provider_resolution() && Token_Definition::PREVIEW_SAMPLE === $definition->get_preview_behavior() ) {
				$expected[] = $definition->get_id();
			}
		}

		$actual = array_keys( Token_Preview::SAMPLE_VALUES );
		sort( $expected );
		sort( $actual );
		self::assertSame( $expected, $actual );
		Token_Preview::default();
	}

	public function test_sample_urls_use_the_reserved_example_domain(): void {
		foreach ( Token_Registry::default()->all() as $definition ) {
			$sample = Token_Preview::SAMPLE_VALUES[ $definition->get_id() ] ?? null;
			if ( null === $sample || Token_Definition::VALUE_TYPE_URL !== $definition->get_value_type() ) {
				continue;
			}

			self::assertStringStartsWith( 'https://example.com/', $sample );
		}
	}

	public function test_compiled_artifact_gains_a_sample_view_without_changing(): void {
		$result = $this->compile();
		self::assertTrue( $result->is_success() );

		$html    = $result->html();
		$text    = $result->text();
		$preview = Token_Preview::default();

		self::assertTrue( $preview->applies_to( $html ) );
		$sample_html = $preview->html( $html );
		$sample_text = $preview->text( $text );

		self::assertSame( $html, $result->html(), 'The canonical artifact is never rewritten.' );
		self::assertStringContainsString( '{{cb:subscriber.first_name}}', $html );

		self::assertStringContainsString( 'Hello <strong>Alex Sample</strong>, this is for .', $sample_html );
		self::assertStringContainsString( 'href="https://example.com/campaignbridge-preview/unsubscribe"', $sample_html );
		self::assertSame( 2, substr_count( $sample_html, 'href="https://example.com/campaignbridge-preview/view-online"' ) );
		self::assertStringContainsString( 'From Example Company</h2>', $sample_html );
		self::assertStringContainsString( 'Hello Alex Sample, this is for . Unsubscribe (https://example.com/campaignbridge-preview/unsubscribe)', $sample_text );

		foreach ( array( $sample_html, $sample_text ) as $view ) {
			self::assertStringNotContainsString( '{{cb:', $view );
		}
	}

	public function test_artifact_without_provider_tokens_needs_no_sample_view(): void {
		self::assertFalse( Token_Preview::default()->applies_to( '<p>Hello Example Company</p>' ) );
	}

	public function test_literal_tokens_stay_canonical_and_omitted_tokens_are_removed(): void {
		$preview = new Token_Preview(
			Token_Registry::create(
				1,
				array(
					Token_Definition::create( 'cb:subscriber.nickname', 'Nickname', Token_Definition::CATEGORY_SUBSCRIBER, Token_Definition::VALUE_TYPE_STRING, false, Token_Definition::PREVIEW_LITERAL, true, false ),
					Token_Definition::create( 'cb:subscriber.phone', 'Phone', Token_Definition::CATEGORY_SUBSCRIBER, Token_Definition::VALUE_TYPE_STRING, false, Token_Definition::PREVIEW_OMIT, true, false ),
				)
			)
		);

		self::assertFalse( $preview->applies_to( 'Hi {{cb:subscriber.nickname}}' ) );
		self::assertSame( 'Hi {{cb:subscriber.nickname}}, call ', $preview->text( 'Hi {{cb:subscriber.nickname}}, call {{cb:subscriber.phone}}' ) );
	}

	public function test_a_sample_previewed_token_without_a_sample_fails_at_construction(): void {
		$this->expectException( \LogicException::class );

		new Token_Preview(
			Token_Registry::create(
				1,
				array(
					Token_Definition::create( 'cb:subscriber.city', 'City', Token_Definition::CATEGORY_SUBSCRIBER, Token_Definition::VALUE_TYPE_STRING, false, Token_Definition::PREVIEW_SAMPLE, true, false ),
				)
			)
		);
	}

	public function test_campaignbridge_resolved_tokens_are_not_preview_samples(): void {
		$preview = Token_Preview::default();

		self::assertFalse( $preview->applies_to( '{{cb:organization.name}}' ) );
		self::assertSame( '{{cb:organization.name}}', $preview->html( '{{cb:organization.name}}' ) );
	}

	private function compile(): \CampaignBridge\Domain\Email\Compile_Result {
		return Compiler_Factory::create()->compile(
			parse_blocks( self::DOCUMENT ),
			new Render_Context(
				array(
					'title'        => 'Preview fixture',
					'language'     => 'en',
					'token_values' => array( 'cb:organization.name' => 'Example Company' ),
				),
				array( 'posts' => array( '42' => Post_Snapshot::create( 42, 'post', array( 'title' => 'T', 'excerpt' => 'E', 'url' => 'https://example.com/p' ) ) ) )
			)
		);
	}
}
