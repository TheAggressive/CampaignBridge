<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Port method contracts are documented by Provider_Discovery.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Invalid audience IDs fail closed before any request.
/**
 * Mailchimp reference discovery adapter.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Http_Client_Instance;
use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Discovered_Audience;
use CampaignBridge\Domain\Provider\Discovered_Item;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovered_Segment;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Values;
use CampaignBridge\Domain\Provider\Provider_Capabilities;
use CampaignBridge\Domain\Provider\Provider_Discovery;
use CampaignBridge\Domain\Provider\Sender_Identity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers Mailchimp audiences, merge fields, segments, and tags.
 *
 * Requests ask Mailchimp only for the fields CampaignBridge keeps, are
 * bounded to Discovery_Batch::MAX_ITEMS, and report `complete: false` when
 * Mailchimp holds more or an item fails validation. Responses are normalized
 * here; nothing Mailchimp-shaped leaves this class. Member records are never
 * requested.
 */
final class Mailchimp_Discovery implements Provider_Discovery {
	private const SLUG = 'mailchimp';

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
		return self::SLUG;
	}

	public function capabilities(): Provider_Capabilities {
		return Provider_Capabilities::from_flags( self::SLUG, Mailchimp_Provider::CAPABILITIES );
	}

	public function account_key( array $settings ): ?string {
		$api_key = $this->api_key( $settings );

		return null === $api_key ? null : hash( 'sha256', self::SLUG . "\0" . $api_key );
	}

	public function discover_audiences( array $settings ): Discovery_Batch|Provider_Error {
		return $this->collect(
			$settings,
			'/lists?' . $this->fields_query( 'lists', array( 'id', 'name', 'stats.member_count', 'campaign_defaults.from_name', 'campaign_defaults.from_email' ) ),
			'lists',
			Discovery_Kind::AUDIENCES,
			static function ( array $audience ): Discovered_Item {
				$defaults = is_array( $audience['campaign_defaults'] ?? null ) ? $audience['campaign_defaults'] : array();
				try {
					$sender = Sender_Identity::create( $defaults['from_name'] ?? null, $defaults['from_email'] ?? null );
				} catch ( \InvalidArgumentException ) {
					$sender = null;
				}
				$stats = is_array( $audience['stats'] ?? null ) ? $audience['stats'] : array();

				return Discovered_Audience::create( $audience['id'] ?? null, $audience['name'] ?? null, $stats['member_count'] ?? null, $sender );
			}
		);
	}

	public function discover_merge_fields( array $settings, string $audience_id ): Discovery_Batch|Provider_Error {
		return $this->collect(
			$settings,
			'/lists/' . rawurlencode( Discovery_Values::remote_id( $audience_id, 'Audience ID' ) ) . '/merge-fields?' . $this->fields_query( 'merge_fields', array( 'tag', 'name', 'type', 'required' ) ),
			'merge_fields',
			Discovery_Kind::MERGE_FIELDS,
			static fn ( array $field ): Discovered_Item => Discovered_Merge_Field::create( $field['tag'] ?? null, $field['name'] ?? null, $field['type'] ?? null, $field['required'] ?? null )
		);
	}

	public function discover_segments( array $settings, string $audience_id ): Discovery_Batch|Provider_Error {
		return $this->collect(
			$settings,
			'/lists/' . rawurlencode( Discovery_Values::remote_id( $audience_id, 'Audience ID' ) ) . '/segments?' . $this->fields_query( 'segments', array( 'id', 'name', 'type', 'member_count' ) ),
			'segments',
			Discovery_Kind::SEGMENTS,
			static fn ( array $segment ): Discovered_Item => Discovered_Segment::create(
				$segment['id'] ?? null,
				$segment['name'] ?? null,
				self::segment_kind( $segment['type'] ?? null ),
				$segment['member_count'] ?? null
			)
		);
	}

	/**
	 * Fetch one bounded collection and normalize each entry.
	 *
	 * @param array<string, mixed>                       $settings  Decrypted settings.
	 * @param callable(array<string, mixed>): Discovered_Item $normalize Entry normalizer.
	 */
	private function collect( array $settings, string $endpoint, string $collection, string $kind, callable $normalize ): Discovery_Batch|Provider_Error {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION );
		}

		$response = $this->http->get(
			Mailchimp_Provider::build_api_url( $api_key, $endpoint ),
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $api_key ) )
		);
		if ( is_wp_error( $response ) ) {
			return Mailchimp_Errors::from_transport( $response );
		}
		$status = $response['status_code'] ?? 0;
		if ( ! is_int( $status ) || 200 !== $status ) {
			return Mailchimp_Errors::from_status( is_int( $status ) ? $status : 0 );
		}

		$decoded = json_decode( is_string( $response['body'] ?? null ) ? $response['body'] : '', true );
		if ( ! is_array( $decoded ) || ! is_array( $decoded[ $collection ] ?? null ) ) {
			return Mailchimp_Errors::unexpected_response();
		}

		$entries  = array_slice( array_values( $decoded[ $collection ] ), 0, Discovery_Batch::MAX_ITEMS );
		$items    = array();
		$complete = true;
		foreach ( $entries as $entry ) {
			try {
				if ( ! is_array( $entry ) ) {
					throw new \InvalidArgumentException( 'Mailchimp entry is not an object.' );
				}
				$items[] = $normalize( $entry );
			} catch ( \InvalidArgumentException ) {
				// An unusable remote entry is omitted; the list is no longer complete.
				$complete = false;
			}
		}
		$total = $decoded['total_items'] ?? null;
		if ( ! is_int( $total ) || count( $entries ) < $total || count( $decoded[ $collection ] ) > count( $entries ) ) {
			$complete = false;
		}

		return Discovery_Batch::create( $kind, $items, $complete );
	}

	/**
	 * Build a bounded query that requests only the kept fields.
	 *
	 * @param array<int, string> $fields Fields relative to the collection.
	 */
	private function fields_query( string $collection, array $fields ): string {
		$requested   = array_map( static fn ( string $field ): string => $collection . '.' . $field, $fields );
		$requested[] = 'total_items';

		return http_build_query(
			array(
				'count'  => Discovery_Batch::MAX_ITEMS,
				'offset' => 0,
				'fields' => implode( ',', $requested ),
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/** Mailchimp tags are static segments; saved and fuzzy segments are conditions. */
	private static function segment_kind( mixed $type ): string {
		return 'static' === $type ? Discovered_Segment::KIND_TAG : Discovered_Segment::KIND_SEGMENT;
	}

	/** @param array<string, mixed> $settings Decrypted settings. */
	private function api_key( array $settings ): ?string {
		$api_key = $settings['api_key'] ?? null;

		return is_string( $api_key ) && ( new Mailchimp_Provider() )->is_valid_api_key( $api_key ) ? $api_key : null;
	}
}
