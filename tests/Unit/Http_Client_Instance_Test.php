<?php
/**
 * Unit tests for Http_Client_Instance class.
 *
 * @package CampaignBridge\Tests\Unit
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort -- PHPUnit test class
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace -- Namespace declared below
// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit test file
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged -- PHPUnit assertions in tests

/**
 * Suppress false positives for PHPUnit assertion methods in static analysis.
 * The linter doesn't recognize dynamic PHPUnit methods, but they exist at runtime.
 *
 * @method void assertInstanceOf(string $class, mixed $object, string $message = '')
 * @method void assertTrue(bool $condition, string $message = '')
 */

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Http_Client;
use CampaignBridge\Core\Http_Client_Instance;
use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Test Http_Client_Instance functionality.
 *
 * @method void assertInstanceOf(string $class, mixed $object, string $message = '')
 * @method void assertTrue(bool $condition, string $message = '')
 */
class _Http_Client_Instance_Test extends Test_Case {

	/**
	 * Instance under test.
	 *
	 * @var Http_Client_Instance
	 */
	private Http_Client_Instance $http_client;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->http_client = new Http_Client_Instance();
	}

	/**
	 * Test that Http_Client_Instance implements the interface.
	 */
	public function test_implements_interface(): void {
		$this->assertInstanceOf( Http_Client_Interface::class, $this->http_client ); // phpcs:ignore
	}

	/**
	 * Test that factory method creates instance.
	 */
	public function test_factory_method_creates_instance(): void {
		$instance = Http_Client::create_instance();

		$this->assertInstanceOf( Http_Client_Instance::class, $instance ); // phpcs:ignore
		$this->assertInstanceOf( Http_Client_Interface::class, $instance ); // phpcs:ignore
	}

	/**
	 * Test that all interface methods exist and are callable.
	 */
	public function test_interface_methods_exist(): void {
		$methods = array( 'post', 'get', 'put', 'delete' );

		foreach ( $methods as $method ) {
			$this->assertTrue( method_exists( $this->http_client, $method ), "Method {$method} should exist" ); // phpcs:ignore
			$this->assertTrue( is_callable( array( $this->http_client, $method ) ), "Method {$method} should be callable" ); // phpcs:ignore
		}
	}

	/**
	 * Test that instance can be used polymorphically.
	 */
	public function test_polymorphic_usage(): void {
		$instance = Http_Client::create_instance();

		$test_function = function ( Http_Client_Interface $client ) {
			return $client instanceof Http_Client_Interface;
		};

		$this->assertTrue( $test_function( $instance ), 'Instance should work polymorphically via interface' ); // phpcs:ignore
	}
}
