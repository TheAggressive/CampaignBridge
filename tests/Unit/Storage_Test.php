<?php
/**
 * Tests for Storage class
 *
 * @package CampaignBridge
 * @subpackage Tests\Unit
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Storage;
use WP_UnitTestCase;

/**
 * Test Storage functionality
 */
class Storage_Test extends WP_UnitTestCase {
	/**
	 * Test data for storage operations
	 */
	private array $test_data = array(
		'string_value' => 'test_string',
		'int_value'    => 42,
		'float_value'  => 3.14,
		'bool_value'   => true,
		'array_value'  => array(
			'key'    => 'value',
			'nested' => array( 'data' => 'here' ),
		),
		'null_value'   => null,
	);

	/**
	 * Test admin user ID for cleanup
	 */
	private int $test_admin_user_id;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up admin user for testing (Storage operations may require admin privileges)
		$this->test_admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->test_admin_user_id );
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up test admin user
		if ( isset( $this->test_admin_user_id ) ) {
			wp_delete_user( $this->test_admin_user_id );
		}

		// Clean up test options and transients
		foreach ( array_keys( $this->test_data ) as $key ) {
			Storage::delete_option( $key );
			Storage::delete_transient( $key );
			// Also clean up direct WordPress calls in case Storage wrapper fails
			delete_option( 'campaignbridge_' . $key );
			delete_transient( 'campaignbridge_' . $key );
		}

		// Clean up additional test keys
		$cleanup_keys = array( 'new_option_test', 'delete_test' );
		foreach ( $cleanup_keys as $key ) {
			Storage::delete_option( $key );
			delete_option( 'campaignbridge_' . $key );
		}

		// Clean up test post meta for all posts
		$posts = get_posts(
			array(
				'numberposts' => -1,
				'post_status' => 'any',
			)
		);
		foreach ( $posts as $post ) {
			foreach ( array_keys( $this->test_data ) as $key ) {
				delete_post_meta( $post->ID, 'campaignbridge_' . $key );
			}
		}

		// Clean up test user meta for all users
		$users = get_users( array( 'number' => -1 ) );
		foreach ( $users as $user ) {
			foreach ( array_keys( $this->test_data ) as $key ) {
				delete_user_meta( $user->ID, 'campaignbridge_' . $key );
			}
		}

		// Clean up cache groups
		wp_cache_delete( 'test_cache_key', 'test_group' );
		wp_cache_delete( 'nonexistent_cache', 'test_group' );
	}

	/**
	 * Test option storage operations
	 */
	public function test_option_operations(): void {
		// Test storing different data types
		foreach ( $this->test_data as $key => $value ) {
			$result = Storage::update_option( $key, $value );
			$this->assertTrue( $result, "Failed to store option: $key" );

			$retrieved = Storage::get_option( $key );
			$this->assertEquals( $value, $retrieved, "Failed to retrieve option: $key" );
		}
	}

	/**
	 * Test option addition (only if not exists)
	 */
	public function test_add_option(): void {
		$key   = 'new_option_test';
		$value = 'new_value';

		// First add should succeed
		$result = Storage::add_option( $key, $value );
		$this->assertTrue( $result );

		// Verify value
		$this->assertEquals( $value, Storage::get_option( $key ) );

		// Second add should fail (option already exists)
		$result = Storage::add_option( $key, 'different_value' );
		$this->assertFalse( $result );

		// Value should remain unchanged
		$this->assertEquals( $value, Storage::get_option( $key ) );
	}

	/**
	 * Test option deletion
	 */
	public function test_delete_option(): void {
		$key   = 'delete_test';
		$value = 'to_be_deleted';

		// Store option
		Storage::update_option( $key, $value );
		$this->assertEquals( $value, Storage::get_option( $key ) );

		// Delete option
		$result = Storage::delete_option( $key );
		$this->assertTrue( $result );

		// Verify deletion
		$this->assertFalse( Storage::get_option( $key ) );
	}

	/**
	 * Test option default values
	 */
	public function test_option_defaults(): void {
		$nonexistent_key = 'nonexistent_option';
		$default_value   = 'default_value';

		$result = Storage::get_option( $nonexistent_key, $default_value );
		$this->assertEquals( $default_value, $result );
	}

	/**
	 * Test transient storage operations
	 */
	public function test_transient_operations(): void {
		// Test storing different data types
		foreach ( $this->test_data as $key => $value ) {
			$result = Storage::set_transient( $key, $value, 3600 );
			$this->assertTrue( $result, "Failed to store transient: $key" );

			$retrieved = Storage::get_transient( $key );
			$this->assertEquals( $value, $retrieved, "Failed to retrieve transient: $key" );
		}
	}

	/**
	 * Test transient expiration
	 */
	public function test_transient_expiration(): void {
		$key   = 'expiring_transient';
		$value = 'will_expire';

		// Set transient with very short expiration (1 second)
		Storage::set_transient( $key, $value, 1 );

		// Verify it exists immediately
		$this->assertEquals( $value, Storage::get_transient( $key ) );

		// Wait for expiration
		sleep( 2 );

		// Verify it's expired
		$this->assertFalse( Storage::get_transient( $key ) );
	}

	/**
	 * Test transient deletion
	 */
	public function test_delete_transient(): void {
		$key   = 'delete_transient_test';
		$value = 'to_be_deleted';

		// Store transient
		Storage::set_transient( $key, $value, 3600 );
		$this->assertEquals( $value, Storage::get_transient( $key ) );

		// Delete transient
		$result = Storage::delete_transient( $key );
		$this->assertTrue( $result );

		// Verify deletion
		$this->assertFalse( Storage::get_transient( $key ) );
	}

	/**
	 * Test post meta operations
	 */
	public function test_post_meta_operations(): void {
		$post_id = $this->factory->post->create();

		// Test storing different data types
		foreach ( $this->test_data as $key => $value ) {
			$result = Storage::update_post_meta( $post_id, $key, $value );
			$this->assertIsNumeric( $result, "Failed to store post meta: $key" );

			$retrieved = Storage::get_post_meta( $post_id, $key, true );
			$this->assertEquals( $value, $retrieved, "Failed to retrieve post meta: $key" );
		}
	}

	/**
	 * Test post meta addition
	 */
	public function test_add_post_meta(): void {
		$post_id = $this->factory->post->create();
		$key     = 'unique_meta';
		$value   = 'unique_value';

		// First add should succeed
		$result = Storage::add_post_meta( $post_id, $key, $value, true );
		$this->assertIsNumeric( $result );

		// Verify value
		$this->assertEquals( $value, Storage::get_post_meta( $post_id, $key, true ) );

		// Second add with unique=true should fail
		$result = Storage::add_post_meta( $post_id, $key, 'different_value', true );
		$this->assertFalse( $result );

		// Value should remain unchanged
		$this->assertEquals( $value, Storage::get_post_meta( $post_id, $key, true ) );
	}

	/**
	 * Test post meta deletion
	 */
	public function test_delete_post_meta(): void {
		$post_id = $this->factory->post->create();
		$key     = 'delete_meta_test';
		$value   = 'to_be_deleted';

		// Store meta
		Storage::update_post_meta( $post_id, $key, $value );
		$this->assertEquals( $value, Storage::get_post_meta( $post_id, $key, true ) );

		// Delete meta
		$result = Storage::delete_post_meta( $post_id, $key );
		$this->assertTrue( $result );

		// Verify deletion
		$this->assertEquals( '', Storage::get_post_meta( $post_id, $key, true ) );
	}

	/**
	 * Test user meta operations
	 */
	public function test_user_meta_operations(): void {
		$user_id = $this->factory->user->create();

		// Test storing different data types
		foreach ( $this->test_data as $key => $value ) {
			$result = Storage::update_user_meta( $user_id, $key, $value );
			$this->assertNotFalse( $result, "Failed to store user meta: $key" );

			$retrieved = Storage::get_user_meta( $user_id, $key, true );
			$this->assertEquals( $value, $retrieved, "Failed to retrieve user meta: $key" );
		}
	}

	/**
	 * Test user meta deletion
	 */
	public function test_delete_user_meta(): void {
		$user_id = $this->factory->user->create();
		$key     = 'delete_user_meta_test';
		$value   = 'to_be_deleted';

		// Store meta
		Storage::update_user_meta( $user_id, $key, $value );
		$this->assertEquals( $value, Storage::get_user_meta( $user_id, $key, true ) );

		// Delete meta
		$result = Storage::delete_user_meta( $user_id, $key );
		$this->assertTrue( $result );

		// Verify deletion
		$this->assertEquals( '', Storage::get_user_meta( $user_id, $key, true ) );
	}

	/**
	 * Test batch post meta operations
	 */
	public function test_batch_post_meta_operations(): void {
		$post_id   = $this->factory->post->create();
		$meta_data = array(
			'batch_key1' => 'batch_value1',
			'batch_key2' => 'batch_value2',
			'batch_key3' => array( 'nested' => 'data' ),
		);

		// Batch update
		$result = Storage::update_post_metas( $post_id, $meta_data );
		$this->assertTrue( $result );

		// Verify all values were stored
		foreach ( $meta_data as $key => $value ) {
			$this->assertEquals( $value, Storage::get_post_meta( $post_id, $key, true ) );
		}
	}

	/**
	 * Test meta value sanitization
	 */
	public function test_meta_value_sanitization(): void {
		$post_id = $this->factory->post->create();

		// Test different data types that should be sanitized
		$test_cases = array(
			'string'  => 'test_string',
			'integer' => 42,
			'float'   => 3.14,
			'boolean' => true,
			'array'   => array( 'key' => 'value' ),
			'object'  => (object) array( 'property' => 'value' ),
		);

		foreach ( $test_cases as $type => $value ) {
			Storage::update_post_meta( $post_id, "sanitize_test_$type", $value );
			$retrieved = Storage::get_post_meta( $post_id, "sanitize_test_$type", true );

			// Values should be properly stored and retrieved
			$this->assertEquals( $value, $retrieved, "Failed to sanitize $type" );
		}
	}

	/**
	 * Test cache operations
	 */
	public function test_cache_operations(): void {
		$key   = 'cache_test';
		$value = 'cached_value';
		$group = 'test_group';

		// Store in cache
		$result = Storage::set_cache( $key, $value, $group, 3600 );
		$this->assertTrue( $result );

		// Retrieve from cache
		$retrieved = Storage::get_cache( $key, $group );
		$this->assertEquals( $value, $retrieved );

		// Delete from cache
		$result = Storage::delete_cache( $key, $group );
		$this->assertTrue( $result );

		// Verify deletion
		$this->assertFalse( Storage::get_cache( $key, $group ) );
	}

	/**
	 * Test cache with default values
	 */
	public function test_cache_defaults(): void {
		$key     = 'nonexistent_cache';
		$group   = 'test_group';
		$default = 'default_value';

		$result = Storage::get_cache( $key, $group, $default );
		$this->assertEquals( $default, $result );
	}
}
