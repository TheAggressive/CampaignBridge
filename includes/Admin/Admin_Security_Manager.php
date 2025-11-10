<?php
/**
 * Admin Security Manager - Handles security headers and admin security measures
 *
 * Manages security headers, Content Security Policy, and other security measures
 * for WordPress admin pages, particularly those handling sensitive encrypted data.
 *
 * @package CampaignBridge\Admin
 */

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
		\add_action( 'admin_head', array( $this, 'add_security_headers' ) );
	}

	/**
	 * Add security headers for admin pages with encrypted fields.
	 *
	 * @return void
	 */
	public function add_security_headers(): void {
		// Only add headers on pages that might contain encrypted fields.
		$current_screen = get_current_screen();
		if ( ! $current_screen || ! in_array( $current_screen->id, self::SECURE_PAGES, true ) ) {
			return;
		}

		// Add Content Security Policy to prevent XSS.
		header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self'; form-action 'self';" );

		// Prevent clickjacking.
		header( 'X-Frame-Options: SAMEORIGIN' );

		// Enable XSS protection.
		header( 'X-XSS-Protection: 1; mode=block' );

		// Prevent MIME type sniffing.
		header( 'X-Content-Type-Options: nosniff' );

		// Referrer policy for encrypted field pages.
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}
}
