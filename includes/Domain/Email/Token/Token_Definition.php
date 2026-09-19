<?php
/**
 * Immutable value object describing a single provider-neutral email token.
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
 * Describes a single email token by its canonical ID and metadata.
 *
 * Instances are immutable. All data is validated at construction time.
 */
final class Token_Definition {

	const CATEGORY_SUBSCRIBER   = 'subscriber';
	const CATEGORY_CAMPAIGN     = 'campaign';
	const CATEGORY_ORGANIZATION = 'organization';
	const CATEGORY_SYSTEM       = 'system';

	const VALUE_TYPE_STRING = 'string';
	const VALUE_TYPE_DATE   = 'date';
	const VALUE_TYPE_URL    = 'url';

	const PREVIEW_SAMPLE  = 'sample';
	const PREVIEW_LITERAL = 'literal';
	const PREVIEW_OMIT    = 'omit';

	/**
	 * Canonical token ID including the `cb:` namespace prefix.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Human-readable label for UI display.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Token category: subscriber, campaign, organization, or system.
	 *
	 * The category is the resolution context that owns the value.
	 *
	 * @var string
	 */
	private string $category;

	/**
	 * Value type: string, date, or url.
	 *
	 * @var string
	 */
	private string $value_type;

	/**
	 * Whether the token value is portable across providers.
	 *
	 * @var bool
	 */
	private bool $portable;

	/**
	 * Preview behavior: sample, literal, or omit.
	 *
	 * @var string
	 */
	private string $preview_behavior;

	/**
	 * Whether the value can only be produced in a provider delivery context.
	 *
	 * True for provider-owned subscriber data and for per-recipient links that
	 * remain canonical tokens until a provider emits its own representation.
	 * False for values CampaignBridge resolves itself.
	 *
	 * @var bool
	 */
	private bool $requires_provider_resolution;

	/**
	 * Whether approval-blocking compliance validation requires this value.
	 *
	 * This marks legally required message content (such as the unsubscribe
	 * link or the physical postal address), not privacy sensitivity; privacy
	 * is expressed by the preview behavior.
	 *
	 * @var bool
	 */
	private bool $compliance_relevant;

	/**
	 * Construct a token definition.
	 *
	 * @param string $id                         Canonical ID.
	 * @param string $label                      Human-readable label.
	 * @param string $category                   One of CATEGORY_* constants.
	 * @param string $value_type                 One of VALUE_TYPE_* constants.
	 * @param bool   $portable                   Whether portable across providers.
	 * @param string $preview_behavior           One of PREVIEW_* constants.
	 * @param bool   $requires_provider_resolution Whether provider data is needed.
	 * @param bool   $compliance_relevant        Whether compliance-relevant.
	 */
	private function __construct(
		string $id,
		string $label,
		string $category,
		string $value_type,
		bool $portable,
		string $preview_behavior,
		bool $requires_provider_resolution,
		bool $compliance_relevant
	) {
		$this->id                           = $id;
		$this->label                        = $label;
		$this->category                     = $category;
		$this->value_type                   = $value_type;
		$this->portable                     = $portable;
		$this->preview_behavior             = $preview_behavior;
		$this->requires_provider_resolution = $requires_provider_resolution;
		$this->compliance_relevant          = $compliance_relevant;
	}

	/**
	 * Create a new token definition with validated arguments.
	 *
	 * @param string $id                         Canonical ID.
	 * @param string $label                      Human-readable label.
	 * @param string $category                   Token category.
	 * @param string $value_type                 Value type.
	 * @param bool   $portable                   Whether portable.
	 * @param string $preview_behavior           Preview behavior.
	 * @param bool   $requires_provider_resolution Whether provider data is needed.
	 * @param bool   $compliance_relevant        Whether compliance-relevant.
	 *
	 * @return static
	 *
	 * @throws \InvalidArgumentException When any argument is invalid.
	 */
	public static function create(
		string $id,
		string $label,
		string $category,
		string $value_type,
		bool $portable,
		string $preview_behavior,
		bool $requires_provider_resolution,
		bool $compliance_relevant
	): self {
		self::validate_id( $id );
		self::validate_label( $label );

		if ( ! in_array( $category, array( self::CATEGORY_SUBSCRIBER, self::CATEGORY_CAMPAIGN, self::CATEGORY_ORGANIZATION, self::CATEGORY_SYSTEM ), true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid token category: %s', $category ) );
		}

		if ( ! in_array( $value_type, array( self::VALUE_TYPE_STRING, self::VALUE_TYPE_DATE, self::VALUE_TYPE_URL ), true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid token value type: %s', $value_type ) );
		}

		if ( ! in_array( $preview_behavior, array( self::PREVIEW_SAMPLE, self::PREVIEW_LITERAL, self::PREVIEW_OMIT ), true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid preview behavior: %s', $preview_behavior ) );
		}

		return new self(
			$id,
			$label,
			$category,
			$value_type,
			$portable,
			$preview_behavior,
			$requires_provider_resolution,
			$compliance_relevant
		);
	}

	/**
	 * Validate the canonical token ID format.
	 *
	 * Must match: cb:{category}.{name} where name is [a-z][a-z0-9_]*
	 *
	 * @param string $id Token ID to validate.
	 *
	 * @throws \InvalidArgumentException When the ID is malformed.
	 */
	private static function validate_id( string $id ): void {
		if ( ! preg_match( '/^cb:[a-z]+(?:\.[a-z][a-z0-9_]*)+$/', $id ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid token ID format: %s', $id ) );
		}
	}

	/**
	 * Validate the human-readable label.
	 *
	 * @param string $label Label to validate.
	 *
	 * @throws \InvalidArgumentException When the label is empty or too long.
	 */
	private static function validate_label( string $label ): void {
		if ( '' === $label || mb_strlen( $label ) > 128 ) {
			throw new \InvalidArgumentException( 'Token label must be 1-128 characters.' );
		}
	}

	/**
	 * Get the canonical token ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get the token category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return $this->category;
	}

	/**
	 * Get the value type.
	 *
	 * @return string
	 */
	public function get_value_type(): string {
		return $this->value_type;
	}

	/**
	 * Check whether the token value is portable across providers.
	 *
	 * @return bool
	 */
	public function is_portable(): bool {
		return $this->portable;
	}

	/**
	 * Get the preview behavior.
	 *
	 * @return string
	 */
	public function get_preview_behavior(): string {
		return $this->preview_behavior;
	}

	/**
	 * Check whether resolving this token requires provider-side data.
	 *
	 * @return bool
	 */
	public function requires_provider_resolution(): bool {
		return $this->requires_provider_resolution;
	}

	/**
	 * Check whether this token is relevant to email compliance.
	 *
	 * @return bool
	 */
	public function is_compliance_relevant(): bool {
		return $this->compliance_relevant;
	}

	/**
	 * Get the token name portion (without the `cb:` prefix).
	 *
	 * @return string
	 */
	public function get_name(): string {
		return substr( $this->id, 3 );
	}
}
