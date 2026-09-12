<?php
/**
 * Connection_Result value object.
 *
 * Immutable result of a provider connection check. Wraps a success/failure
 * state and an optional Provider_Error, providing the domain-typed contract
 * that provider implementations return from verify_connection().
 *
 * @package CampaignBridge\Domain\Campaign
 */

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable result of a provider connection check.
 */
final class Connection_Result {

	/**
	 * Whether the connection was verified successfully.
	 *
	 * @var bool
	 */
	private bool $connected;

	/**
	 * The error that caused the connection failure, if any.
	 *
	 * @var Provider_Error|null
	 */
	private ?Provider_Error $error;

	/**
	 * Private constructor — use the static factories.
	 *
	 * @param bool                $connected Whether the connection succeeded.
	 * @param Provider_Error|null $error     The error, if the connection failed.
	 */
	private function __construct( bool $connected, ?Provider_Error $error ) {
		$this->connected = $connected;
		$this->error     = $error;
	}

	/**
	 * Create a successful connection result.
	 */
	public static function success(): self {
		return new self( true, null );
	}

	/**
	 * Create a failed connection result.
	 *
	 * @param Provider_Error $error The error that caused the failure.
	 */
	public static function failure( Provider_Error $error ): self {
		return new self( false, $error );
	}

	/**
	 * Whether the connection was verified successfully.
	 */
	public function connected(): bool {
		return $this->connected;
	}

	/**
	 * The error that caused the connection failure, or null on success.
	 */
	public function error(): ?Provider_Error {
		return $this->error;
	}
}
