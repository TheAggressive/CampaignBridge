<?php
/**
 * Mailchimp Provider Implementation for CampaignBridge.
 *
 * Provides full integration with Mailchimp's API for email campaign management,
 * audience handling, and template synchronization following WordPress security
 * best practices and our established coding standards.
 *
 * @package CampaignBridge
 * @since 0.2.0
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Encryption;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailchimp email service provider implementation.
 *
 * Handles all Mailchimp API interactions including campaign creation,
 * audience management, and template synchronization with proper error
 * handling, rate limiting, and security measures.
 */
class Mailchimp_Provider extends Abstract_Provider {
	/**
	 * Mailchimp API base URL
	 */
	private const API_BASE_URL = 'https://us1.api.mailchimp.com/3.0';

	/**
	 * API endpoints
	 */
	private const ENDPOINT_CAMPAIGNS = '/campaigns';
	private const ENDPOINT_TEMPLATES = '/templates';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'mailchimp', __( 'Mailchimp', 'campaignbridge' ) );

		// Configure Mailchimp-specific capabilities.
		$this->capabilities = array(
			'audiences'  => true,
			'templates'  => true,
			'scheduling' => true,
			'automation' => false,
			'analytics'  => true,
		);

		// Mailchimp API key pattern.
		$this->api_key_pattern = '/^[a-f0-9]{32}-us[0-9]+$/';
	}

	/**
	 * Check if provider is properly configured.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return bool
	 */
	public function is_configured( array $settings ): bool {
		$required_fields = array( 'api_key' );
		if ( ! $this->validate_required_settings( $settings, $required_fields ) ) {
			return false;
		}

		// Also validate API key format.
		return $this->is_valid_api_key( $settings['api_key'] );
	}

	/**
	 * Validate API key format.
	 *
	 * @param string $api_key API key to validate.
	 * @return bool True if valid format.
	 */
	public function is_valid_api_key( string $api_key ): bool {
		return preg_match( $this->api_key_pattern, $api_key ) === 1;
	}

	/**
	 * Render Mailchimp-specific settings fields.
	 *
	 * @param array<string, mixed> $settings    Current settings.
	 * @param string               $option_name Option name prefix.
	 * @return void
	 */
	public function render_settings_fields( array $settings, string $option_name ): void {
		$api_key     = $settings['api_key'] ?? '';
		$has_api_key = ! empty( $api_key );

		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'API Key', 'campaignbridge' ); ?></th>
			<td>
				<div class="campaignbridge-api-key-field" data-has-key="<?php echo esc_attr( $has_api_key ? 'true' : 'false' ); ?>">
					<?php if ( $has_api_key ) : ?>
						<!-- Show masked API key when configured -->
						<div class="api-key-display">
							<input
								type="text"
								value="<?php echo esc_attr( $this->get_masked_api_key( $api_key ) ); ?>"
								class="regular-text api-key-masked"
								readonly
								disabled
							/>
							<button type="button" class="button button-secondary api-key-toggle" aria-label="<?php esc_attr_e( 'Edit API Key', 'campaignbridge' ); ?>">
								<?php esc_html_e( 'Change', 'campaignbridge' ); ?>
							</button>
						</div>

						<!-- Hidden edit field -->
						<div class="api-key-edit" style="display: none;">
							<input
								type="password"
								name="<?php echo esc_attr( $option_name ); ?>[api_key]"
								value=""
								class="regular-text api-key-input"
								placeholder="<?php esc_attr_e( 'Enter new API key', 'campaignbridge' ); ?>"
								autocomplete="new-password"
							/>
							<button type="button" class="button button-secondary api-key-cancel">
								<?php esc_html_e( 'Cancel', 'campaignbridge' ); ?>
							</button>
						</div>
					<?php else : ?>
						<!-- Show empty field for initial setup -->
						<input
							type="password"
							name="<?php echo esc_attr( $option_name ); ?>[api_key]"
							value=""
							class="regular-text api-key-input"
							placeholder="<?php esc_attr_e( 'Enter your Mailchimp API key', 'campaignbridge' ); ?>"
							autocomplete="new-password"
						/>
					<?php endif; ?>
				</div>

				<p class="description">
					<?php esc_html_e( 'Your Mailchimp API key (starts with letters and numbers, ends with -us followed by numbers)', 'campaignbridge' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Send campaign to Mailchimp
	 *
	 * @param array<string, mixed> $blocks   Content blocks.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return bool|WP_Error
	 */
	public function send_campaign( array $blocks, array $settings ) {
		$this->log( 'Sending campaign to Mailchimp', array( 'block_count' => count( $blocks ) ) );

		try {
			// Validate configuration.
			if ( ! $this->is_configured( $settings ) ) {
				return $this->create_error(
					'configuration_error',
					__( 'Mailchimp is not properly configured.', 'campaignbridge' )
				);
			}

			$api_key = $settings['api_key'];

			// Create campaign data.
			$campaign_data = $this->prepare_campaign_data( $blocks, $settings );

			// Create campaign via Mailchimp API.
			$campaign_response = $this->create_mailchimp_campaign( $campaign_data, $api_key );

			if ( is_wp_error( $campaign_response ) ) {
				return $campaign_response;
			}

			// Send the campaign.
			$send_response = $this->send_mailchimp_campaign( $campaign_response['id'], $api_key );

			if ( is_wp_error( $send_response ) ) {
				return $send_response;
			}

			$this->log( 'Campaign sent successfully to Mailchimp' );
			return true;

		} catch ( \Exception $e ) {
			$this->log( 'Campaign sending failed: ' . $e->getMessage() );
			return $this->create_error( 'sending_error', $e->getMessage() );
		}
	}

		/**
		 * Get available template section keys.
		 *
		 * @param array<string, mixed> $settings Plugin settings.
		 * @param bool                 $refresh  Force refresh.
		 * @return array<string>|WP_Error
		 */
	public function get_section_keys( array $settings, bool $refresh = false ) {
		try {
			if ( ! $this->is_configured( $settings ) ) {
				return array();
			}

			$api_key = $settings['api_key'];

			// Get Mailchimp templates.
			$templates = $this->get_mailchimp_templates( $api_key );

			if ( is_wp_error( $templates ) ) {
				return $templates;
			}

			// Extract section keys from templates.
			$section_keys = array();
			foreach ( $templates as $template ) {
				if ( isset( $template['sections'] ) ) {
					$section_keys = array_merge( $section_keys, array_keys( $template['sections'] ) );
				}
			}

			return array_map( 'strval', array_unique( $section_keys ) );

		} catch ( \Exception $e ) {
			return $this->create_error( 'section_keys_error', $e->getMessage() );
		}
	}

	/**
	 * Get settings schema for validation.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			'api_key' => array(
				'sensitive'  => true,
				'required'   => true,
				'pattern'    => $this->api_key_pattern,
				'min_length' => 32,
				'max_length' => 50,
			),
		);
	}

	/**
	 * Prepare campaign data for Mailchimp API
	 *
	 * @param array<string, mixed> $blocks   Content blocks.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return array<string, mixed>
	 */
	private function prepare_campaign_data( array $blocks, array $settings ): array {
		$subject   = $settings['subject'] ?? '';
		$preheader = $settings['preheader'] ?? '';

		// Combine all blocks into HTML content.
		$content_html = $this->combine_blocks_to_html( $blocks );

		$campaign_data = array(
			'type'     => 'regular',
			'settings' => array(
				'subject_line' => $subject,
				'preview_text' => $preheader,
				'from_name'    => $settings['from_name'] ?? '',
				'reply_to'     => $settings['from_email'] ?? '',
			),
		);

		// Only add recipients if audience_id is available.
		if ( ! empty( $settings['audience_id'] ) ) {
			$campaign_data['recipients'] = array(
				'list_id' => $settings['audience_id'],
			);
		}

		return $campaign_data;
	}

	/**
	 * Combine blocks into HTML content.
	 *
	 * @param array<string, mixed> $blocks Content blocks.
	 * @return string
	 */
	private function combine_blocks_to_html( array $blocks ): string {
		$content = '';

		foreach ( $blocks as $section => $block_html ) {
			$content .= "<!-- {$section} section -->\n";
			$content .= $block_html . "\n";
		}

		return $content;
	}

	/**
	 * Create campaign via Mailchimp API.
	 *
	 * @param array<string, mixed> $campaign_data Campaign data.
	 * @param string               $api_key       API key.
	 * @return array<string, mixed>|WP_Error
	 */
	private function create_mailchimp_campaign( array $campaign_data, string $api_key ) {
		$response = \CampaignBridge\Core\Http_Client::post_json(
			self::API_BASE_URL . self::ENDPOINT_CAMPAIGNS,
			$campaign_data,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = $response['status_code'];
		$body        = $response['body'];

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_data  = json_decode( $body, true );
			$error_msg   = $error_data['detail'] ?? sprintf( 'API request failed with status %d', $status_code );
			$safe_status = is_int( $status_code ) ? $status_code : 500;

			return $this->create_error( 'mailchimp_api_error', $error_msg, $safe_status );
		}

		return json_decode( $body, true );
	}

	/**
	 * Send campaign via Mailchimp API.
	 *
	 * @param string $campaign_id Campaign ID.
	 * @param string $api_key     API key.
	 * @return bool|WP_Error
	 */
	private function send_mailchimp_campaign( string $campaign_id, string $api_key ) {
		$send_data = array( 'send' => true );

		$response = \CampaignBridge\Core\Http_Client::post(
			self::API_BASE_URL . self::ENDPOINT_CAMPAIGNS . '/' . $campaign_id . '/actions/send',
			array(
				'method'  => 'PATCH',
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $send_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = $response['status_code'];

		if ( $status_code < 200 || $status_code >= 300 ) {
			$body        = $response['body'];
			$error_data  = json_decode( $body, true );
			$error_msg   = $error_data['detail'] ?? sprintf( 'Failed to send campaign with status %d', $status_code );
			$safe_status = is_int( $status_code ) ? $status_code : 500;

			return $this->create_error( 'mailchimp_send_error', $error_msg, $safe_status );
		}

		return true;
	}

	/**
	 * Get Mailchimp templates.
	 *
	 * @param string $api_key API key.
	 * @return array<string, mixed>|WP_Error
	 */
	private function get_mailchimp_templates( string $api_key ) {
		$response = \CampaignBridge\Core\Http_Client::get(
			self::API_BASE_URL . self::ENDPOINT_TEMPLATES,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = $response['status_code'];
		$body        = $response['body'];

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_data  = json_decode( $body, true );
			$error_msg   = $error_data['detail'] ?? sprintf( 'Failed to fetch templates with status %d', $status_code );
			$safe_status = is_int( $status_code ) ? $status_code : 500;

			return $this->create_error( 'mailchimp_templates_error', $error_msg, $safe_status );
		}

		return json_decode( $body, true )['templates'] ?? array();
	}

	/**
	 * Get masked API key for display
	 *
	 * @param string $api_key API key.
	 * @return string
	 */
	private function get_masked_api_key( string $api_key ): string {
		if ( empty( $api_key ) ) {
			return '';
		}

		if ( strlen( $api_key ) <= 8 ) {
			return str_repeat( '•', strlen( $api_key ) );
		}

		return str_repeat( '•', strlen( $api_key ) - 4 ) . substr( $api_key, -4 );
	}

	/**
	 * Handle API errors gracefully.
	 *
	 * @param mixed $error API error response.
	 * @return \WP_Error Processed error.
	 */
	public function handle_api_error( $error ): \WP_Error {
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		if ( is_array( $error ) && isset( $error['title'], $error['detail'] ) ) {
			// Mailchimp API error format.
			return new \WP_Error(
				'mailchimp_api_error',
				sprintf(
					/* translators: 1: error title, 2: error detail */
					__( 'Mailchimp API Error: %1$s - %2$s', 'campaignbridge' ),
					$error['title'],
					$error['detail']
				)
			);
		}

		// Generic error handling.
		return new \WP_Error(
			'mailchimp_api_error',
			__( 'An error occurred while communicating with Mailchimp.', 'campaignbridge' )
		);
	}
}
