<?php
/**
 * Email design validation error.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carries a stable diagnostic code and safe manifest path. */
final class Email_Design_Error extends \InvalidArgumentException {
	/**
	 * Create an error with its stable code and safe path.
	 *
	 * @param string $diagnostic_code Stable diagnostic code.
	 * @param string $design_path     Safe manifest path.
	 * @param string $message         Operator-safe reason.
	 */
	public function __construct(
		private readonly string $diagnostic_code,
		private readonly string $design_path,
		string $message
	) {
		parent::__construct( $message );
	}

	/** Stable CampaignBridge diagnostic code. */
	public function diagnostic_code(): string {
		return $this->diagnostic_code;
	}

	/** JSON-style path to the invalid value. */
	public function design_path(): string {
		return $this->design_path;
	}
}
