<?php
/**
 * HTML Export Provider for CampaignBridge.
 *
 * Provides HTML export functionality for email campaigns, allowing users to
 * export their email templates as static HTML files for use with any email
 * service provider or for manual distribution.
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
 * HTML Export Provider: handles static HTML export functionality.
 *
 * This provider allows users to export their email campaigns as static HTML
 * files that can be used with any email service provider or distributed manually.
 * It provides a simple way to generate email-safe HTML without requiring
 * external API integrations.
 */
class Html_Provider extends Abstract_Provider {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'html', __( 'HTML Export', 'campaignbridge' ) );

		// Set custom API key pattern for HTML provider (not applicable).
		$this->api_key_pattern = '/^$/'; // Empty pattern since no API key needed.
	}

	/**
	 * Check if the provider has sufficient settings to operate.
	 *
	 * HTML export doesn't require any configuration, so it's always ready.
	 *
	 * @param array<string, mixed> $settings Plugin settings array.
	 * @return bool Always true for HTML export.
	 */
	public function is_configured( array $settings ): bool {
		return true; // HTML export requires no configuration.
	}

	/**
	 * Render provider-specific settings fields in the admin interface.
	 *
	 * HTML export doesn't require any settings, so this renders a simple
	 * informational message.
	 *
	 * @param array<string, mixed> $settings    Current plugin settings array.
	 * @param string               $option_name Root option name for form field namespacing.
	 * @return void Outputs HTML directly to the page.
	 */
	public function render_settings_fields( array $settings, string $option_name ): void {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Export Settings', 'campaignbridge' ); ?></th>
			<td>
				<p class="description">
					<?php esc_html_e( 'HTML export requires no configuration. Your email templates will be exported as static HTML files.', 'campaignbridge' ); ?>
				</p>
				<div class="cb-settings__provider-fields">
					<h4><?php esc_html_e( 'Export Options', 'campaignbridge' ); ?></h4>
					<ul>
						<li><?php esc_html_e( '• Email-safe HTML with inline CSS', 'campaignbridge' ); ?></li>
						<li><?php esc_html_e( '• Table-based layouts for maximum compatibility', 'campaignbridge' ); ?></li>
						<li><?php esc_html_e( '• Responsive design support', 'campaignbridge' ); ?></li>
						<li><?php esc_html_e( '• Absolute URLs for images and links', 'campaignbridge' ); ?></li>
					</ul>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Export campaign content as HTML.
	 *
	 * Generates static HTML files from the provided content blocks.
	 * This method creates downloadable HTML files that can be used
	 * with any email service provider.
	 *
	 * @param array<string, mixed> $blocks   Associative array mapping section keys to HTML content.
	 * @param array<string, mixed> $settings Plugin settings array with provider configuration.
	 * @return bool|\WP_Error True on success, WP_Error on failure with details.
	 */
	public function send_campaign( array $blocks, array $settings ) {
		try {
			// Generate HTML content from blocks.
			$html_content = $this->generate_html_content( $blocks, $settings );

			// Create downloadable file.
			$file_path = $this->create_html_file( $html_content );

			if ( is_wp_error( $file_path ) ) {
				return $file_path;
			}

			// Log successful export.
			$this->log(
				'HTML export completed successfully',
				array(
					'file_path' => $file_path,
					'sections'  => array_keys( $blocks ),
				)
			);

			return true;

		} catch ( \Exception $e ) {
			$this->log(
				'HTML export failed: ' . $e->getMessage(),
				array(
					'error' => $e->getMessage(),
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
				)
			);

			return $this->create_error(
				'html_export_failed',
				__( 'Failed to export HTML: ', 'campaignbridge' ) . $e->getMessage(),
				500
			);
		}
	}

	/**
	 * Get available template section keys for content mapping.
	 *
	 * HTML export supports all standard email template sections.
	 *
	 * @param array<string, mixed> $settings Plugin settings array (unused for HTML export).
	 * @param bool                 $refresh  Force refresh of cached data (unused for HTML export).
	 * @return array<string> Array of section key strings.
	 */
	public function get_section_keys( array $settings, bool $refresh = false ): array {
		return array(
			'header',
			'body',
			'footer',
			'content',
			'sidebar',
		);
	}

	/**
	 * Get settings schema for validation and redaction.
	 *
	 * @return array<string, mixed> Schema array with field definitions.
	 */
	public function settings_schema(): array {
		return array(
			'export_format' => array(
				'type'        => 'string',
				'default'     => 'html',
				'description' => __( 'Export format for HTML files', 'campaignbridge' ),
			),
			'include_css'   => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Include inline CSS in exported HTML', 'campaignbridge' ),
			),
		);
	}

	/**
	 * Redact sensitive settings for display/logging.
	 *
	 * HTML export doesn't have sensitive settings, so returns as-is.
	 *
	 * @param array<string, mixed> $settings Raw settings array.
	 * @return array<string, mixed> Redacted settings array.
	 */
	public function redact_settings( array $settings ): array {
		// HTML export has no sensitive data to redact.
		return $settings;
	}

	/**
	 * Get provider capabilities and supported features.
	 *
	 * @return array<string, mixed> Array of supported features.
	 */
	public function get_capabilities(): array {
		return array(
			'export'     => true,
			'preview'    => true,
			'templates'  => true,
			'audiences'  => false,
			'scheduling' => false,
			'analytics'  => false,
		);
	}

	/**
	 * Generate HTML content from blocks.
	 *
	 * @param array<string, mixed> $blocks   Content blocks to convert to HTML.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string Generated HTML content.
	 */
	private function generate_html_content( array $blocks, array $settings ): string {
		$html  = '<!DOCTYPE html>' . "\n";
		$html .= '<html lang="en">' . "\n";
		$html .= '<head>' . "\n";
		$html .= '<meta charset="UTF-8">' . "\n";
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
		$html .= '<title>' . esc_html( $settings['subject'] ?? 'Email Campaign' ) . '</title>' . "\n";
		$html .= '</head>' . "\n";
		$html .= '<body>' . "\n";

		// Add content sections.
		foreach ( $blocks as $section => $content ) {
			$html .= "<!-- {$section} section -->\n";
			$html .= $content . "\n";
		}

		$html .= '</body>' . "\n";
		$html .= '</html>';

		return $html;
	}

	/**
	 * Create downloadable HTML file.
	 *
	 * @param string $content HTML content to save.
	 * @return string|\WP_Error File path on success, WP_Error on failure.
	 */
	private function create_html_file( string $content ) {
		// Create uploads directory if it doesn't exist.
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/campaignbridge-exports/';

		if ( ! wp_mkdir_p( $export_dir ) ) {
			return $this->create_error(
				'export_dir_creation_failed',
				__( 'Failed to create export directory', 'campaignbridge' ),
				500
			);
		}

		// Generate unique filename.
		$filename  = 'campaign-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.html';
		$file_path = $export_dir . $filename;

		// Write file.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$result = $wp_filesystem->put_contents( $file_path, $content );

		if ( false === $result ) {
			return $this->create_error(
				'file_write_failed',
				__( 'Failed to write HTML file', 'campaignbridge' ),
				500
			);
		}

		return $file_path;
	}
}
