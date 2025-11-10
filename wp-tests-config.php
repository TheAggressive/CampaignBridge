<?php
/**
 * WordPress test configuration for wp-env
 */

// Database settings for wp-env test environment
define('DB_NAME', 'tests-wordpress');
define('DB_USER', 'root');
define('DB_PASSWORD', 'password');
define('DB_HOST', 'tests-mysql');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// WordPress settings
define('WP_TESTS_DOMAIN', 'localhost:9081');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');
define('WP_PHP_BINARY', 'php');

// Authentication Unique Keys and Salts
define('AUTH_KEY',         'test-auth-key');
define('SECURE_AUTH_KEY',  'test-secure-auth-key');
define('LOGGED_IN_KEY',    'test-logged-in-key');
define('NONCE_KEY',        'test-nonce-key');
define('AUTH_SALT',        'test-auth-salt');
define('SECURE_AUTH_SALT', 'test-secure-auth-salt');
define('LOGGED_IN_SALT',   'test-logged-in-salt');
define('NONCE_SALT',       'test-nonce-salt');

// WordPress test settings
define('WP_TESTS_FORCE_KNOWN_BUGS', false);
define('WP_TESTS_MULTISITE', false);

// Table prefix
$table_prefix = 'wptests_';

// Load WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}
