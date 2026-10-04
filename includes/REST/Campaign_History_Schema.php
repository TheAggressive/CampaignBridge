<?php
/**
 * Published schemas for the campaign history routes.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Workflow\Campaign\Campaign_History;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Response and argument schemas for campaign audit history and delivery attempts. */
final class Campaign_History_Schema {
	/**
	 * Paging arguments shared by both routes.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function page_args(): array {
		return array(
			'page'     => array(
				'description' => __( 'Current page, newest records first.', 'campaignbridge' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
				'maximum'     => 1000000,
			),
			'per_page' => array(
				'description' => __( 'Maximum number of records returned.', 'campaignbridge' ),
				'type'        => 'integer',
				'default'     => 50,
				'minimum'     => 1,
				'maximum'     => Campaign_History::MAX_PER_PAGE,
			),
		);
	}

	/**
	 * Audit history collection document.
	 *
	 * @return array<string, mixed>
	 */
	public static function history(): array {
		return self::collection(
			'campaignbridge-campaign-history',
			array(
				'id'         => self::text(),
				'action'     => self::text(),
				'result'     => self::text(),
				'actor'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'id', 'name' ),
					'properties'           => array(
						'id'   => array( 'type' => array( 'integer', 'null' ) ),
						'name' => array( 'type' => array( 'string', 'null' ) ),
					),
				),
				'context'    => array(
					'description' => __( 'Redacted, bounded audit context.', 'campaignbridge' ),
					'type'        => 'object',
				),
				'created_at' => self::time(),
			)
		);
	}

	/**
	 * Delivery attempt collection document.
	 *
	 * @return array<string, mixed>
	 */
	public static function attempts(): array {
		return self::collection(
			'campaignbridge-campaign-attempts',
			array(
				'id'                  => self::text(),
				'operation'           => self::text(),
				'status'              => self::text(),
				'retryability'        => self::text(),
				'has_idempotency_key' => array( 'type' => 'boolean' ),
				'remote_correlation'  => array( 'type' => array( 'string', 'null' ) ),
				'created_at'          => self::time(),
				'updated_at'          => self::time(),
			)
		);
	}

	/**
	 * Paged collection document.
	 *
	 * @param string                              $title Schema title.
	 * @param array<string, array<string, mixed>> $item  Item properties, all required.
	 * @return array<string, mixed>
	 */
	private static function collection( string $title, array $item ): array {
		$count = array(
			'type'    => 'integer',
			'minimum' => 0,
		);

		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => $title,
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'items', 'pagination' ),
			'properties'           => array(
				'items'      => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array_keys( $item ),
						'properties'           => $item,
					),
				),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'page', 'per_page', 'total', 'total_pages' ),
					'properties'           => array(
						'page'        => $count,
						'per_page'    => $count,
						'total'       => $count,
						'total_pages' => $count,
					),
				),
			),
		);
	}

	/**
	 * Plain string property.
	 *
	 * @return array<string, string>
	 */
	private static function text(): array {
		return array( 'type' => 'string' );
	}

	/**
	 * RFC 3339 timestamp property.
	 *
	 * @return array<string, string>
	 */
	private static function time(): array {
		return array(
			'type'   => 'string',
			'format' => 'date-time',
		);
	}
}
