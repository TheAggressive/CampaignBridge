<?php
/**
 * Trusted outbound HTTPS origin policy.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The HTTPS host, or fixed family of hosts, one integration may contact.
 *
 * Every Http_Client request must declare an origin, and the integration fixes
 * that policy in code. A request URL built from stored or user data therefore
 * cannot carry a credential, or the request itself, to another host.
 *
 * A URL is allowed only when it is literally `https://<host>` followed by an
 * optional path and query. User information, ports, fragments, `@`,
 * backslashes, whitespace, and control characters are refused, so differences
 * between URL parsers cannot change which host receives the request.
 *
 * This is a destination allowlist for fixed public APIs. It does not inspect
 * DNS answers; an integration whose host comes from site data needs a
 * different boundary.
 */
final class Http_Origin {
	/** One lowercase DNS label. */
	private const LABEL = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

	/** A top-level label starts with a letter, which also excludes IPv4 literals. */
	private const TOP_LABEL = '[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?';

	/**
	 * Create a policy.
	 *
	 * @param string      $domain        Exact host, or the parent domain of a subdomain policy.
	 * @param string|null $label_pattern Anchored pattern for the single leading label, or null for an exact host.
	 */
	private function __construct( private readonly string $domain, private readonly ?string $label_pattern ) {}

	/**
	 * Allow exactly one host.
	 *
	 * @param string $host Lowercase fully qualified host name.
	 * @throws \InvalidArgumentException When the host is not a plain DNS name.
	 */
	public static function host( string $host ): self {
		if ( ! self::is_host_name( $host ) ) {
			throw new \InvalidArgumentException( 'A trusted origin needs a lowercase DNS host name.' );
		}

		return new self( $host, null );
	}

	/**
	 * Allow one subdomain label of a fixed parent domain.
	 *
	 * Used for providers that shard accounts across hosts, such as a data
	 * center prefix. The pattern is matched against the first label alone,
	 * and the rest of the host must equal the parent exactly.
	 *
	 * @param string $label_pattern Regular-expression fragment for the first label, without delimiters.
	 * @param string $domain        Lowercase parent domain.
	 * @throws \InvalidArgumentException When the parent or label pattern is unsafe.
	 */
	public static function subdomain( string $label_pattern, string $domain ): self {
		if ( ! self::is_host_name( $domain ) || '' === $label_pattern || str_contains( $label_pattern, '/' ) ) {
			throw new \InvalidArgumentException( 'A trusted subdomain origin needs one label pattern and a lowercase parent domain.' );
		}

		return new self( $domain, '/^(?:' . $label_pattern . ')$/D' );
	}

	/**
	 * Whether a request URL targets this origin over HTTPS.
	 *
	 * @param string $url Absolute request URL.
	 */
	public function allows( string $url ): bool {
		if ( 1 !== preg_match( '#^https://([^/?]+)(?:[/?][^\s\\\\@\#\x00-\x1f\x7f]*)?$#D', $url, $matches ) ) {
			return false;
		}
		$host = $matches[1];
		if ( ! self::is_host_name( $host ) ) {
			return false;
		}
		if ( null === $this->label_pattern ) {
			return $host === $this->domain;
		}

		// Only the first label may vary; the rest must be the parent exactly.
		$dot = strpos( $host, '.' );

		return false !== $dot
			&& substr( $host, $dot + 1 ) === $this->domain
			&& 1 === preg_match( $this->label_pattern, substr( $host, 0, $dot ) );
	}

	/**
	 * Whether a value is a lowercase multi-label DNS name, not an IP literal.
	 *
	 * @param string $host Candidate host.
	 */
	private static function is_host_name( string $host ): bool {
		return strlen( $host ) <= 253 && 1 === preg_match( '/^' . self::LABEL . '(?:\.' . self::LABEL . ')*\.' . self::TOP_LABEL . '$/D', $host );
	}
}
