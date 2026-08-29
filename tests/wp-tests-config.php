<?php
/**
 * WordPress core PHPUnit configuration.
 *
 * All defaults point at the disposable test database. Never use production
 * credentials here because the WordPress test library drops prefixed tables.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

/**
 * Read a non-empty environment override.
 *
 * @param string $name    Environment variable name.
 * @param string $default Default value.
 * @return string
 */
function campaignbridge_tests_env( string $name, string $default ): string {
	$value = getenv( $name );

	return is_string( $value ) && '' !== $value ? $value : $default;
}

define( 'ABSPATH', rtrim( campaignbridge_tests_env( 'CB_TESTS_ABSPATH', '/var/www/html' ), '/' ) . '/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

$campaignbridge_plugin_dir = getenv( 'CB_TESTS_PLUGIN_DIR' );
if ( is_string( $campaignbridge_plugin_dir ) && '' !== $campaignbridge_plugin_dir ) {
	define( 'WP_PLUGIN_DIR', rtrim( $campaignbridge_plugin_dir, '/' ) );
	define( 'WP_PLUGIN_URL', 'http://example.test/wp-content/plugins' );
}

define( 'DB_NAME', campaignbridge_tests_env( 'CB_TESTS_DB_NAME', 'campaignbridge_test' ) );
define( 'DB_USER', campaignbridge_tests_env( 'CB_TESTS_DB_USER', 'campaignbridge' ) );
define( 'DB_PASSWORD', campaignbridge_tests_env( 'CB_TESTS_DB_PASSWORD', 'campaignbridge' ) );
define( 'DB_HOST', campaignbridge_tests_env( 'CB_TESTS_DB_HOST', '127.0.0.1:13308' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'FS_METHOD', 'direct' );

define( 'AUTH_KEY', 'campaignbridge-tests-auth-key' );
define( 'SECURE_AUTH_KEY', 'campaignbridge-tests-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'campaignbridge-tests-logged-in-key' );
define( 'NONCE_KEY', 'campaignbridge-tests-nonce-key' );
define( 'AUTH_SALT', 'campaignbridge-tests-auth-salt' );
define( 'SECURE_AUTH_SALT', 'campaignbridge-tests-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'campaignbridge-tests-logged-in-salt' );
define( 'NONCE_SALT', 'campaignbridge-tests-nonce-salt' );

$table_prefix = 'cbtests_';

define( 'WP_TESTS_DOMAIN', 'example.test' );
define( 'WP_TESTS_EMAIL', 'admin@example.test' );
define( 'WP_TESTS_TITLE', 'CampaignBridge Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
