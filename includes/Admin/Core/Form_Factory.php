<?php
/**
 * Form Factory - Static methods for creating pre-configured forms
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

use CampaignBridge\Core\Storage;

/**
 * Form Factory - Static methods for creating pre-configured forms
 *
 * @package CampaignBridge\Admin\Core
 */
class Form_Factory {
	/**
	 * Create a contact form (pre-configured)
	 *
	 * @param string $form_id Form ID.
	 * @return Form
	 */
	public static function contact( string $form_id = 'contact' ): Form {
		return Form::make(
			$form_id,
			array(
				'fields' => array(
					'name'    => array(
						'type'     => 'text',
						'label'    => 'Name',
						'required' => true,
					),
					'email'   => array(
						'type'     => 'email',
						'label'    => 'Email',
						'required' => true,
					),
					'subject' => array(
						'type'  => 'text',
						'label' => 'Subject',
					),
					'message' => array(
						'type'     => 'textarea',
						'label'    => 'Message',
						'required' => true,
						'rows'     => 5,
					),
				),
				'hooks'  => array(
					'after_save' => function ( $data ) {
						// Send notification email when contact form is submitted.
						// No capability check needed as this is part of legitimate form functionality.
						// phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingCapabilityCheck
						\wp_mail( Storage::get_option( 'admin_email' ), $data['subject'] ?? 'Contact Form', $data['message'] );
					},
				),
			)
		);
	}

	/**
	 * Create a user registration form
	 *
	 * @param string $form_id Form ID.
	 * @return Form
	 */
	public static function register( string $form_id = 'register' ): Form {
		return Form::make(
			$form_id,
			array(
				'fields' => array(
					'username'         => array(
						'type'     => 'text',
						'label'    => 'Username',
						'required' => true,
					),
					'email'            => array(
						'type'     => 'email',
						'label'    => 'Email',
						'required' => true,
					),
					'password'         => array(
						'type'     => 'password',
						'label'    => 'Password',
						'required' => true,
					),
					'password_confirm' => array(
						'type'     => 'password',
						'label'    => 'Confirm Password',
						'required' => true,
					),
				),
				'hooks'  => array(
					'before_validate' => function ( $data ) {
						if ( $data['password'] !== $data['password_confirm'] ) {
							throw new \Exception( 'Passwords do not match' );
						}
					},
				),
			)
		);
	}

	/**
	 * Create a WordPress Settings API form
	 *
	 * @param string $form_id        Form ID.
	 * @param string $settings_group Settings group name.
	 * @return Form
	 */
	public static function settings_api( string $form_id = 'settings', string $settings_group = '' ): Form {
		$group = $settings_group ? $settings_group : $form_id;
		return Form::make( $form_id )
			->save_to_settings_api( $group )
			->table()
			->success( 'Settings saved successfully!' );
	}
}
