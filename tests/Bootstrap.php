<?php
/**
 * PHPUnit Test Bootstrap for CampaignBridge.
 *
 * @package CampaignBridge\Tests
 */

namespace CampaignBridge\Tests;

/**
 * Test Bootstrap Class for setting up WordPress testing environment.
 */
class Bootstrap {

	/**
	 * WordPress tests directory.
	 *
	 * @var string
	 */
	private string $tests_dir;

	/**
	 * Plugin directory.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_dir = dirname( __DIR__ );
		$this->tests_dir  = $this->get_wp_tests_dir();
	}

	/**
	 * Initialize the test environment.
	 */
	public function init(): void {
		$this->load_composer_dependencies();
		$this->setup_phpunit_polyfills();
		$this->validate_tests_directory();
		$this->load_wp_functions();
		$this->register_plugin_loader();
		$this->load_wp_bootstrap();
		$this->load_test_helpers();
	}

	/**
	 * Get WordPress tests directory.
	 *
	 * @return string
	 */
	private function get_wp_tests_dir(): string {
		$tests_dir = getenv( 'WP_TESTS_DIR' );

		// In wp-env, try the default location first
		if ( ! $tests_dir ) {
			$tests_dir = '/wordpress-phpunit';
		}

		// Fallback to temp directory
		if ( ! file_exists( $tests_dir ) ) {
			$tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
		}

		return $tests_dir;
	}

	/**
	 * Load Composer dependencies.
	 */
	private function load_composer_dependencies(): void {
		// Load main Composer autoloader first (required for PSR-4 autoloading).
		require_once $this->plugin_dir . '/vendor/autoload.php';

		// Load PHPUnit polyfills for WordPress test compatibility.
		require_once $this->plugin_dir . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
	}

	/**
	 * Setup PHPUnit polyfills configuration.
	 */
	private function setup_phpunit_polyfills(): void {
		$polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
		if ( false !== $polyfills_path ) {
			define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills_path );
		}
	}

	/**
	 * Validate WordPress tests directory exists.
	 *
	 * @throws \Exception If tests directory is missing.
	 */
	private function validate_tests_directory(): void {
		if ( ! file_exists( "{$this->tests_dir}/includes/functions.php" ) ) {
			throw new \Exception(
				'Could not find ' . htmlspecialchars( $this->tests_dir ) . '/includes/functions.php, have you run bin/install-wp-tests.sh ?'
			);
		}
	}

	/**
	 * Load WordPress functions.
	 */
	private function load_wp_functions(): void {
		require_once "{$this->tests_dir}/includes/functions.php";
	}

	/**
	 * Register plugin loader function.
	 */
	private function register_plugin_loader(): void {
		$plugin_file = $this->plugin_dir . '/campaignbridge.php';

		if ( function_exists( 'tests_add_filter' ) ) {
			\tests_add_filter(
				'muplugins_loaded',
				function () use ( $plugin_file ) {
					require $plugin_file;
				}
			);
		} else {
			require $plugin_file;
		}
	}

	/**
	 * Load WordPress bootstrap.
	 */
	private function load_wp_bootstrap(): void {
		// Enable debugging for tests so logs go to files
		if (!defined('WP_DEBUG')) {
			define('WP_DEBUG', true);
		}
		require "{$this->tests_dir}/includes/bootstrap.php";
	}

	/**
	 * Load test helpers.
	 */
	private function load_test_helpers(): void {
		$helpers_dir = __DIR__ . '/helpers';
		require_once $helpers_dir . '/test_case.php';
		require_once $helpers_dir . '/test_factory.php';
	}
}

// Initialize and run the bootstrap.
try {
	$bootstrap = new Bootstrap();
	$bootstrap->init();
} catch ( \Exception $e ) {
	echo 'Bootstrap Error: ' . $e->getMessage() . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}
