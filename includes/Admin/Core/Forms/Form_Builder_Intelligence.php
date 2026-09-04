<?php
/**
 * Convention-based defaults for the fluent form builder.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps naming heuristics separate from form orchestration. */
final class Form_Builder_Intelligence {
	/**
	 * Infer a specialized type when a generic text field has a known name.
	 *
	 * @param string $name Field name.
	 * @param string $current_type Configured type.
	 */
	public static function field_type( string $name, string $current_type ): string {
		if ( 'text' !== $current_type ) {
			return $current_type;
		}

		$patterns = array(
			'email'    => array( 'email' ),
			'url'      => array( 'url', 'link' ),
			'password' => array( 'password', 'pass' ),
			'tel'      => array( 'phone', 'tel', 'mobile' ),
			'number'   => array( 'count', 'amount', 'quantity' ),
		);
		$name     = strtolower( $name );

		foreach ( $patterns as $type => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( str_contains( $name, $keyword ) ) {
					return $type;
				}
			}
		}

		return $current_type;
	}

	/**
	 * Generate a readable label from a field identifier.
	 *
	 * @param string $name Field name.
	 */
	public static function label( string $name ): string {
		$label = preg_replace( '/([a-z])([A-Z])/', '$1 $2', $name ) ?? $name;
		$label = ucwords( strtolower( str_replace( '_', ' ', $label ) ) );

		return str_replace(
			array( 'Api', 'Url', 'Id', 'Smtp', 'Http', 'Https', 'Json', 'Xml', 'Html', 'Css', 'Js' ),
			array( 'API', 'URL', 'ID', 'SMTP', 'HTTP', 'HTTPS', 'JSON', 'XML', 'HTML', 'CSS', 'JS' ),
			$label
		);
	}

	/**
	 * Choose a conventional renderer layout.
	 *
	 * @param Form_Config $config Form configuration.
	 */
	public static function layout( Form_Config $config ): string {
		if ( is_admin() ) {
			return 'div';
		}

		return self::is_settings_form( $config ) ? 'table' : 'div';
	}

	/**
	 * Choose conventional submit copy.
	 *
	 * @param Form_Config $config Form configuration.
	 */
	public static function submit_text( Form_Config $config ): string {
		$form_id = (string) $config->get( 'form_id', '' );

		if ( self::is_settings_form( $config ) ) {
			return __( 'Save Settings', 'campaignbridge' );
		}

		if ( str_contains( $form_id, 'profile' ) || str_contains( $form_id, 'user' ) ) {
			return __( 'Update Profile', 'campaignbridge' );
		}

		if ( self::is_integration_form( $form_id ) ) {
			return __( 'Save Configuration', 'campaignbridge' );
		}

		return 'post_meta' === $config->get( 'save_method', 'options' )
			? __( 'Save Changes', 'campaignbridge' )
			: __( 'Save', 'campaignbridge' );
	}

	/**
	 * Choose conventional success copy.
	 *
	 * @param Form_Config $config Form configuration.
	 */
	public static function success_message( Form_Config $config ): string {
		$form_id = (string) $config->get( 'form_id', '' );

		if ( self::is_settings_form( $config ) ) {
			return __( 'Settings saved successfully!', 'campaignbridge' );
		}

		if ( str_contains( $form_id, 'profile' ) || str_contains( $form_id, 'user' ) ) {
			return __( 'Profile updated successfully!', 'campaignbridge' );
		}

		return self::is_integration_form( $form_id )
			? __( 'Configuration saved successfully!', 'campaignbridge' )
			: __( 'Saved successfully!', 'campaignbridge' );
	}

	/**
	 * Choose conventional failure copy.
	 *
	 * @param Form_Config $config Form configuration.
	 */
	public static function error_message( Form_Config $config ): string {
		$form_id = (string) $config->get( 'form_id', '' );

		if ( self::is_settings_form( $config ) ) {
			return __( 'Failed to save settings. Please try again.', 'campaignbridge' );
		}

		if ( str_contains( $form_id, 'profile' ) || str_contains( $form_id, 'user' ) ) {
			return __( 'Failed to update profile. Please try again.', 'campaignbridge' );
		}

		return self::is_integration_form( $form_id )
			? __( 'Failed to save configuration. Please try again.', 'campaignbridge' )
			: __( 'An error occurred. Please try again.', 'campaignbridge' );
	}

	/**
	 * Add safe convention-based validation rules.
	 *
	 * @param string             $name Field name.
	 * @param string             $type Field type.
	 * @param Form_Field_Builder $field Field builder.
	 */
	public static function add_validation( string $name, string $type, Form_Field_Builder $field ): void {
		$name = strtolower( $name );

		if ( 'email' === $type ) {
			$field->validation( 'email', true );
		} elseif ( 'url' === $type ) {
			$field->validation( 'url', true );
		} elseif ( 'password' === $type && ! str_contains( $name, 'confirm' ) ) {
			$field->validation( 'min_length', 8 );
		}

		if ( str_contains( $name, 'required' ) || str_contains( $name, 'mandatory' ) ) {
			$field->validation( 'required', true );
		}
		if ( str_contains( $name, 'api_key' ) || str_contains( $name, 'apikey' ) ) {
			$field->validation( 'min_length', 10 );
		}
		if ( str_contains( $name, 'timeout' ) ) {
			$field->validation( 'numeric', true );
			$field->validation( 'min', 1 );
			$field->validation( 'max', 300 );
		}
	}

	/**
	 * Determine whether a form uses settings conventions.
	 *
	 * @param Form_Config $config Form configuration.
	 */
	private static function is_settings_form( Form_Config $config ): bool {
		$form_id = (string) $config->get( 'form_id', '' );
		return str_contains( $form_id, 'settings' ) || str_contains( $form_id, 'config' );
	}

	/**
	 * Determine whether a form configures an integration.
	 *
	 * @param string $form_id Form identifier.
	 */
	private static function is_integration_form( string $form_id ): bool {
		return str_contains( $form_id, 'api' ) || str_contains( $form_id, 'integration' );
	}
}
