<?php
/**
 * Performance Tests for CampaignBridge Plugin.
 *
 * Tests real performance-critical code paths to ensure they meet performance budgets.
 * Measures execution time, memory usage, and database query performance for key operations.
 *
 * @package CampaignBridge\Tests\Performance
 * @since 1.0.0
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Performance;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Services\Email_Generator;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;

/**
 * Performance Test Suite
 *
 * Tests performance of real CampaignBridge code paths with realistic data sets.
 */
class Performance_Test extends Test_Case {

	/**
	 * Performance budgets (in milliseconds)
	 */
	private const PERFORMANCE_BUDGETS = array(
		'email_generation_simple'  => 500,  // 500ms for simple email generation
		'email_generation_complex' => 2000, // 2s for complex templates
		'rest_api_posts_small'     => 100,  // 100ms for small post queries
		'rest_api_posts_large'     => 500,  // 500ms for large post queries
		'form_processing_small'    => 200,  // 200ms for small forms
		'form_processing_large'    => 1000, // 1s for large forms
		'block_registration'       => 300,  // 300ms for block registration
	);

	/**
	 * Memory usage budgets (in MB)
	 */
	private const MEMORY_BUDGETS = array(
		'email_generation_simple'  => 16,   // 16MB for simple generation
		'email_generation_complex' => 32,   // 32MB for complex templates
		'rest_api_posts_large'     => 8,    // 8MB for post queries
		'form_processing_large'    => 16,   // 16MB for large forms
	);

	public function setUp(): void {
		parent::setUp();

		// Set up admin user for performance tests
		$this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->cleanup_performance_test_data();
	}

	/**
	 * Test email generation performance with simple template.
	 */
	public function test_email_generation_performance_simple(): void {
		$blocks = $this->create_simple_email_blocks();

		$start_time   = microtime( true );
		$start_memory = memory_get_peak_usage( true );

		$result = Email_Generator::generate_email_html( $blocks );

		$end_time   = microtime( true );
		$end_memory = memory_get_peak_usage( true );

		$execution_time = ( $end_time - $start_time ) * 1000; // Convert to milliseconds
		$memory_usage   = ( $end_memory - $start_memory ) / 1024 / 1024; // Convert to MB

		// Assert performance budget
		$this->assertLessThan(
			self::PERFORMANCE_BUDGETS['email_generation_simple'],
			$execution_time,
			sprintf(
				'Email generation took %.2fms, exceeding budget of %dms',
				$execution_time,
				self::PERFORMANCE_BUDGETS['email_generation_simple']
			)
		);

		// Assert memory budget
		$this->assertLessThan(
			self::MEMORY_BUDGETS['email_generation_simple'],
			$memory_usage,
			sprintf(
				'Email generation used %.2fMB, exceeding budget of %dMB',
				$memory_usage,
				self::MEMORY_BUDGETS['email_generation_simple']
			)
		);

		// Assert result is valid HTML
		$this->assertStringContainsString( '<html', $result );
		$this->assertStringContainsString( '</html>', $result );
		$this->assertGreaterThan( 1000, strlen( $result ) ); // Should be substantial HTML
	}

	/**
	 * Test email generation performance with complex template.
	 */
	public function test_email_generation_performance_complex(): void {
		$blocks = $this->create_complex_email_blocks();

		$start_time   = microtime( true );
		$start_memory = memory_get_peak_usage( true );

		$result = Email_Generator::generate_email_html( $blocks );

		$end_time   = microtime( true );
		$end_memory = memory_get_peak_usage( true );

		$execution_time = ( $end_time - $start_time ) * 1000;
		$memory_usage   = ( $end_memory - $start_memory ) / 1024 / 1024;

		// Assert performance budget
		$this->assertLessThan(
			self::PERFORMANCE_BUDGETS['email_generation_complex'],
			$execution_time,
			sprintf(
				'Complex email generation took %.2fms, exceeding budget of %dms',
				$execution_time,
				self::PERFORMANCE_BUDGETS['email_generation_complex']
			)
		);

		// Assert memory budget
		$this->assertLessThan(
			self::MEMORY_BUDGETS['email_generation_complex'],
			$memory_usage,
			sprintf(
				'Complex email generation used %.2fMB, exceeding budget of %dMB',
				$memory_usage,
				self::MEMORY_BUDGETS['email_generation_complex']
			)
		);

		// Assert result contains expected complex elements
		$this->assertStringContainsString( 'Welcome to Our Newsletter', $result ); // From heading block
		$this->assertStringContainsString( 'Post Title', $result ); // From post-card block
	}

	/**
	 * Test REST API performance with small dataset.
	 */
	public function test_rest_api_performance_small_dataset(): void {
		// Create small number of test posts
		$this->create_test_posts( 10 );

		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$request->set_param( 'post_type', 'post' );

		$start_time   = microtime( true );
		$start_memory = memory_get_peak_usage( true );

		$response = rest_do_request( $request );

		$end_time   = microtime( true );
		$end_memory = memory_get_peak_usage( true );

		$execution_time = ( $end_time - $start_time ) * 1000;

		$this->assertLessThan(
			self::PERFORMANCE_BUDGETS['rest_api_posts_small'],
			$execution_time,
			sprintf(
				'REST API query took %.2fms, exceeding budget of %dms',
				$execution_time,
				self::PERFORMANCE_BUDGETS['rest_api_posts_small']
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'items', $response->get_data() );
	}

	/**
	 * Test REST API performance with large dataset.
	 */
	public function test_rest_api_performance_large_dataset(): void {
		// Create large number of test posts
		$this->create_test_posts( 100 );

		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$request->set_param( 'post_type', 'post' );

		$start_time   = microtime( true );
		$start_memory = memory_get_peak_usage( true );

		$response = rest_do_request( $request );

		$end_time   = microtime( true );
		$end_memory = memory_get_peak_usage( true );

		$execution_time = ( $end_time - $start_time ) * 1000;
		$memory_usage   = ( $end_memory - $start_memory ) / 1024 / 1024;

		$this->assertLessThan(
			self::PERFORMANCE_BUDGETS['rest_api_posts_large'],
			$execution_time,
			sprintf(
				'Large REST API query took %.2fms, exceeding budget of %dms',
				$execution_time,
				self::PERFORMANCE_BUDGETS['rest_api_posts_large']
			)
		);

		$this->assertLessThan(
			self::MEMORY_BUDGETS['rest_api_posts_large'],
			$memory_usage,
			sprintf(
				'REST API query used %.2fMB, exceeding budget of %dMB',
				$memory_usage,
				self::MEMORY_BUDGETS['rest_api_posts_large']
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'items', $data );
		$this->assertLessThanOrEqual( 100, count( $data['items'] ) ); // Should return all 100 posts
	}

	/**
	 * Test form processing performance with small form.
	 */
	public function test_form_processing_performance_small(): void {
		// Set up admin user and context
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		set_current_screen( 'toplevel_page_campaignbridge' );

		$form = Form::make( 'perf_test_small' )
			->text( 'name' )->required()
			->email( 'email' )->required()
			->textarea( 'message' )
			->save_to_options( 'perf_test_' );

		$_POST['perf_test_small']         = array(
			'name'    => 'Test User',
			'email'   => 'test@example.com',
			'message' => 'This is a test message',
		);
		$_SERVER['REQUEST_METHOD']        = 'POST';
		$_POST['perf_test_small_wpnonce'] = wp_create_nonce( 'campaignbridge_form_perf_test_small' );

		$start_time   = microtime( true );
		$start_memory = memory_get_peak_usage( true );

		$form->render(); // This triggers form processing

		$end_time   = microtime( true );
		$end_memory = memory_get_peak_usage( true );

		$execution_time = ( $end_time - $start_time ) * 1000;

		$this->assertLessThan(
			self::PERFORMANCE_BUDGETS['form_processing_small'],
			$execution_time,
			sprintf(
				'Small form processing took %.2fms, exceeding budget of %dms',
				$execution_time,
				self::PERFORMANCE_BUDGETS['form_processing_small']
			)
		);

		$this->assertTrue( $form->submitted() );
		$this->assertTrue( $form->valid() );
	}

	/**
	 * Test form processing performance with large form.
	 */
	public function test_form_processing_performance_large(): void {
		// Set up admin user and context
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		set_current_screen( 'toplevel_page_campaignbridge' );

		$form = Form::make( 'perf_test_large' )
			->text( 'name' )->required()
			->email( 'email' )->required();

		// Add many fields to simulate large form
		for ( $i = 1; $i <= 20; $i++ ) {
			$form->text( "field_{$i}" )->rules( array( 'required' ) );
		}

		$form->textarea( 'description' )->rules( array( 'required', 'min_length:10' ) );
		$form->save_to_options( 'perf_test_large_' );

		// Create form data with all fields
		$form_data = array(
			'name'        => 'Test User',
			'email'       => 'test@example.com',
			'description' => 'This is a detailed description that meets the minimum length requirement for testing.',
		);

		for ( $i = 1; $i <= 20; $i++ ) {
			$form_data[ "field_{$i}" ] = "Value {$i}";
		}

		$_POST['perf_test_large']         = $form_data;
		$_SERVER['REQUEST_METHOD']        = 'POST';
		$_POST['perf_test_large_wpnonce'] = wp_create_nonce( 'campaignbridge_form_perf_test_large' );

		$start_time   = microtime( true );
		$start_memory = memory_get_peak_usage( true );

		$form->render(); // This triggers form processing

		$end_time   = microtime( true );
		$end_memory = memory_get_peak_usage( true );

		$execution_time = ( $end_time - $start_time ) * 1000;
		$memory_usage   = ( $end_memory - $start_memory ) / 1024 / 1024;

		$this->assertLessThan(
			self::PERFORMANCE_BUDGETS['form_processing_large'],
			$execution_time,
			sprintf(
				'Large form processing took %.2fms, exceeding budget of %dms',
				$execution_time,
				self::PERFORMANCE_BUDGETS['form_processing_large']
			)
		);

		$this->assertLessThan(
			self::MEMORY_BUDGETS['form_processing_large'],
			$memory_usage,
			sprintf(
				'Large form processing used %.2fMB, exceeding budget of %dMB',
				$memory_usage,
				self::MEMORY_BUDGETS['form_processing_large']
			)
		);

		$this->assertTrue( $form->submitted() );
		$this->assertTrue( $form->valid() );
	}

	/**
	 * Test block registration performance.
	 */
	public function test_block_registration_performance(): void {
		// Reset block registry for clean test
		if ( function_exists( 'WP_Block_Type_Registry::get_instance' ) ) {
			$registry = WP_Block_Type_Registry::get_instance();
			// Clear existing blocks (this is test-specific)
			$reflection = new \ReflectionClass( $registry );
			$property   = $reflection->getProperty( 'registered_block_types' );
			$property->setAccessible( true );
			$property->setValue( $registry, array() );
		}

		$start_time = microtime( true );

		// Trigger block registration (this happens during plugin init)
		if ( function_exists( 'CampaignBridge_Plugin::init' ) ) {
			// Blocks are registered during init, so measure that time
			do_action( 'init' );
		}

		$end_time       = microtime( true );
		$execution_time = ( $end_time - $start_time ) * 1000;

		$this->assertLessThan(
			self::PERFORMANCE_BUDGETS['block_registration'],
			$execution_time,
			sprintf(
				'Block registration took %.2fms, exceeding budget of %dms',
				$execution_time,
				self::PERFORMANCE_BUDGETS['block_registration']
			)
		);
	}

	/**
	 * Create simple email blocks for testing.
	 */
	private function create_simple_email_blocks(): array {
		return array(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerContent' => array( '<p>Hello World</p>' ),
				'innerBlocks'  => array(),
			),
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerContent' => array( '<p>This is a simple email template.</p>' ),
				'innerBlocks'  => array(),
			),
		);
	}

	/**
	 * Create complex email blocks for testing.
	 */
	private function create_complex_email_blocks(): array {
		return array(
			array(
				'blockName'    => 'campaignbridge/container',
				'attrs'        => array(
					'backgroundColor' => '#f0f0f0',
					'padding'         => '20px',
				),
				'innerContent' => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/heading',
						'attrs'        => array( 'level' => 1 ),
						'innerContent' => array( '<h1>Welcome to Our Newsletter</h1>' ),
						'innerBlocks'  => array(),
					),
					array(
						'blockName'    => 'campaignbridge/post-card',
						'attrs'        => array(
							'postId'      => 1,
							'showImage'   => true,
							'showExcerpt' => true,
						),
						'innerContent' => array(),
						'innerBlocks'  => array(),
					),
					array(
						'blockName'    => 'core/buttons',
						'attrs'        => array(),
						'innerContent' => array(),
						'innerBlocks'  => array(
							array(
								'blockName'    => 'core/button',
								'attrs'        => array(
									'url'  => 'https://example.com',
									'text' => 'Read More',
								),
								'innerContent' => array(),
								'innerBlocks'  => array(),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Create test posts for performance testing.
	 */
	private function create_test_posts( int $count ): array {
		$post_ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$post_ids[] = wp_insert_post(
				array(
					'post_title'   => "Performance Test Post {$i}",
					'post_content' => 'This is test content for performance testing. ' . str_repeat( 'More content. ', 50 ),
					'post_status'  => 'publish',
					'post_type'    => 'post',
				)
			);
		}

		return $post_ids;
	}

	/**
	 * Clean up performance test data.
	 */
	private function cleanup_performance_test_data(): void {
		// Clean up test posts
		$test_posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'title'          => 'Performance Test Post%',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $test_posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		// Clean up test options
		$option_keys = array(
			'perf_test_name',
			'perf_test_email',
			'perf_test_message',
		);

		foreach ( $option_keys as $key ) {
			delete_option( $key );
		}

		// Clean up large form options
		for ( $i = 1; $i <= 20; $i++ ) {
			delete_option( "perf_test_large_field_{$i}" );
		}
		delete_option( 'perf_test_large_name' );
		delete_option( 'perf_test_large_email' );
		delete_option( 'perf_test_large_description' );
	}
}
