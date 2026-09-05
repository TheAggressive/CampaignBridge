<?php
/**
 * Admin copy and records for the brand kit.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

use CampaignBridge\Domain\Email\Brand_Kit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slot labels shown on the Brand settings tab and in the REST payload.
 */
final class Brand_Kit_Copy {
	/**
	 * Translated description for each slot.
	 *
	 * @return array<string, string>
	 */
	public static function descriptions(): array {
		return array(
			Brand_Kit::SLOT_TEXT       => __( 'Body and heading text.', 'campaignbridge' ),
			Brand_Kit::SLOT_SECONDARY  => __( 'Captions, footers, and supporting copy.', 'campaignbridge' ),
			Brand_Kit::SLOT_BACKGROUND => __( 'The email canvas.', 'campaignbridge' ),
			Brand_Kit::SLOT_CARD       => __( 'Sections, cards, and inset panels.', 'campaignbridge' ),
			Brand_Kit::SLOT_BORDER     => __( 'Dividers and rules.', 'campaignbridge' ),
			Brand_Kit::SLOT_BRAND      => __( 'Buttons, links, and emphasis.', 'campaignbridge' ),
			Brand_Kit::SLOT_ON_BRAND   => __( 'Text that sits on the brand colour.', 'campaignbridge' ),
		);
	}

	/**
	 * DataViews records for the current kit.
	 *
	 * @param Brand_Kit $kit Active kit.
	 * @return array<int, array{id: string, name: string, description: string, color: string}>
	 */
	public static function records( Brand_Kit $kit ): array {
		$descriptions = self::descriptions();
		$records      = array();

		foreach ( $kit->colors() as $preset ) {
			$records[] = array(
				'id'          => $preset['slug'],
				'name'        => $preset['name'],
				'description' => $descriptions[ $preset['slug'] ] ?? '',
				'color'       => $preset['color'],
			);
		}

		return $records;
	}

	/**
	 * REST and localized payload for the Brand tab.
	 *
	 * @param Brand_Kit $kit Active kit.
	 * @return array{source: string, slots: array<int, array{id: string, name: string, description: string, color: string}>}
	 */
	public static function payload( Brand_Kit $kit ): array {
		return array(
			'source' => $kit->source(),
			'slots'  => self::records( $kit ),
		);
	}
}
