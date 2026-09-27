<?php
/**
 * Brand kit REST route tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;

final class Brand_Kit_Routes_Test extends Test_Case {
	private const ROUTE       = '/campaignbridge/v1/brand-kit';
	private const FONTS_ROUTE = '/campaignbridge/v1/brand-kit/fonts';

	public function setUp(): void {
		parent::setUp();
		do_action( 'rest_api_init' );
		Routes::register();
		( new Brand_Kit_Repository() )->clear();
	}

	public function tearDown(): void {
		( new Brand_Kit_Repository() )->clear();
		parent::tearDown();
	}

	public function test_the_route_is_registered(): void {
		$this->assertArrayHasKey( self::ROUTE, rest_get_server()->get_routes() );
		$this->assertArrayHasKey( self::FONTS_ROUTE, rest_get_server()->get_routes() );
	}

	public function test_font_updates_use_the_focused_fonts_route(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'PUT', self::FONTS_ROUTE );
		$request->set_param( 'fonts', array( 'heading' => 'inter' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'inter', ( new Brand_Kit_Repository() )->get()->font( 'heading' ) );
	}

	public function test_font_updates_reject_unknown_slots_without_saving(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		$before = ( new Brand_Kit_Repository() )->get()->to_array();

		$request = new WP_REST_Request( 'PUT', self::FONTS_ROUTE );
		$request->set_param( 'fonts', array( 'madeUpSlot' => 'arial' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( $before, ( new Brand_Kit_Repository() )->get()->to_array() );
	}

	public function test_font_updates_reject_non_string_slot_values_without_saving(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		$before = ( new Brand_Kit_Repository() )->get()->to_array();

		$request = new WP_REST_Request( 'PUT', self::FONTS_ROUTE );
		$request->set_param( 'fonts', array( 'heading' => array( 'slug' => 'arial' ) ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( $before, ( new Brand_Kit_Repository() )->get()->to_array() );
	}

	public function test_adds_multiple_google_fonts_and_assigns_each_to_a_type_slot(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$repository = new Brand_Kit_Repository();
		foreach ( array( 'Roboto', 'Lora' ) as $family ) {
			$request = new WP_REST_Request( 'PUT', self::FONTS_ROUTE );
			$request->set_param( 'fonts', $repository->get()->fonts() );
			$request->set_param( 'customFontFamily', $family );
			$response = rest_get_server()->dispatch( $request );
			self::assertSame( 200, $response->get_status(), $family );
		}

		$roboto = Brand_Kit::custom_font_slug( 'Roboto' );
		$lora   = Brand_Kit::custom_font_slug( 'Lora' );
		$kit    = $repository->get();
		self::assertCount( 2, $kit->custom_fonts() );
		self::assertSame( array( $roboto, $lora ), array_column( $kit->custom_fonts(), 'slug' ) );

		$duplicate = new WP_REST_Request( 'PUT', self::FONTS_ROUTE );
		$duplicate->set_param( 'fonts', $kit->fonts() );
		$duplicate->set_param( 'customFontFamily', 'Roboto' );
		self::assertSame( 200, rest_get_server()->dispatch( $duplicate )->get_status() );
		self::assertCount( 2, $repository->get()->custom_fonts() );

		$request = new WP_REST_Request( 'PUT', self::FONTS_ROUTE );
		$request->set_param(
			'fonts',
			array(
				'heading' => $lora,
				'body'    => $roboto,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertSame( $lora, $repository->get()->font( 'heading' ) );
		self::assertSame( $roboto, $repository->get()->font( 'body' ) );
		self::assertContains( $lora, array_column( $data['fontOptions'], 'slug' ) );
		self::assertContains( $roboto, array_column( $data['fontOptions'], 'slug' ) );
	}

	public function test_rejects_a_thirteenth_custom_font_without_changing_the_kit(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$custom = array();
		for ( $index = 0; $index < Brand_Kit::MAX_CUSTOM_FONTS; ++$index ) {
			$name     = 'Example Font ' . $index;
			$custom[] = array(
				'slug'    => Brand_Kit::custom_font_slug( $name ),
				'name'    => $name,
				'family'  => $name . ',Arial,Helvetica,sans-serif',
				'weights' => array( 400 ),
				'url'     => 'https://fonts.googleapis.com/css2?family=Example+Font+' . $index . ':wght@400&display=swap',
			);
		}
		$repository = new Brand_Kit_Repository();
		$repository->save( Brand_Kit::from_colors( array(), Brand_Kit::SOURCE_CUSTOM, null, null, $custom ) );

		$request = new WP_REST_Request( 'PUT', self::FONTS_ROUTE );
		$request->set_param( 'fonts', $repository->get()->fonts() );
		$request->set_param( 'customFontFamily', 'Roboto' );
		$response = rest_get_server()->dispatch( $request );

		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'custom_font_limit_reached', $response->get_data()['code'] ?? null );
		self::assertCount( Brand_Kit::MAX_CUSTOM_FONTS, $repository->get()->custom_fonts() );
	}

	public function test_get_returns_the_seven_slots(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$request  = new WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Brand_Kit::SOURCE_DEFAULTS, $data['source'] );
		$this->assertCount( 7, $data['slots'] );
		$this->assertSame( Brand_Kit::SLOTS, array_column( $data['slots'], 'id' ) );
	}

	public function test_put_updates_one_slot_and_marks_the_kit_custom(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'id', Brand_Kit::SLOT_BRAND );
		$request->set_param( 'color', '#ff5500' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Brand_Kit::SOURCE_CUSTOM, $data['source'] );

		$brand = null;
		foreach ( $data['slots'] as $slot ) {
			if ( Brand_Kit::SLOT_BRAND === $slot['id'] ) {
				$brand = $slot['color'];
			}
		}

		$this->assertSame( '#ff5500', $brand );
		$this->assertSame( '#ff5500', ( new Brand_Kit_Repository() )->get()->color( Brand_Kit::SLOT_BRAND ) );
	}

	public function test_color_and_font_updates_preserve_the_frozen_logo(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		$logo       = array(
			'url'      => 'https://cdn.example.com/logo.png',
			'alt'      => 'Example',
			'width'    => 800,
			'height'   => 240,
			'link_url' => 'https://example.com/',
		);
		$repository = new Brand_Kit_Repository();
		$repository->save( Brand_Kit::from_colors( array(), Brand_Kit::SOURCE_THEME, null, null, null, $logo ) );

		self::assertSame( 200, $this->put_color( Brand_Kit::SLOT_BRAND, '#123456' )->get_status() );
		$request = new WP_REST_Request( 'PUT', self::FONTS_ROUTE );
		$request->set_param( 'fonts', array( 'heading' => 'inter' ) );
		self::assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
		self::assertSame( $logo, $repository->get()->logo() );
	}

	public function test_put_rejects_a_colour_that_is_not_portable(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'id', Brand_Kit::SLOT_BRAND );
		$request->set_param( 'color', 'oklch(0.7 0.1 200)' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_writing_the_same_colour_twice_is_not_a_server_error(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$first  = $this->put_color( Brand_Kit::SLOT_BRAND, '#123456' );
		$second = $this->put_color( Brand_Kit::SLOT_BRAND, '#123456' );

		self::assertSame( 200, $first->get_status(), 'first write' );
		self::assertSame( 200, $second->get_status(), 'identical rewrite' );
	}

	/**
	 * Dispatch a brand colour update.
	 *
	 * @param string $slug  Slot slug.
	 * @param string $color Six-digit colour.
	 */
	private function put_color( string $slug, string $color ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'PUT', '/campaignbridge/v1/brand-kit' );
		$request->set_param( 'id', $slug );
		$request->set_param( 'color', $color );

		return rest_get_server()->dispatch( $request );
	}
}
