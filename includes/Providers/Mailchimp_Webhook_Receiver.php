<?php
/**
 * Mailchimp webhook verification adapter.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Domain\Provider\Provider_Webhook_Receiver;
use CampaignBridge\Domain\Webhook\Signed_Delivery;
use CampaignBridge\Domain\Webhook\Webhook_Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authenticates Mailchimp's signed webhook deliveries.
 *
 * Mailchimp sends `X-Mailchimp-Signature: t={unix seconds},v1={hex}`, where
 * the signature is HMAC-SHA256 of `{t}.{raw body}` keyed with the webhook's
 * signing secret, and asks receivers to refuse deliveries older than five
 * minutes. The body is form-encoded; only a `campaign` event's campaign ID
 * is read, and only after the signature matched.
 */
final class Mailchimp_Webhook_Receiver implements Provider_Webhook_Receiver {
	/** Header, as WordPress normalizes request header names. */
	public const SIGNATURE_HEADER = 'x_mailchimp_signature';

	/** Seconds a signature stays fresh, as Mailchimp recommends. */
	public const TOLERANCE = 300;

	/** Seconds a signature may appear to come from the future, for clock skew. */
	public const FUTURE_SKEW = 60;

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mailchimp';
	}

	/**
	 * {@inheritDoc}
	 */
	public function authenticate( string $raw_body, array $headers, array $secrets, int $now ): ?Signed_Delivery {
		$parsed = self::parse_signature( $headers[ self::SIGNATURE_HEADER ] ?? '' );
		if ( null === $parsed ) {
			return null;
		}
		[ $timestamp, $signatures ] = $parsed;
		if ( $timestamp < $now - self::TOLERANCE || $timestamp > $now + self::FUTURE_SKEW ) {
			return null;
		}

		foreach ( $secrets as $secret ) {
			if ( '' === $secret ) {
				continue;
			}
			$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
			foreach ( $signatures as $signature ) {
				if ( hash_equals( $expected, $signature ) ) {
					return new Signed_Delivery( hash( 'sha256', 'mailchimp|' . $timestamp . '|' . $signature ), $timestamp + self::TOLERANCE );
				}
			}
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function event( string $raw_body ): ?Webhook_Event {
		if ( '' === $raw_body ) {
			return null;
		}
		$fields = array();
		parse_str( $raw_body, $fields );
		$type = $fields['type'] ?? null;
		if ( ! is_string( $type ) || 1 !== preg_match( '/^[a-z_]{1,32}$/', $type ) ) {
			return null;
		}
		if ( 'campaign' !== $type ) {
			// Subscriber events are never requested and never read.
			return Webhook_Event::ignored();
		}

		$data = $fields['data'] ?? null;
		$id   = is_array( $data ) ? ( $data['id'] ?? null ) : null;
		if ( ! is_string( $id ) || 1 !== preg_match( '/^[A-Za-z0-9]{1,64}$/', $id ) ) {
			return null;
		}

		return Webhook_Event::campaign( $id );
	}

	/**
	 * The timestamp and v1 signatures of a signature header.
	 *
	 * @param string $header Header value.
	 * @return array{0: int, 1: array<int, string>}|null
	 */
	private static function parse_signature( string $header ): ?array {
		if ( '' === $header || strlen( $header ) > 1024 ) {
			return null;
		}
		$timestamp  = null;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				return null;
			}
			[ $name, $value ] = $pair;
			if ( 't' === $name ) {
				if ( null !== $timestamp || 1 !== preg_match( '/^[0-9]{1,12}$/', $value ) ) {
					return null;
				}
				$timestamp = (int) $value;
			} elseif ( 'v1' === $name && 1 === preg_match( '/^[0-9a-f]{64}$/', $value ) ) {
				$signatures[] = $value;
			}
		}

		return null === $timestamp || array() === $signatures ? null : array( $timestamp, $signatures );
	}
}
