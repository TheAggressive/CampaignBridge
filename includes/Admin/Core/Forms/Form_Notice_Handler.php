<?php
/**
 * Form Notice Handler - Handles form success/error notices
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Notice Handler - Manages form success/error messages and displays them directly
 *
 * This class handles form notifications independently without external dependencies.
 * It displays notices directly through WordPress admin_notices action.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Notice_Handler {

	/**
	 * Trigger a success notice
	 *
	 * @param Form_Config          $config Form configuration.
	 * @param array<string, mixed> $data   Form data.
	 */
	public function trigger_success( Form_Config $config, array $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$message = $config->get( 'success_message' );
		$this->display_notice( $message, 'success' );
	}

	/**
	 * Trigger an error notice
	 *
	 * @param Form_Config          $config Form configuration.
	 * @param array<string, mixed> $data   Form data.
	 */
	public function trigger_error( Form_Config $config, array $data ): void {
		$message = $config->get( 'error_message' );
		$this->display_notice( $message, 'error' );
	}

	/**
	 * Trigger a warning notice
	 *
	 * @param string $message The warning message to display.
	 */
	public function trigger_warning( string $message ): void {
		$this->display_notice( $message, 'warning' );
	}

	/**
	 * Display form notice directly via WordPress admin_notices
	 *
	 * @param string $message The message to display.
	 * @param string $type    The message type (success/error).
	 */
	private function display_notice( string $message, string $type ): void {
		// Add notice directly to WordPress admin_notices.
		add_action(
			'admin_notices',
			function () use ( $message, $type ) {
				$classes = array(
					'success' => 'notice-success',
					'error'   => 'notice-error',
					'warning' => 'notice-warning',
					'info'    => 'notice-info',
				);
				$class   = $classes[ $type ] ?? 'notice-info';

				echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible">';
				echo '<p>' . esc_html( $message ) . '</p>';
				echo '<button type="button" class="notice-dismiss">';
				echo '<span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'campaignbridge' ) . '</span>';
				echo '</button>';
				echo '</div>';
			}
		);
	}
}
