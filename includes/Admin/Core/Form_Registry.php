<?php
/**
 * Form Registry for CampaignBridge.
 *
 * Manages form configurations and provides lookup by form ID.
 * Acts as a central registry for all forms in the application.
 *
 * @package CampaignBridge\Admin\Core
 * @since 0.4.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core;

use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Core\Error_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form Registry class.
 *
 * Provides centralized management and lookup of form configurations.
 */
class Form_Registry {
	/**
	 * Option key for storing form registry in database.
	 */
	private const OPTION_KEY = 'campaignbridge_form_registry';

	/**
	 * Registered forms.
	 *
	 * @var array<string, Form_Config>
	 */
	private static array $forms = array();

	/**
	 * Register a form configuration.
	 *
	 * @param string      $form_id Form ID.
	 * @param Form_Config $config  Form configuration.
	 * @return void
	 * @throws \Throwable If failed to register form.
	 */
	public static function register( string $form_id, Form_Config $config ): void {
		try {
			self::$forms[ $form_id ] = $config;

			// Persist to database for AJAX requests.
			$forms_data             = \CampaignBridge\Core\Storage::get_option( self::OPTION_KEY, array() );
			$forms_data[ $form_id ] = $config->all();
			// phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification -- Form registration happens during rendering, not submission. Actual form submissions are nonce-verified separately.
			\CampaignBridge\Core\Storage::update_option( self::OPTION_KEY, $forms_data );
		} catch ( \Throwable $e ) {
			Error_Handler::error( sprintf( 'Form_Registry: Failed to register form "%s": %s', $form_id, $e->getMessage() ) );
			// Remove from in-memory cache if database update failed.
			unset( self::$forms[ $form_id ] );
			throw $e;
		}
	}

	/**
	 * Get a form configuration by ID.
	 *
	 * @param string $form_id Form ID.
	 * @return Form_Config|null Form configuration or null if not found.
	 */
	public static function get( string $form_id ): ?Form_Config {
		// Check in-memory cache first.
		if ( isset( self::$forms[ $form_id ] ) ) {
			return self::$forms[ $form_id ];
		}

		// Load specific form from database if not in memory.
		$forms_data = \CampaignBridge\Core\Storage::get_option( self::OPTION_KEY, array() );

		if ( isset( $forms_data[ $form_id ] ) ) {
			try {
				self::$forms[ $form_id ] = new Form_Config( $forms_data[ $form_id ] );
				return self::$forms[ $form_id ];
			} catch ( \Throwable $e ) {
				// Log error and return null for invalid config.
				Error_Handler::error( sprintf( 'Form_Registry: Invalid config for form "%s": %s', $form_id, $e->getMessage() ) );
				return null;
			}
		}

		return null;
	}

	/**
	 * Check if a form is registered.
	 *
	 * @param string $form_id Form ID.
	 * @return bool True if form is registered.
	 */
	public static function has( string $form_id ): bool {
		// Check in-memory cache first.
		if ( isset( self::$forms[ $form_id ] ) ) {
			return true;
		}

		// Check database.
		$forms_data = \CampaignBridge\Core\Storage::get_option( self::OPTION_KEY, array() );

		return isset( $forms_data[ $form_id ] );
	}

	/**
	 * Get all registered form IDs.
	 *
	 * @return array<string> Array of registered form IDs.
	 */
	public static function get_registered_form_ids(): array {
		$forms_data = \CampaignBridge\Core\Storage::get_option( self::OPTION_KEY, array() );

		return array_unique( array_merge( array_keys( self::$forms ), array_keys( $forms_data ) ) );
	}

	/**
	 * Unregister a form.
	 *
	 * @param string $form_id Form ID to unregister.
	 * @return void
	 */
	public static function unregister( string $form_id ): void {
		unset( self::$forms[ $form_id ] );
	}

	/**
	 * Clear all registered forms.
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$forms = array();
	}
}
