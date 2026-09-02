<?php
/**
 * Invalid persisted email-block attribute.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carries a safe attribute name and reason into compiler diagnostics. */
final class Invalid_Block_Attribute extends \InvalidArgumentException {
	/**
	 * Create an invalid-attribute failure.
	 *
	 * @param string $attribute Stable semantic attribute path.
	 * @param string $reason    Safe validation reason.
	 */
	public function __construct(
		private readonly string $attribute,
		string $reason
	) {
		parent::__construct( sprintf( 'Attribute %s %s', $attribute, $reason ) );
	}

	/** Get the stable semantic attribute path. */
	public function attribute(): string {
		return $this->attribute;
	}
}
