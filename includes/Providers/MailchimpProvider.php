<?php
/**
 * CampaignBridge Mailchimp provider.
 *
 * Creates campaigns, updates content sections, and fetches
 * audiences/templates via the Mailchimp API.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Notices;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailchimp provider.
 */
// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * Mailchimp provider implementation.
 *
 * Creates campaigns, updates content with template sections, and
 * fetches audiences, templates and section keys via Mailchimp API.
 */
class MailchimpProvider implements ProviderInterface {
	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'mailchimp';
	}

	/**
	 * Human-readable label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Mailchimp', 'campaignbridge' );
	}

	/**
	 * Whether required settings exist.
	 *
	 * @param array $settings Plugin settings.
	 * @return bool
	 */
	public function is_configured( $settings ) {
		return ! empty( $settings['api_key'] ) && ! empty( $settings['audience_id'] ) && ! empty( $settings['template_id'] );
	}

	/**
	 * Render provider settings fields.
	 *
	 * @param array  $settings    Plugin settings.
	 * @param string $option_name Root option name.
	 * @return void
	 */
	public function render_settings_fields( $settings, $option_name ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html__( 'API Key', 'campaignbridge' ); ?></th>
			<td>
				<input id="campaignbridge-mailchimp-api-key" type="password" autocomplete="new-password" name="<?php echo esc_attr( $option_name ); ?>[api_key]" value="<?php echo esc_attr( isset( $settings['api_key'] ) ? $settings['api_key'] : '' ); ?>" size="50" />
				<span id="campaignbridge-verify-status" class="cb-verify-status"></span>
				<?php if ( empty( $settings['api_key'] ) ) : ?>
					<p class="description"><?php echo esc_html__( 'Enter and save your API key to select an audience and template.', 'campaignbridge' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( ! empty( $settings['api_key'] ) ) : ?>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Audience', 'campaignbridge' ); ?></th>
			<td>
				<?php
				$current_audience_label = '';
				if ( ! empty( $settings['audience_id'] ) ) {
					$aud_items = $this->get_audiences( $settings );
					if ( is_array( $aud_items ) ) {
						foreach ( $aud_items as $it ) {
							if ( isset( $it['id'] ) && (string) $it['id'] === (string) $settings['audience_id'] ) {
								$current_audience_label = isset( $it['name'] ) ? (string) $it['name'] : '';
								break;
							}
						}
					}
				}
				?>
				<select id="campaignbridge-mailchimp-audience" name="<?php echo esc_attr( $option_name ); ?>[audience_id]" style="min-width:320px;">
					<?php if ( ! empty( $settings['audience_id'] ) ) : ?>
						<option value="<?php echo esc_attr( $settings['audience_id'] ); ?>" selected><?php echo esc_html( $current_audience_label ? $current_audience_label : (string) $settings['audience_id'] ); ?></option>
					<?php else : ?>
						<option value="">—</option>
					<?php endif; ?>
				</select>
				<button type="button" class="button" id="campaignbridge-fetch-audiences"><?php echo esc_html__( 'Reset Audiences', 'campaignbridge' ); ?></button>
				<p class="description"><?php echo esc_html__( 'Pick your Mailchimp Audience (list). Requires API key.', 'campaignbridge' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Template', 'campaignbridge' ); ?></th>
			<td>
				<?php
				$current_template_label = '';
				if ( ! empty( $settings['template_id'] ) ) {
					$tpl_items = $this->get_templates( $settings );
					if ( is_array( $tpl_items ) ) {
						foreach ( $tpl_items as $it ) {
							if ( isset( $it['id'] ) && (int) $it['id'] === (int) $settings['template_id'] ) {
								$current_template_label = isset( $it['name'] ) ? (string) $it['name'] : '';
								break;
							}
						}
					}
				}
				?>
				<select id="campaignbridge-mailchimp-templates" name="<?php echo esc_attr( $option_name ); ?>[template_id]" style="min-width:320px;">
					<?php if ( ! empty( $settings['template_id'] ) ) : ?>
						<option value="<?php echo esc_attr( $settings['template_id'] ); ?>" selected><?php echo esc_html( $current_template_label ? $current_template_label : (string) $settings['template_id'] ); ?></option>
					<?php else : ?>
						<option value="">—</option>
					<?php endif; ?>
				</select>
				<button type="button" class="button" id="campaignbridge-fetch-templates"><?php echo esc_html__( 'Reset Templates', 'campaignbridge' ); ?></button>
				<p class="description"><?php echo esc_html__( 'Pick your Saved Template. Requires API key.', 'campaignbridge' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>
		<?php
	}

	/**
	 * Create a draft campaign and update its content using the given blocks.
	 *
	 * @param array $blocks   section_key => HTML string.
	 * @param array $settings Provider settings (api_key, audience_id, template_id).
	 * @return bool
	 */
	public function send_campaign( $blocks, $settings ) {
		$api_key     = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$audience_id = isset( $settings['audience_id'] ) ? $settings['audience_id'] : '';
		$template_id = isset( $settings['template_id'] ) ? (int) $settings['template_id'] : 0;

		if ( empty( $api_key ) || empty( $audience_id ) || empty( $template_id ) ) {
			Notices::error( esc_html__( 'Please complete Mailchimp settings.', 'campaignbridge' ) );
			return false;
		}

		$api_key_parts = explode( '-', $api_key );
		if ( count( $api_key_parts ) < 2 ) {
			Notices::error( esc_html__( 'Invalid Mailchimp API key format.', 'campaignbridge' ) );
			return false;
		}

		$dc       = end( $api_key_parts );
		$endpoint = sprintf( 'https://%s.api.mailchimp.com/3.0', $dc );

		$campaign = wp_remote_post(
			$endpoint . '/campaigns',
			array(
				'headers' => array(
					'Authorization' => 'apikey ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'type'       => 'regular',
						'recipients' => array( 'list_id' => $audience_id ),
						'settings'   => array(
							'subject_line' => 'Your Weekly Update',
							'title'        => 'WP Mailchimp Campaign',
							'from_name'    => 'Your Name',
							'reply_to'     => 'you@example.com',
							'template_id'  => $template_id,
						),
					)
				),
			)
		);

		if ( is_wp_error( $campaign ) ) {
			Notices::error( esc_html( $campaign->get_error_message() ) );
			return false;
		}

		$campaign_code = (int) wp_remote_retrieve_response_code( $campaign );
		if ( $campaign_code < 200 || $campaign_code >= 300 ) {
			Notices::error( esc_html__( 'Failed to create campaign.', 'campaignbridge' ) );
			return false;
		}

		$campaign_body = json_decode( wp_remote_retrieve_body( $campaign ) );
		if ( empty( $campaign_body->id ) ) {
			Notices::error( esc_html__( 'Failed to create campaign.', 'campaignbridge' ) );
			return false;
		}

		$content = array(
			'template' => array(
				'id'       => (int) $template_id,
				'sections' => $blocks,
			),
		);

		$content_resp = wp_remote_request(
			$endpoint . '/campaigns/' . rawurlencode( $campaign_body->id ) . '/content',
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Authorization' => 'apikey ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $content ),
			)
		);

		if ( is_wp_error( $content_resp ) ) {
			Notices::error( esc_html( $content_resp->get_error_message() ) );
			return false;
		}

		$content_code = (int) wp_remote_retrieve_response_code( $content_resp );
		if ( $content_code < 200 || $content_code >= 300 ) {
			Notices::error( esc_html__( 'Failed to update campaign content.', 'campaignbridge' ) );
			return false;
		}

		Notices::success( esc_html__( 'Campaign created. Please review and send it in Mailchimp.', 'campaignbridge' ) );
		return true;
	}

	/**
	 * Fetch Mailchimp audiences (lists).
	 *
	 * @param array $settings Provider settings (api_key).
	 * @param bool  $refresh  Force refresh.
	 * @return array|WP_Error
	 */
	public function get_audiences( $settings, $refresh = false ) {
		$api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'API key is required.', 'campaignbridge' ) );
		}
		$parts = explode( '-', $api_key );
		if ( count( $parts ) < 2 ) {
			return new WP_Error( 'bad_key', __( 'Invalid Mailchimp API key format.', 'campaignbridge' ) );
		}
		$cache_key = 'cb_mc_audiences_' . md5( $api_key );
		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached; }
		}
		$dc       = end( $parts );
		$endpoint = sprintf( 'https://%s.api.mailchimp.com/3.0', $dc );
		$resp     = wp_remote_get(
			$endpoint . '/lists?count=1000',
			array(
				'headers' => array( 'Authorization' => 'apikey ' . $api_key ),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'http_error', __( 'Failed to fetch audiences.', 'campaignbridge' ) );
		}
		$data  = json_decode( wp_remote_retrieve_body( $resp ), true );
		$items = array();
		if ( ! empty( $data['lists'] ) && is_array( $data['lists'] ) ) {
			foreach ( $data['lists'] as $list ) {
				$items[] = array(
					'id'   => (string) $list['id'],
					'name' => (string) $list['name'],
				);
			}
		}
		set_transient( $cache_key, $items, 15 * MINUTE_IN_SECONDS );
		return $items;
	}

	/**
	 * Fetch user (saved) templates.
	 *
	 * @param array $settings Provider settings (api_key).
	 * @param bool  $refresh  Force refresh.
	 * @return array|WP_Error
	 */
	public function get_templates( $settings, $refresh = false ) {
		$api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'API key is required.', 'campaignbridge' ) );
		}
		$parts = explode( '-', $api_key );
		if ( count( $parts ) < 2 ) {
			return new WP_Error( 'bad_key', __( 'Invalid Mailchimp API key format.', 'campaignbridge' ) );
		}
		$cache_key = 'cb_mc_templates_' . md5( $api_key );
		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached; }
		}
		$dc       = end( $parts );
		$endpoint = sprintf( 'https://%s.api.mailchimp.com/3.0', $dc );
		$resp     = wp_remote_get(
			$endpoint . '/templates?type=user&count=1000',
			array(
				'headers' => array( 'Authorization' => 'apikey ' . $api_key ),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'http_error', __( 'Failed to fetch templates.', 'campaignbridge' ) );
		}
		$data  = json_decode( wp_remote_retrieve_body( $resp ), true );
		$items = array();
		if ( ! empty( $data['templates'] ) && is_array( $data['templates'] ) ) {
			foreach ( $data['templates'] as $tpl ) {
				$items[] = array(
					'id'   => (int) $tpl['id'],
					'name' => (string) $tpl['name'],
				);
			}
		}
		set_transient( $cache_key, $items, 15 * MINUTE_IN_SECONDS );
		return $items;
	}

	/**
	 * Fetch template section keys.
	 *
	 * @param array $settings Plugin settings.
	 * @param bool  $refresh  Force refresh.
	 * @return array|WP_Error
	 */
	public function get_section_keys( $settings, $refresh = false ) {
		$api_key     = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$template_id = isset( $settings['template_id'] ) ? (int) $settings['template_id'] : 0;
		if ( empty( $api_key ) || empty( $template_id ) ) {
			return new WP_Error( 'missing_settings', __( 'API key and Template ID are required.', 'campaignbridge' ) );
		}
		$parts = explode( '-', $api_key );
		if ( count( $parts ) < 2 ) {
			return new WP_Error( 'bad_key', __( 'Invalid Mailchimp API key format.', 'campaignbridge' ) );
		}
		$cache_key = 'cb_mc_sections_' . md5( $api_key . '|' . $template_id );
		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached; }
		}
		$dc       = end( $parts );
		$endpoint = sprintf( 'https://%s.api.mailchimp.com/3.0', $dc );

		// Fetch template default content to discover editable sections keys.
		$resp = wp_remote_get(
			$endpoint . '/templates/' . rawurlencode( (string) $template_id ),
			array(
				'headers' => array(
					'Authorization' => 'apikey ' . $api_key,
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'http_error', __( 'Failed to fetch template.', 'campaignbridge' ) );
		}
		$data     = json_decode( wp_remote_retrieve_body( $resp ), true );
		$sections = array();
		if ( isset( $data['sections'] ) && is_array( $data['sections'] ) ) {
			$sections = array_keys( $data['sections'] );
		}
		set_transient( $cache_key, $sections, 15 * MINUTE_IN_SECONDS );
		return $sections;
	}
}
