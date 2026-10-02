<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Port method contracts are documented by Provider_Draft_Gateway.
/**
 * Mailchimp remote draft adapter.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Http_Client_Instance;
use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Draft_Content;
use CampaignBridge\Domain\Provider\Draft_Outcome;
use CampaignBridge\Domain\Provider\Provider_Draft_Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates one regular Mailchimp campaign draft and uploads its content.
 *
 * The create request is never retried: Mailchimp has no idempotency key for
 * campaign creation, so a lost response may hide a created draft and is
 * reported as ambiguous. The content upload is an idempotent PUT. No
 * schedule or send endpoint is ever called. Response bodies are read only for
 * the draft ID and never leave this class.
 */
final class Mailchimp_Draft_Gateway implements Provider_Draft_Gateway {
	/** Seconds to wait for a draft mutation before the outcome is unknown. */
	private const TIMEOUT = 20;

	/**
	 * Injected HTTP transport.
	 *
	 * @var Http_Client_Interface
	 */
	private readonly Http_Client_Interface $http;

	public function __construct( ?Http_Client_Interface $http = null ) {
		$this->http = $http ?? new Http_Client_Instance();
	}

	public function slug(): string {
		return 'mailchimp';
	}

	public function create_draft( array $settings, Draft_Content $content ): Draft_Outcome {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Draft_Outcome::from_create_error( Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION ) );
		}

		$response = $this->http->post(
			Mailchimp_Provider::build_api_url( $api_key, '/campaigns' ),
			$this->json_request(
				$api_key,
				array(
					'type'       => 'regular',
					'recipients' => array( 'list_id' => $content->audience_id() ),
					'settings'   => array(
						'subject_line' => $content->subject(),
						'preview_text' => $content->preview_text(),
						'title'        => $content->correlation(),
						'from_name'    => $content->from_name(),
						'reply_to'     => $content->reply_to(),
					),
				),
				false
			)
		);
		if ( is_wp_error( $response ) ) {
			return Draft_Outcome::from_create_error( Mailchimp_Errors::from_transport( $response ) );
		}
		$status = $response['status_code'] ?? 0;
		if ( ! is_int( $status ) || 200 !== $status ) {
			return Draft_Outcome::from_create_error( Mailchimp_Errors::from_status( is_int( $status ) ? $status : 0 ) );
		}

		$decoded   = json_decode( is_string( $response['body'] ?? null ) ? $response['body'] : '', true );
		$remote_id = is_array( $decoded ) ? ( $decoded['id'] ?? null ) : null;
		if ( ! is_string( $remote_id ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $remote_id ) ) {
			// Mailchimp accepted the request but the draft ID is unreadable: it may exist.
			return Draft_Outcome::from_create_error( Mailchimp_Errors::unexpected_response() );
		}

		return $this->upload_content( $settings, $remote_id, $content );
	}

	public function upload_content( array $settings, string $remote_id, Draft_Content $content ): Draft_Outcome {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Draft_Outcome::content_pending( $remote_id, Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION ) );
		}

		$response = $this->http->put(
			Mailchimp_Provider::build_api_url( $api_key, '/campaigns/' . rawurlencode( $remote_id ) . '/content' ),
			$this->json_request(
				$api_key,
				array(
					'html'       => $content->html(),
					'plain_text' => $content->text(),
				),
				true
			)
		);
		if ( is_wp_error( $response ) ) {
			return Draft_Outcome::content_pending( $remote_id, Mailchimp_Errors::from_transport( $response ) );
		}
		$status = $response['status_code'] ?? 0;
		if ( ! is_int( $status ) || 200 !== $status ) {
			return Draft_Outcome::content_pending( $remote_id, Mailchimp_Errors::from_status( is_int( $status ) ? $status : 0 ) );
		}

		return Draft_Outcome::created( $remote_id );
	}

	/**
	 * Build an authenticated JSON request.
	 *
	 * @param array<string, mixed> $body JSON body.
	 * @return array<string, mixed>
	 */
	private function json_request( string $api_key, array $body, bool $may_retry ): array {
		return array(
			'headers'              => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'                 => (string) wp_json_encode( $body ),
			'timeout'              => self::TIMEOUT,
			'campaignbridge_retry' => $may_retry,
		);
	}

	/** @param array<string, mixed> $settings Decrypted settings. */
	private function api_key( array $settings ): ?string {
		$api_key = $settings['api_key'] ?? null;

		return is_string( $api_key ) && ( new Mailchimp_Provider() )->is_valid_api_key( $api_key ) ? $api_key : null;
	}
}
