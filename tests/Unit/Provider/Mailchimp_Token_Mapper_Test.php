<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Mailchimp token mapping tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Token\Token_Parser;
use CampaignBridge\Domain\Email\Token\Token_Registry;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Provider\Token_Mapping;
use CampaignBridge\Providers\Mailchimp_Token_Mapper;
use CampaignBridge\Services\Email\Compiler_Factory;
use WP_UnitTestCase;

/** Proves canonical tokens map to Mailchimp tags only where the audience supports them. */
final class Mailchimp_Token_Mapper_Test extends WP_UnitTestCase {
	private const DOCUMENT = '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
		. '<!-- wp:paragraph --><p>Hello <strong>{{cb:subscriber.first_name}} {{cb:subscriber.last_name}}</strong>, this is for {{cb:subscriber.email}}. <a href="{{cb:campaign.unsubscribe_url}}">Unsubscribe</a></p><!-- /wp:paragraph -->'
		. '<!-- wp:heading --><h2 class="wp-block-heading">From {{cb:organization.name}}</h2><!-- /wp:heading -->'
		. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{cb:campaign.view_online_url}}">View online</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

	/** @param array<int, string> $tags Discovered merge-field tags. */
	private static function fields( array $tags, bool $complete = true, string $audience = 'abc123' ): Discovery_Result {
		return Discovery_Result::create(
			'mailchimp',
			$audience,
			Discovery_Batch::create(
				Discovery_Kind::MERGE_FIELDS,
				array_map( static fn ( string $tag ): Discovered_Merge_Field => Discovered_Merge_Field::create( $tag, ucfirst( strtolower( $tag ) ), 'text', false ), $tags ),
				$complete
			),
			'2026-10-01T12:00:00Z'
		);
	}

	public function test_every_provider_token_maps_when_the_audience_has_name_fields(): void {
		$mapping = ( new Mailchimp_Token_Mapper() )->map( Token_Registry::default(), 'abc123', self::fields( array( 'FNAME', 'LNAME', 'COMPANY' ) ) );

		self::assertSame(
			array(
				'provider'    => 'mailchimp',
				'scope'       => 'abc123',
				'mapped'      => array(
					'cb:subscriber.first_name'    => '*|FNAME|*',
					'cb:subscriber.last_name'     => '*|LNAME|*',
					'cb:subscriber.email'         => '*|EMAIL|*',
					'cb:campaign.view_online_url' => '*|ARCHIVE|*',
					'cb:campaign.unsubscribe_url' => '*|UNSUB|*',
				),
				'unsupported' => array(),
			),
			$mapping->to_array()
		);
		self::assertNull( $mapping->representation( 'cb:organization.name' ), 'CampaignBridge-owned tokens are resolved locally, not mapped.' );
		self::assertNull( $mapping->representation( 'cb:subscriber.company' ), 'Audience custom fields never become canonical tokens.' );
	}

	public function test_name_tokens_are_unsupported_unless_the_audience_proves_the_field(): void {
		$mapper = new Mailchimp_Token_Mapper();

		$missing = $mapper->map( Token_Registry::default(), 'abc123', self::fields( array( 'FNAME' ) ) );
		self::assertSame( array( 'cb:subscriber.last_name' => Token_Mapping::REASON_MERGE_FIELD_MISSING ), $missing->unsupported() );

		$truncated = $mapper->map( Token_Registry::default(), 'abc123', self::fields( array( 'FNAME' ), false ) );
		self::assertSame( array( 'cb:subscriber.last_name' => Token_Mapping::REASON_MERGE_FIELDS_INCOMPLETE ), $truncated->unsupported() );

		$undiscovered = $mapper->map( Token_Registry::default(), 'abc123', null );
		self::assertSame(
			array(
				'cb:subscriber.first_name' => Token_Mapping::REASON_MERGE_FIELDS_INCOMPLETE,
				'cb:subscriber.last_name'  => Token_Mapping::REASON_MERGE_FIELDS_INCOMPLETE,
			),
			$undiscovered->unsupported()
		);
		self::assertSame( '*|UNSUB|*', $undiscovered->representation( 'cb:campaign.unsubscribe_url' ), 'System tags need no discovery.' );
	}

	public function test_merge_fields_must_belong_to_the_same_mailchimp_audience(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new Mailchimp_Token_Mapper() )->map( Token_Registry::default(), 'abc123', self::fields( array( 'FNAME', 'LNAME' ), true, 'other99' ) );
	}

	public function test_compiled_artifacts_translate_without_mutating_canonical_content(): void {
		$compiled = Compiler_Factory::create()->compile(
			parse_blocks( self::DOCUMENT ),
			new Render_Context(
				array(
					'title'        => 'Mapping fixture',
					'language'     => 'en',
					'token_values' => array( 'cb:organization.name' => 'Example Company' ),
				),
				array( 'posts' => array( '42' => Post_Snapshot::create( 42, 'post', array( 'title' => 'T', 'excerpt' => 'E', 'url' => 'https://example.com/p' ) ) ) )
			)
		);
		self::assertTrue( $compiled->is_success() );
		$canonical_html = $compiled->html();
		$canonical_text = $compiled->text();

		$mapping = ( new Mailchimp_Token_Mapper() )->map( Token_Registry::default(), 'abc123', self::fields( array( 'FNAME', 'LNAME' ) ) );
		$html    = $mapping->translate( $compiled->html(), Token_Registry::default(), new Token_Parser() );
		$text    = $mapping->translate( $compiled->text(), Token_Registry::default(), new Token_Parser() );

		self::assertTrue( $html->is_complete() );
		self::assertTrue( $text->is_complete() );
		self::assertStringNotContainsString( '{{cb:', $html->content() . $text->content() );
		foreach ( array( '*|FNAME|*', '*|LNAME|*', '*|EMAIL|*', 'href="*|UNSUB|*"', 'href="*|ARCHIVE|*"', 'Example Company' ) as $expected ) {
			self::assertStringContainsString( $expected, $html->content() );
		}
		self::assertSame( $canonical_html, $compiled->html(), 'The canonical artifact is never altered.' );
		self::assertSame( $canonical_text, $compiled->text() );
		self::assertStringContainsString( '{{cb:subscriber.first_name}}', $compiled->html() );
	}

	public function test_a_footer_without_a_template_url_carries_mailchimps_unsubscribe_tag(): void {
		$compiled = Compiler_Factory::create()->compile(
			parse_blocks( '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- wp:campaignbridge/compliance-footer {"businessName":"Example Co","address":"1 Example St"} /--><!-- /wp:campaignbridge/container -->' ),
			new Render_Context(
				array(
					'title'    => 'Footer fixture',
					'language' => 'en',
				)
			)
		);
		self::assertTrue( $compiled->is_success() );

		$mapping = ( new Mailchimp_Token_Mapper() )->map( Token_Registry::default(), 'abc123', self::fields( array( 'FNAME', 'LNAME' ) ) );
		$html    = $mapping->translate( $compiled->html(), Token_Registry::default(), new Token_Parser() );
		$text    = $mapping->translate( $compiled->text(), Token_Registry::default(), new Token_Parser() );

		self::assertTrue( $html->is_complete() );
		self::assertTrue( $text->is_complete() );
		self::assertStringContainsString( '<a href="*|UNSUB|*"', $html->content() );
		self::assertStringContainsString( 'Unsubscribe: *|UNSUB|*', $text->content() );
	}

	public function test_translation_fails_closed_on_unmapped_or_malformed_tokens(): void {
		$parser   = new Token_Parser();
		$registry = Token_Registry::default();
		$mapping  = ( new Mailchimp_Token_Mapper() )->map( $registry, 'abc123', self::fields( array( 'FNAME' ) ) );

		$unmapped = $mapping->translate( 'Hi {{cb:subscriber.first_name}} {{cb:subscriber.last_name}} {{cb:organization.name}}', $registry, $parser );
		self::assertFalse( $unmapped->is_complete() );
		self::assertSame( array( 'cb:subscriber.last_name', 'cb:organization.name' ), $unmapped->unmapped() );
		self::assertSame( 'Hi *|FNAME|* {{cb:subscriber.last_name}} {{cb:organization.name}}', $unmapped->content() );

		$malformed = $mapping->translate( 'Hi {{cb:subscriber.FIRST}} and {{cb:subscriber.first_name}}', $registry, $parser );
		self::assertFalse( $malformed->is_complete() );
		self::assertNotEmpty( $malformed->parse_errors() );
		self::assertSame( 'Hi {{cb:subscriber.FIRST}} and {{cb:subscriber.first_name}}', $malformed->content(), 'Nothing is translated when parsing fails.' );

		// Mailchimp would evaluate author-typed merge syntax after handoff, so it fails closed.
		$literal = $mapping->translate( 'Mailchimp syntax *|FNAME|* is not literal there.', $registry, $parser );
		self::assertTrue( $literal->has_literal_conflict() );
		self::assertFalse( $literal->is_complete() );
		self::assertSame( 'Mailchimp syntax *|FNAME|* is not literal there.', $literal->content() );
		self::assertTrue( $mapping->translate( 'A plain *| pipe |* free sentence.', $registry, $parser )->has_literal_conflict() );
		self::assertFalse( $mapping->translate( 'Price: 5 * 3 | total', $registry, $parser )->has_literal_conflict() );
	}

	public function test_mappings_must_cover_every_provider_token_explicitly(): void {
		$this->expectException( \InvalidArgumentException::class );
		Token_Mapping::create( 'mailchimp', 'abc123', array( 'cb:subscriber.email' => '*|EMAIL|*' ), array(), Token_Registry::default() );
	}
}
