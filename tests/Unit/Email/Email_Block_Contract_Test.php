<?php
/**
 * Authoritative email authoring block contract tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Core_Block_Normalizer;
use CampaignBridge\Services\Email\Email_Block_Contract;
use PHPUnit\Framework\TestCase;

/** Prove every consumer of the supported-block grammar agrees with the contract. */
final class Email_Block_Contract_Test extends TestCase {
	private const SUPPORTED_CORE = array(
		'core/paragraph',
		'core/heading',
		'core/image',
		'core/buttons',
		'core/button',
		'core/list',
		'core/list-item',
		'core/separator',
		'core/spacer',
		'core/social-links',
		'core/social-link',
	);

	private const CAMPAIGNBRIDGE = array(
		'campaignbridge/container',
		'campaignbridge/preheader',
		'campaignbridge/section',
		'campaignbridge/columns',
		'campaignbridge/column',
		'campaignbridge/post-card',
		'campaignbridge/post-image',
		'campaignbridge/navigation',
		'campaignbridge/compliance-footer',
	);

	private const OBSOLETE = array(
		'campaignbridge/text',
		'campaignbridge/heading',
		'campaignbridge/image',
		'campaignbridge/button',
		'campaignbridge/divider',
		'campaignbridge/spacer',
		'campaignbridge/list',
		'campaignbridge/list-item',
		'campaignbridge/post-excerpt',
		'campaignbridge/post-button',
		'campaignbridge/post-link',
		'campaignbridge/post-title',
	);

	public function test_contract_names_exactly_the_supported_core_and_campaignbridge_blocks(): void {
		$core = Email_Block_Contract::core_names();
		sort( $core, SORT_STRING );
		$expected_core = self::SUPPORTED_CORE;
		sort( $expected_core, SORT_STRING );
		self::assertSame( $expected_core, $core );

		$custom = array_values( array_diff( Email_Block_Contract::names(), Email_Block_Contract::core_names() ) );
		sort( $custom, SORT_STRING );
		$expected_custom = self::CAMPAIGNBRIDGE;
		sort( $expected_custom, SORT_STRING );
		self::assertSame( $expected_custom, $custom );
	}

	public function test_compiler_registry_matches_the_contract(): void {
		$registry = Compiler_Factory::registry()->block_names();
		$contract = Email_Block_Contract::names();
		sort( $registry, SORT_STRING );
		sort( $contract, SORT_STRING );

		self::assertSame( $contract, $registry );
	}

	public function test_renderer_nesting_is_the_contract_nesting(): void {
		$registry = Compiler_Factory::registry();
		foreach ( Email_Block_Contract::names() as $name ) {
			$renderer = $registry->get( $name );
			self::assertNotNull( $renderer, $name );
			self::assertSame( Email_Block_Contract::children( $name ), $renderer->allowed_children(), $name );
		}
	}

	public function test_core_normalization_supports_exactly_the_contract_core_semantics(): void {
		$semantics = array_map( static fn ( string $name ): string => (string) Email_Block_Contract::semantics( $name ), Email_Block_Contract::core_names() );
		sort( $semantics, SORT_STRING );
		$normalizer = Core_Block_Normalizer::SEMANTICS;
		sort( $normalizer, SORT_STRING );

		self::assertSame( $normalizer, $semantics );
	}

	public function test_core_blocks_map_to_explicit_email_semantics(): void {
		self::assertSame(
			array(
				'core/paragraph' => 'text',
				'core/heading'   => 'heading',
				'core/image'     => 'image',
				'core/buttons'   => 'button-group',
				'core/button'    => 'button',
				'core/list'      => 'list',
				'core/list-item' => 'list-item',
				'core/separator' => 'divider',
				'core/spacer'    => 'spacer',
				'core/social-links' => 'social-links',
				'core/social-link'  => 'social-link',
			),
			array_combine( self::SUPPORTED_CORE, array_map( array( Email_Block_Contract::class, 'semantics' ), self::SUPPORTED_CORE ) )
		);
	}

	public function test_v1_nesting_is_intentionally_constrained(): void {
		self::assertSame( array( 'core/button' ), Email_Block_Contract::children( 'core/buttons' ) );
		self::assertSame( array( 'core/list-item' ), Email_Block_Contract::children( 'core/list' ) );
		self::assertSame( array( 'core/social-link' ), Email_Block_Contract::children( 'core/social-links' ) );
		self::assertSame( array(), Email_Block_Contract::children( 'core/list-item' ), 'Nested lists are outside the v1 grammar.' );
		self::assertSame( array( 'campaignbridge/column' ), Email_Block_Contract::children( 'campaignbridge/columns' ) );
		self::assertNotContains( 'campaignbridge/columns', Email_Block_Contract::children( 'campaignbridge/column' ) );
		self::assertNotContains( 'core/button', Email_Block_Contract::children( 'campaignbridge/section' ), 'Core buttons are always grouped.' );
		self::assertNotContains( 'core/list-item', Email_Block_Contract::children( 'campaignbridge/section' ) );
		foreach ( array( 'core/group', 'core/cover', 'core/gallery', 'core/embed', 'core/video', 'core/html', 'core/shortcode', 'core/query', 'core/navigation', 'core/columns', 'core/column' ) as $unsupported ) {
			self::assertFalse( Email_Block_Contract::has( $unsupported ), $unsupported );
		}
	}

	public function test_social_service_catalogue_is_bounded_and_matches_packaged_assets(): void {
		self::assertSame(
			array( 'bluesky', 'facebook', 'instagram', 'linkedin', 'mastodon', 'tiktok', 'x', 'youtube' ),
			array_keys( Email_Block_Contract::social_services() )
		);
		foreach ( array_keys( Email_Block_Contract::social_services() ) as $service ) {
			self::assertFileExists( dirname( __DIR__, 3 ) . '/assets/email/social/' . $service . '.png' );
		}
	}

	public function test_replaced_campaignbridge_duplicates_are_gone(): void {
		$blocks_directory = dirname( __DIR__, 3 ) . '/src/blocks/';
		$registry         = Compiler_Factory::registry();
		foreach ( self::OBSOLETE as $name ) {
			self::assertFalse( Email_Block_Contract::has( $name ), $name );
			self::assertNull( $registry->get( $name ), $name );
			self::assertDirectoryDoesNotExist( $blocks_directory . substr( $name, strlen( 'campaignbridge/' ) ), $name );
		}
	}

	public function test_post_card_children_are_the_mixed_core_and_campaignbridge_composition(): void {
		self::assertSame(
			array(
				'campaignbridge/columns',
				'campaignbridge/post-image',
				'core/heading',
				'core/paragraph',
				'core/buttons',
			),
			Email_Block_Contract::children( 'campaignbridge/post-card' )
		);
		self::assertSame(
			array(
				'core/paragraph',
				'core/heading',
				'core/image',
				'core/buttons',
				'core/list',
				'core/separator',
				'core/spacer',
				'core/social-links',
				'campaignbridge/post-card',
			'campaignbridge/post-image',
			'campaignbridge/navigation',
		),
			Email_Block_Contract::children( 'campaignbridge/column' )
		);
	}

	public function test_exactly_one_read_only_post_binding_source_is_supported(): void {
		self::assertSame( 'campaignbridge/post-data', Email_Block_Contract::binding_source() );
		self::assertSame( array( 'core/heading', 'core/paragraph', 'core/button' ), Email_Block_Contract::binding_block_names() );
		self::assertSame( array( 'content' ), Email_Block_Contract::binding_attribute_names( 'core/heading' ) );
		self::assertSame( array( 'content' ), Email_Block_Contract::binding_attribute_names( 'core/paragraph' ) );
		self::assertSame( array( 'url' ), Email_Block_Contract::binding_attribute_names( 'core/button' ) );
	}

	public function test_every_binding_field_belongs_to_the_snapshot_contract(): void {
		$supported = Post_Snapshot::supported_fields();
		foreach ( Email_Block_Contract::binding_block_names() as $block ) {
			self::assertTrue( Email_Block_Contract::is_core( $block ), $block );
			foreach ( Email_Block_Contract::binding_attribute_names( $block ) as $attribute ) {
				$rule = Email_Block_Contract::binding( $block, $attribute );
				self::assertIsArray( $rule );
				self::assertContains( $rule['projection'], array( 'rich-text', 'url' ) );
				foreach ( $rule['fields'] as $name => $field ) {
					self::assertSame( $field, Email_Block_Contract::binding_field( $block, $attribute, (string) $name ) );
					self::assertContains( $field['reads'], $supported, $block . '.' . $attribute . '.' . $name );
					if ( null !== $field['link'] ) {
						self::assertContains( $field['link'], $supported, $block . '.' . $attribute . '.' . $name );
					}
				}
			}
		}
	}

	public function test_brand_logo_bindings_are_bounded_to_core_image(): void {
		self::assertSame( 'campaignbridge/brand-data', Email_Block_Contract::brand_binding_source() );
		self::assertSame( array( 'core/image' ), Email_Block_Contract::brand_binding_block_names() );
		self::assertSame( array( 'url', 'alt', 'href' ), Email_Block_Contract::brand_binding_attribute_names( 'core/image' ) );
		self::assertSame( array( 'field' => 'logoUrl', 'target' => 'url' ), Email_Block_Contract::brand_binding( 'core/image', 'url' ) );
		self::assertSame( array( 'field' => 'logoAlt', 'target' => 'alt' ), Email_Block_Contract::brand_binding( 'core/image', 'alt' ) );
		self::assertSame( array( 'field' => 'logoLink', 'target' => 'linkUrl' ), Email_Block_Contract::brand_binding( 'core/image', 'href' ) );
	}

	public function test_every_bound_attribute_is_bindable_in_wordpress_core(): void {
		// The saved binding is only honoured for attributes WordPress itself
		// declares bindable; binding anything else would be a private contract.
		foreach ( Email_Block_Contract::binding_block_names() as $block ) {
			$bindable = get_block_bindings_supported_attributes( $block );
			foreach ( Email_Block_Contract::binding_attribute_names( $block ) as $attribute ) {
				self::assertContains( $attribute, $bindable, $block . '.' . $attribute );
			}
		}
	}

	public function test_renderers_that_accept_bindings_declare_the_binding_attribute(): void {
		$registry = Compiler_Factory::registry();
		foreach ( Email_Block_Contract::names() as $name ) {
			$renderer = $registry->get( $name );
			self::assertNotNull( $renderer, $name );
			self::assertSame(
				in_array( $name, Email_Block_Contract::binding_block_names(), true ),
				in_array( 'postBindings', $renderer->attribute_names(), true ),
				$name
			);
		}
	}

	public function test_brand_bound_renderer_declares_its_canonical_binding_attribute(): void {
		$renderer = Compiler_Factory::registry()->get( 'core/image' );

		self::assertNotNull( $renderer );
		self::assertContains( 'brandBindings', $renderer->attribute_names() );
	}

	public function test_email_compilation_never_uses_frontend_block_rendering(): void {
		$root  = dirname( __DIR__, 3 ) . '/includes/';
		$files = array_merge(
			glob( $root . 'Workflow/Email/*.php' ) ?: array(),
			glob( $root . 'Services/Email/*.php' ) ?: array(),
			glob( $root . 'Services/Email/Renderer/*.php' ) ?: array(),
			glob( $root . 'Domain/Email/*.php' ) ?: array()
		);
		self::assertNotEmpty( $files );
		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file );
			foreach ( array( 'render_block(', 'do_blocks(', "'the_content'", 'wp_get_global_styles(', 'wp_get_global_stylesheet(' ) as $forbidden ) {
				self::assertStringNotContainsString( $forbidden, $source, basename( $file ) . ' must not use ' . $forbidden );
			}
		}
	}
}
