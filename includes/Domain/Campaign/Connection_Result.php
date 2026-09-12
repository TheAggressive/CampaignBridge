<?php
/**
 * Provider connection verification result.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The outcome of a provider connection verification.
 *
 * Carries either success with normalized account details or a
 * {@see Provider_Error}. Provider-specific payloads never leak into
 * this object.
 */
final class Connection_Result {
	/**
	 * Create a connection result value.
	 *
	 * @param bool                 $success  Whether verification succeeded.
	 * @param Provider_Error|null  $error   Normalized error when verification failed.
	 * @param array<string, mixed> $account_details Normalized account details on success.
	 */
	private function __construct(
		private readonly bool $success,
		private readonly ?Provider_Error $error,
		private readonly array $account_details
	) {}

	/**
	 * Create a successful verification result.
	 *
	 * @param array<string, mixed> $details Normalized account details.
	 */
	public static function success( array $details = array() ): self {
		return new self( true, null, $details );
	}

	/**
	 * Create a failed verification result.
	 *
	 * @param Provider_Error $error Normalized provider error.
	 */
	public static function failure( Provider_Error $error ): self {
		return new self( false, $error, array() );
	}

	/** Determine whether verification succeeded. */
	public function is_success(): bool {
		return $this->success;
	}

	/** Get the normalized error, or null on success. */
	public function error(): ?Provider_Error {
		return $this->error;
	}

	/** Get normalized account details (empty on failure).
	 *
	 * @return array<string, mixed>
	 */
	public function account_details(): array {
		return $this->account_details;
	}

	/**
	 * Serialize the result for APIs and logs.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$result = array( 'verified' => $this->success );

		if ( null !== $this->error ) {
			$result['error'] = $this->error->to_array();
		} else {
			$result['account'] = $this->account_details;
		}

		return $result;
	}
}
