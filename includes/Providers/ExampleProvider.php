<?php
/**
 * Example Provider Implementation for CampaignBridge.
 *
 * This is a template/example provider that demonstrates how to implement
 * the ProviderInterface and extend Abstract_Provider. It serves as a
 * starting point for creating new email service providers.
 *
 * To create a real provider, copy this file, rename it, and implement
 * the provider-specific logic for your email service.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Example provider implementation.
 *
 * This class demonstrates the recommended pattern for implementing providers.
 * Replace with actual provider logic when implementing real email services.
 */
class ExampleProvider extends Abstract_Provider {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'example', __( 'Example Provider', 'campaignbridge' ) );

		// Customize capabilities for this provider.
		$this->capabilities = array(
			'audiences'  => true,
			'templates'  => false, // This provider doesn't support templates.
			'scheduling' => true,
			'automation' => false,
			'analytics'  => true,
		);

		// Set custom API key pattern for this provider.
		$this->api_key_pattern = '/^[a-zA-Z0-9_-]{20,}$/'; // Generic pattern for example provider.
	}

	/**
	 * Check if the provider has sufficient settings to operate.
	 *
	 * @param array<string, mixed> $settings Plugin settings array containing provider configuration.
	 * @return bool True if provider is ready to send campaigns.
	 */
	public function is_configured( array $settings ): bool {
		// Example: Check for API key and endpoint URL.
		return $this->validate_required_settings( $settings, array( 'api_key', 'endpoint' ) );
	}

	/**
	 * Render provider-specific settings fields in the admin interface.
	 *
	 * @param array<string, mixed> $settings    Current plugin settings array.
	 * @param string               $option_name Root option name for form field namespacing.
	 * @return void Outputs HTML directly to the page.
	 */
	public function render_settings_fields( array $settings, string $option_name ): void {
		$api_key     = $settings['api_key'] ?? '';
		$endpoint    = $settings['endpoint'] ?? '';
		$has_api_key = ! empty( $api_key );

		?>
		<tr>
			<th scope="row"><?php echo esc_html__( 'API Key', 'campaignbridge' ); ?></th>
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
						<div class="api-key-edit" style="display: none; margin-top: 8px;">
							<input
								type="password"
								name="<?php echo esc_attr( $option_name ); ?>[api_key]"
								value=""
								class="regular-text api-key-input"
								placeholder="<?php esc_attr_e( 'Enter new API key', 'campaignbridge' ); ?>"
								autocomplete="new-password"
							/>
							<button type="button" class="button button-primary api-key-save">
								<?php esc_html_e( 'Save', 'campaignbridge' ); ?>
							</button>
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
							placeholder="<?php esc_attr_e( 'Enter your API key', 'campaignbridge' ); ?>"
							autocomplete="new-password"
						/>
					<?php endif; ?>
				</div>
				<p class="description"><?php echo esc_html__( 'Your API key for the email service.', 'campaignbridge' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'API Endpoint', 'campaignbridge' ); ?></th>
			<td>
				<input
					type="url"
					name="<?php echo esc_attr( $option_name ); ?>[endpoint]"
					value="<?php echo esc_attr( $endpoint ); ?>"
					class="regular-text"
					placeholder="https://api.example.com"
				/>
				<p class="description"><?php echo esc_html__( 'The base URL for the email service API.', 'campaignbridge' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Send campaign or export content based on provider type.
	 *
	 * @param array<string, mixed> $blocks   Associative array mapping section keys to HTML content.
	 * @param array<string, mixed> $settings Plugin settings array with provider configuration.
	 * @return bool|\WP_Error True on success, WP_Error on failure with details.
	 */
	public function send_campaign( array $blocks, array $settings ) {
		$this->log( 'Sending campaign', array( 'block_count' => count( $blocks ) ) );

		try {
			// Validate configuration.
			if ( ! $this->is_configured( $settings ) ) {
				return $this->create_error( 'configuration_error', __( 'Provider is not properly configured.', 'campaignbridge' ) );
			}

			$api_key  = $settings['api_key'];
			$endpoint = $settings['endpoint'];

			// Example: Make API call to send campaign.
			$campaign_data = array(
				'subject'    => 'Your Campaign Subject',
				'content'    => $this->prepare_campaign_content( $blocks ),
				'recipients' => array( 'all' => true ),
			);

			// Example of using instance for dependency injection
			// $http_client = Http_Client::create_instance(); // For DI
			// $response = $http_client->post_json(...);.

			// Using static methods (current approach).
			$response = \CampaignBridge\Core\Http_Client::post_json(
				$endpoint . '/campaigns',
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
			if ( $status_code < 200 || $status_code >= 300 ) {
				return $this->create_error(
					'api_error',
					sprintf(
						/* translators: %d: HTTP status code */
						__( 'API request failed with status %d.', 'campaignbridge' ),
						$status_code
					),
					$status_code
				);
			}

			$this->log( 'Campaign sent successfully' );
			return true;

		} catch ( \Exception $e ) {
			$this->log( 'Campaign sending failed', array( 'error' => $e->getMessage() ) );
			return $this->create_error( 'sending_error', $e->getMessage() );
		}
	}

	/**
	 * Get available template section keys for content mapping.
	 *
	 * @param array<string, mixed> $settings Plugin settings array (for provider-specific logic).
	 * @param bool                 $refresh  Force refresh of cached data.
	 * @return array<string>|\WP_Error Array of section key strings, or WP_Error if unsupported/unavailable.
	 */
	public function get_section_keys( array $settings, bool $refresh = false ) {
		// Example: Return supported template sections.
		return array( 'header', 'body', 'footer' );
	}

	/**
	 * Get settings schema for validation and redaction.
	 *
	 * @return array<string, mixed> Schema array with field definitions.
	 */
	public function settings_schema(): array {
		return array(
			'api_key'  => array(
				'sensitive'  => true,
				'required'   => true,
				'pattern'    => '/^[A-Za-z0-9_-]{32,}$/', // Example pattern.
				'min_length' => 32,
				'max_length' => 100,
			),
			'endpoint' => array(
				'sensitive'  => false,
				'required'   => true,
				'pattern'    => '/^https?:\/\/.+$/',
				'min_length' => 10,
				'max_length' => 200,
			),
		);
	}

	/**
	 * Prepare campaign content from blocks.
	 *
	 * @param array<string, string> $blocks Content blocks.
	 * @return string Prepared content.
	 */
	private function prepare_campaign_content( array $blocks ): string {
		$content = '';

		if ( isset( $blocks['header'] ) ) {
			$content .= '<header>' . $blocks['header'] . '</header>';
		}

		if ( isset( $blocks['body'] ) ) {
			$content .= '<main>' . $blocks['body'] . '</main>';
		}

		if ( isset( $blocks['footer'] ) ) {
			$content .= '<footer>' . $blocks['footer'] . '</footer>';
		}

		return $content;
	}

	/**
	 * Get masked API key for display.
	 *
	 * @param string $api_key The API key.
	 * @return string Masked API key.
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
}
