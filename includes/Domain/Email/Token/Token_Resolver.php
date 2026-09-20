<?php
/**
 * Provider-neutral token resolution for compiler values.
 *
 * @package CampaignBridge
 * @since   1.0.0
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email\Token;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves CampaignBridge-owned tokens and preserves provider-resolved tokens.
 *
 * Token_Parser remains the only parser and Token_Registry the only vocabulary.
 * This class decides what a successfully parsed token becomes in one value:
 *
 * - A CampaignBridge-owned token (`requires_provider_resolution() === false`)
 *   is replaced by its value from the explicit compile-context map, or the
 *   value fails closed as unresolved.
 * - A provider-resolved token stays in its canonical `{{cb:...}}` form so a
 *   provider adapter can translate it at handoff.
 *
 * Values come only from the caller-supplied map stored under CONTEXT_KEY. The
 * resolver reads no options, database rows, provider APIs, or global state.
 */
final class Token_Resolver {

	/**
	 * Render_Context metadata key holding CampaignBridge-owned token values.
	 *
	 * The value is a map of canonical token ID to plain-text value, for example
	 * `array( 'cb:organization.name' => 'Example Company' )`.
	 */
	public const CONTEXT_KEY = 'token_values';

	/** Plain-text field; output is escaped by the renderer. */
	public const CONTEXT_TEXT = 'text';

	/** Supported inline rich-text field; link destinations use URL rules. */
	public const CONTEXT_RICH_TEXT = 'rich_text';

	/** Link destination: a literal HTTP(S) URL or exactly one URL token. */
	public const CONTEXT_URL = 'url';

	/** Maximum characters for one CampaignBridge-owned token value. */
	public const MAX_VALUE_LENGTH = 500;

	/** Prefix that marks a CampaignBridge token attempt. */
	private const TOKEN_OPEN = '{{cb:';

	/**
	 * Create a resolver over the canonical parser and registry.
	 *
	 * @param Token_Registry $registry Token vocabulary.
	 * @param Token_Parser   $parser   Token parser.
	 */
	public function __construct(
		private readonly Token_Registry $registry,
		private readonly Token_Parser $parser
	) {}

	/**
	 * Create the resolver for the default v1 registry.
	 *
	 * @return static
	 */
	public static function default(): self {
		return new self( Token_Registry::default(), new Token_Parser() );
	}

	/**
	 * Validate the compile-context value map.
	 *
	 * Only registered CampaignBridge-owned tokens may carry a value, so the
	 * context can never hold subscriber data or pre-empt provider resolution.
	 * Values must be bounded plain text and cannot contain token syntax.
	 *
	 * @param mixed $values Candidate map; null means no values.
	 *
	 * @return Token_Diagnostic|null Error diagnostic, or null when valid.
	 */
	public function validate_context_values( mixed $values ): ?Token_Diagnostic {
		if ( null === $values ) {
			return null;
		}

		if ( ! is_array( $values ) ) {
			return Token_Diagnostic::invalid_context_value();
		}

		foreach ( $values as $id => $value ) {
			$definition = is_string( $id ) ? $this->registry->get( $id ) : null;
			if ( null === $definition || $definition->requires_provider_resolution() || ! $this->is_plain_value( $value ) ) {
				return Token_Diagnostic::invalid_context_value();
			}
		}

		return null;
	}

	/**
	 * Resolve one value in the given field context.
	 *
	 * @param string               $context One of the CONTEXT_* constants.
	 * @param string               $value   Field value.
	 * @param array<string, mixed> $values  Validated CampaignBridge-owned values.
	 *
	 * @return Token_Resolution
	 *
	 * @throws \InvalidArgumentException When the context is not supported.
	 */
	public function resolve( string $context, string $value, array $values ): Token_Resolution {
		return match ( $context ) {
			self::CONTEXT_TEXT      => $this->text( $value, $values ),
			self::CONTEXT_RICH_TEXT => $this->rich_text( $value, $values ),
			self::CONTEXT_URL       => $this->url( $value, $values ),
			default                 => throw new \InvalidArgumentException( sprintf( 'Unsupported token context: %s', $context ) ),
		};
	}

	/**
	 * Reject token syntax in a field that does not accept tokens.
	 *
	 * @param string $value Field value.
	 *
	 * @return Token_Resolution
	 */
	public function reject( string $value ): Token_Resolution {
		$position = strpos( $value, self::TOKEN_OPEN );

		return false === $position
			? Token_Resolution::success( $value )
			: Token_Resolution::failure( array( Token_Diagnostic::unsupported_context( $position ) ) );
	}

	/**
	 * Check whether a value is exactly one provider-resolved URL token.
	 *
	 * This is the final-boundary check for link destinations that are not
	 * literal HTTP(S) URLs.
	 *
	 * @param string $value Candidate link destination.
	 *
	 * @return bool
	 */
	public function is_url_token( string $value ): bool {
		if ( 1 !== preg_match( '/^\{\{(cb:[^{}]+)\}\}$/', $value, $matches ) ) {
			return false;
		}

		$definition = $this->registry->get( $matches[1] );

		return null !== $definition
			&& Token_Definition::VALUE_TYPE_URL === $definition->get_value_type()
			&& $definition->requires_provider_resolution();
	}

	/**
	 * Resolve tokens in plain text.
	 *
	 * @param string               $value  Plain text.
	 * @param array<string, mixed> $values CampaignBridge-owned values.
	 *
	 * @return Token_Resolution
	 */
	private function text( string $value, array $values ): Token_Resolution {
		$parse = $this->parser->parse( $value, $this->registry );
		if ( ! $parse->is_successful() ) {
			return Token_Resolution::failure( $parse->get_errors() );
		}

		$replacements = array();
		$missing      = array();
		foreach ( $parse->get_tokens() as $definition ) {
			if ( $definition->requires_provider_resolution() ) {
				continue;
			}

			$expression = '{{' . $definition->get_id() . '}}';
			$local      = $values[ $definition->get_id() ] ?? null;
			if ( ! is_string( $local ) ) {
				$missing[ $expression ] = Token_Diagnostic::unresolved_token( (int) strpos( $value, $expression ) );
				continue;
			}

			$replacements[ $expression ] = $local;
		}

		if ( array() !== $missing ) {
			return Token_Resolution::failure( array_values( $missing ) );
		}

		return Token_Resolution::success( array() === $replacements ? $value : strtr( $value, $replacements ) );
	}

	/**
	 * Resolve tokens in the supported inline rich-text grammar.
	 *
	 * Text runs are entity-decoded before parsing, so an encoded brace cannot
	 * smuggle token syntax past validation, and resolved runs are re-encoded.
	 * A token must sit inside one text run; one split by markup would surface
	 * only in the plain-text artifact, so it fails closed.
	 *
	 * @param string               $html   Rich text.
	 * @param array<string, mixed> $values CampaignBridge-owned values.
	 *
	 * @return Token_Resolution
	 */
	private function rich_text( string $html, array $values ): Token_Resolution {
		$segments = preg_split( '/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		if ( false === $segments ) {
			return Token_Resolution::failure( array( Token_Diagnostic::malformed_token( 0 ) ) );
		}

		$output  = '';
		$text    = '';
		$in_runs = 0;
		foreach ( $segments as $segment ) {
			$resolution = str_starts_with( $segment, '<' )
				? $this->tag( $segment, $values )
				: $this->text_run( $segment, $values, $text, $in_runs );

			if ( ! $resolution->is_successful() ) {
				return $resolution;
			}

			$output .= (string) $resolution->value();
		}

		$joined = $this->parser->parse( $text, $this->registry );
		if ( ! $joined->is_successful() ) {
			return Token_Resolution::failure( $joined->get_errors() );
		}

		if ( substr_count( $text, self::TOKEN_OPEN ) !== $in_runs ) {
			return Token_Resolution::failure( array( Token_Diagnostic::malformed_token( 0 ) ) );
		}

		return Token_Resolution::success( $output );
	}

	/**
	 * Resolve one rich-text run.
	 *
	 * @param string               $segment Encoded text run.
	 * @param array<string, mixed> $values  CampaignBridge-owned values.
	 * @param string               $text    Decoded text accumulated across runs.
	 * @param int                  $in_runs Token attempts found inside single runs.
	 *
	 * @return Token_Resolution
	 */
	private function text_run( string $segment, array $values, string &$text, int &$in_runs ): Token_Resolution {
		$decoded  = html_entity_decode( $segment, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text    .= $decoded;
		$in_runs += substr_count( $decoded, self::TOKEN_OPEN );

		$resolution = $this->text( $decoded, $values );
		if ( ! $resolution->is_successful() || $resolution->value() === $decoded ) {
			return $resolution->is_successful() ? Token_Resolution::success( $segment ) : $resolution;
		}

		return Token_Resolution::success( $this->encode( (string) $resolution->value() ) );
	}

	/**
	 * Resolve a link destination inside one rich-text tag.
	 *
	 * Token syntax is accepted only as the complete `href` of an anchor.
	 *
	 * @param string               $segment Raw tag.
	 * @param array<string, mixed> $values  CampaignBridge-owned values.
	 *
	 * @return Token_Resolution
	 */
	private function tag( string $segment, array $values ): Token_Resolution {
		if ( 1 !== preg_match( '/^(<a\s+href=)(["\'])([^"\']*)\2(.*)$/is', $segment, $matches ) ) {
			return $this->reject( $segment );
		}

		$rest = $this->reject( $matches[4] );
		if ( ! $rest->is_successful() ) {
			return $rest;
		}

		$href       = html_entity_decode( $matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$resolution = $this->url( $href, $values );
		if ( ! $resolution->is_successful() || $resolution->value() === $href ) {
			return $resolution->is_successful() ? Token_Resolution::success( $segment ) : $resolution;
		}

		return Token_Resolution::success( $matches[1] . $matches[2] . $this->encode( (string) $resolution->value() ) . $matches[2] . $matches[4] );
	}

	/**
	 * Resolve a link destination.
	 *
	 * A destination without double braces is returned unchanged for the
	 * renderer's HTTP(S) validation. Otherwise it must be exactly one
	 * registered URL token; partial interpolation is never accepted.
	 *
	 * @param string               $value  Link destination.
	 * @param array<string, mixed> $values CampaignBridge-owned values.
	 *
	 * @return Token_Resolution
	 */
	private function url( string $value, array $values ): Token_Resolution {
		if ( ! str_contains( $value, '{{' ) && ! str_contains( $value, '}}' ) ) {
			return Token_Resolution::success( $value );
		}

		$parse = $this->parser->parse( $value, $this->registry );
		if ( ! $parse->is_successful() ) {
			return Token_Resolution::failure( $parse->get_errors() );
		}

		if ( 1 !== preg_match( '/^\{\{[^{}]+\}\}$/', $value ) || 1 !== $parse->token_count() ) {
			return Token_Resolution::failure( array( Token_Diagnostic::url_context( 0 ) ) );
		}

		$definition = $parse->get_tokens()[0];
		if ( Token_Definition::VALUE_TYPE_URL !== $definition->get_value_type() ) {
			return Token_Resolution::failure( array( Token_Diagnostic::url_value_type( 0 ) ) );
		}

		if ( $definition->requires_provider_resolution() ) {
			return Token_Resolution::success( $value );
		}

		$local = $values[ $definition->get_id() ] ?? null;

		return is_string( $local )
			? Token_Resolution::success( $local )
			: Token_Resolution::failure( array( Token_Diagnostic::unresolved_token( 0 ) ) );
	}

	/**
	 * Check that a context value is bounded, printable plain text.
	 *
	 * @param mixed $value Candidate value.
	 *
	 * @return bool
	 */
	private function is_plain_value( mixed $value ): bool {
		return is_string( $value )
			&& mb_check_encoding( $value, 'UTF-8' )
			&& '' !== trim( $value )
			&& self::MAX_VALUE_LENGTH >= mb_strlen( $value )
			&& ! str_contains( $value, '{{' )
			&& ! str_contains( $value, '}}' )
			&& 1 !== preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value );
	}

	/**
	 * Encode a resolved rich-text value exactly as the renderer escapes text.
	 *
	 * @param string $value Plain text.
	 *
	 * @return string
	 */
	private function encode( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}
}
