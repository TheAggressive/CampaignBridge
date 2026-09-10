<?php
/**
 * Admin copy and records for the brand kit.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Presets;

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
			Brand_Kit::SLOT_ON_BRAND   => __( 'Text that sits on the brand color.', 'campaignbridge' ),
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
	 * Translated labels for each font slot.
	 *
	 * @return array<string, string>
	 */
	public static function font_slot_labels(): array {
		return array(
			'heading' => __( 'Headings', 'campaignbridge' ),
			'body'    => __( 'Body', 'campaignbridge' ),
			'button'  => __( 'Buttons', 'campaignbridge' ),
		);
	}

	/**
	 * Font options in the REST and editor shape.
	 *
	 * @param Brand_Kit|null $kit Active kit, when a custom font may be present.
	 * @return array<int, array{slug: string, name: string, type: string, family: string, url: string|null}>
	 */
	public static function font_options( ?Brand_Kit $kit = null ): array {
		$options = array();
		foreach ( Design_Presets::fonts() as $font ) {
			$options[] = array(
				'slug'   => $font['slug'],
				'name'   => $font['name'],
				'type'   => $font['type'],
				'family' => $font['family'],
				'url'    => $font['url'],
			);
		}

		$custom = $kit?->custom_font();
		if ( null !== $custom ) {
			$options[] = array(
				'slug'   => Brand_Kit::CUSTOM_FONT_SLUG,
				'name'   => $custom['name'],
				'type'   => 'web',
				'family' => $custom['family'],
				'url'    => $custom['url'],
			);
		}
		return $options;
	}

	/**
	 * REST and localized payload for the Brand tab.
	 *
	 * @param Brand_Kit $kit Active kit.
	 * @return array{source: string, slots: array<int, array{id: string, name: string, description: string, color: string}>, fonts: array<string, string>, fontOptions: array<int, array{slug: string, name: string, type: string, family: string, url: string|null}>, fontSlots: array<string, string>}
	 */
	public static function payload( Brand_Kit $kit ): array {
		return array(
			'source'      => $kit->source(),
			'slots'       => self::records( $kit ),
			'fonts'       => $kit->fonts(),
			'fontOptions' => self::font_options( $kit ),
			'fontSlots'   => self::font_slot_labels(),
		);
	}
}
