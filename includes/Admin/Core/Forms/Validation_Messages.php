<?php
/**
 * Validation Messages - Centralized validation error messages
 *
 * Provides a single source of truth for all form validation error messages
 * to eliminate duplication and ensure consistency.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Validation Messages Class
 *
 * Centralized repository for all validation error messages.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Validation_Messages {

	/**
	 * Get validation error message
	 *
	 * @param string $message_key Message key identifier.
	 * @param mixed  $args        Optional arguments for message formatting.
	 * @return string Translated validation message.
	 */
	public static function get( string $message_key, $args = null ): string {
		$messages = self::get_all_messages();

		$message = $messages[ $message_key ] ?? '';

		if ( null !== $args ) {
			if ( is_array( $args ) ) {
				$message = vsprintf( $message, $args );
			} else {
				$message = sprintf( $message, $args );
			}
		}

		return $message;
	}

	/**
	 * Get all validation messages
	 *
	 * @return array<string, string> Array of message keys and translated strings.
	 */
	public static function get_all_messages(): array {
		return array(
			'field_required'      => \__( '%s is required.', 'campaignbridge' ),
			'this_field_required' => \__( 'This field is required.', 'campaignbridge' ),
			'invalid_email'       => \__( 'Please enter a valid email address.', 'campaignbridge' ),
			'invalid_url'         => \__( 'Please enter a valid URL.', 'campaignbridge' ),
			'invalid_number'      => \__( 'Please enter a valid number.', 'campaignbridge' ),
			'invalid_date'        => \__( 'Please enter a valid date.', 'campaignbridge' ),
			'invalid_pattern'     => \__( 'Value does not match required format.', 'campaignbridge' ),
			'min_length'          => \__( 'Minimum length is %d characters.', 'campaignbridge' ),
			'max_length'          => \__( 'Maximum length is %d characters.', 'campaignbridge' ),
			'value_too_low'       => \__( 'Value must be at least %s.', 'campaignbridge' ),
			'value_too_high'      => \__( 'Value must be no more than %s.', 'campaignbridge' ),
			'number_too_small'    => \__( 'Value must be at least %s.', 'campaignbridge' ),
			'number_too_large'    => \__( 'Value must be no more than %s.', 'campaignbridge' ),
			'invalid_file_data'   => \__( 'Invalid file data structure.', 'campaignbridge' ),
			'file_not_found'      => \__( 'Uploaded file could not be found.', 'campaignbridge' ),
			'file_too_large'      => \__( 'File size exceeds maximum allowed size of %s.', 'campaignbridge' ),
			'file_required'       => \__( 'Please select a file to upload.', 'campaignbridge' ),
			'not_numeric'         => \__( 'Please enter a valid number.', 'campaignbridge' ),
			'minimum_value'       => \__( 'Minimum value is %d.', 'campaignbridge' ),
			'maximum_value'       => \__( 'Maximum value is %d.', 'campaignbridge' ),
		);
	}
}
