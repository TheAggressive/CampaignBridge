<?php
/**
 * Admin Security Manager - Handles security headers and admin security measures
 *
 * Manages security headers, Content Security Policy, and other security measures
 * for WordPress admin pages, particularly those handling sensitive encrypted data.
 *
 * @package CampaignBridge\Admin
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

/**
 * Admin Security Manager Class
 *
 * Handles security headers and admin-specific security measures including
 * CSP, XSS protection, clickjacking prevention, and other security headers
 * for pages that handle encrypted or sensitive data.
 *
 * @package CampaignBridge\Admin
 */
class Admin_Security_Manager {

	/**
	 * Pages that should have enhanced security headers.
	 *
	 * @var array<string>
	 */
	private const SECURE_PAGES = array(
		'toplevel_page_campaignbridge',
		'campaignbridge_page_campaignbridge-settings',
	);

	/**
	 * Initialize the security system.
	 *
	 * @return void
	 */
	public function init(): void {
		// current_screen fires inside set_current_screen(), after the screen
		// object exists but before wp-admin/admin-header.php is required. Both
		// admin_head and admin_enqueue_scripts run after _wp_admin_html_begin()
		// has already started output, at which point header() is too late.
		\add_action( 'current_screen', array( $this, 'add_security_headers' ) );
	}

	/**
	 * Send the security headers for admin pages handling sensitive data.
	 *
	 * @param \WP_Screen|null $screen Screen being loaded.
	 * @return void
	 */
	public function add_security_headers( $screen = null ): void {
		$screen_id = $screen instanceof \WP_Screen ? $screen->id : '';

		if ( '' === $screen_id && function_exists( 'get_current_screen' ) ) {
			$current   = get_current_screen();
			$screen_id = $current instanceof \WP_Screen ? $current->id : '';
		}

		$headers = self::headers_for( $screen_id );

		if ( array() === $headers || \headers_sent() ) {
			return;
		}

		foreach ( $headers as $header ) {
			header( $header );
		}
	}

	/**
	 * Build the headers a screen should receive.
	 *
	 * Kept separate from sending so the policy itself is assertable: a test can
	 * prove which headers a screen gets without needing a live HTTP response.
	 *
	 * @param string $screen_id Screen identifier.
	 * @return array<int, string> Header lines, empty when the screen is not covered.
	 */
	public static function headers_for( string $screen_id ): array {
		if ( ! in_array( $screen_id, self::SECURE_PAGES, true ) ) {
			return array();
		}

		return array(
			// Content Security Policy to limit script and form origins.
			"Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self'; form-action 'self';",
			// Prevent clickjacking.
			'X-Frame-Options: SAMEORIGIN',
			// Legacy XSS filter for older clients.
			'X-XSS-Protection: 1; mode=block',
			// Prevent MIME type sniffing.
			'X-Content-Type-Options: nosniff',
			// Limit referrer leakage from pages showing encrypted fields.
			'Referrer-Policy: strict-origin-when-cross-origin',
		);
	}
}
