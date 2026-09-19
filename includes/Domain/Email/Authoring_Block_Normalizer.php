<?php
/**
 * Authoring-block normalization port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts one parsed authoring block into canonical email semantics.
 *
 * Implementations read only the known serialization contract of supported
 * authoring blocks and return renderer-owned semantic attributes. Blocks they
 * do not own are returned unchanged so the compiler can reject them.
 */
interface Authoring_Block_Normalizer {
	/**
	 * Normalize one parsed block before renderer validation.
	 *
	 * @param Block_Node $block Parsed authoring block.
	 * @return Block_Node Canonical semantic block.
	 * @throws Invalid_Block_Attribute When known serialization is malformed or unsupported.
	 */
	public function normalize( Block_Node $block ): Block_Node;
}
