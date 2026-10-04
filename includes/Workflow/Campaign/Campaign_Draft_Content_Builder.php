<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed signature is the contract.
/**
 * Provider draft content from an approved snapshot.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Email\Token\Token_Parser;
use CampaignBridge\Domain\Email\Token\Token_Registry;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Draft_Content;
use CampaignBridge\Domain\Provider\Provider_Token_Mapper;
use CampaignBridge\Domain\Provider\Token_Mapping;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single translation from an approved snapshot to provider draft content.
 *
 * Shared by draft handoff and delivery so the content created, re-asserted,
 * and delivered is always the approved artifact and frozen envelope,
 * translated through the provider's token mapping for the target audience.
 * The stored artifact is never altered.
 */
final class Campaign_Draft_Content_Builder {
	public function __construct(
		private readonly Provider_Token_Mapper $tokens,
		private readonly Provider_Discovery_Service $discovery
	) {}

	/**
	 * The remote draft title, which carries the correlation ID so an
	 * unconfirmed create can be found again.
	 */
	public static function title( string $correlation_id ): string {
		return 'CampaignBridge ' . $correlation_id;
	}

	/**
	 * Build provider content from a verified snapshot only.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function build( Campaign_Snapshot $snapshot, string $audience, array $settings, string $correlation_id ): Draft_Content|Campaign_Workflow_Error {
		$envelope = $snapshot->envelope();
		$problems = null === $envelope ? array( 'envelope_missing' ) : $envelope->problems();
		if ( null === $envelope || array() !== $problems ) {
			return new Campaign_Workflow_Error(
				Campaign_Workflow_Error::VALIDATION_FAILED,
				'The approved envelope is incomplete (' . implode( ', ', $problems ) . '). Update the template, snapshot, and approve again.'
			);
		}

		$registry = Token_Registry::default();
		$mapping  = $this->tokens->map( $registry, $audience, $this->discovery->cached( Discovery_Kind::MERGE_FIELDS, $settings, $audience )->result() );
		$fields   = array(
			'html'         => $snapshot->artifact()->html(),
			'text'         => $snapshot->artifact()->text(),
			'subject'      => $envelope->subject(),
			'preview_text' => $envelope->preview_text(),
		);
		foreach ( $fields as $name => $value ) {
			$translation = $mapping->translate( $value, $registry, new Token_Parser() );
			if ( ! $translation->is_complete() ) {
				return new Campaign_Workflow_Error( Campaign_Workflow_Error::VALIDATION_FAILED, $this->translation_problem( $name, $translation->has_literal_conflict(), $translation->unmapped(), $mapping ) );
			}
			$fields[ $name ] = $translation->content();
		}

		try {
			return Draft_Content::create(
				$audience,
				$fields['subject'],
				$fields['preview_text'],
				$envelope->from_name(),
				$envelope->from_email(),
				$fields['html'],
				$fields['text'],
				$snapshot->artifact()->fingerprint(),
				self::title( $correlation_id )
			);
		} catch ( \InvalidArgumentException ) {
			return new Campaign_Workflow_Error( Campaign_Workflow_Error::INVALID_INPUT, 'The approved artifact cannot be sent to this provider.' );
		}
	}

	/** @param array<int, string> $unmapped Canonical tokens without a provider representation. */
	private function translation_problem( string $field, bool $literal, array $unmapped, Token_Mapping $mapping ): string {
		if ( $literal ) {
			return sprintf( 'The approved %s contains literal provider merge syntax, which the provider would evaluate. Remove it, snapshot, and approve again.', $field );
		}
		if ( array() === $unmapped ) {
			return sprintf( 'The approved %s contains tokens that could not be read.', $field );
		}
		$reasons = array_unique( array_intersect_key( $mapping->unsupported(), array_flip( $unmapped ) ) );

		return in_array( Token_Mapping::REASON_MERGE_FIELDS_INCOMPLETE, $reasons, true )
			? sprintf( 'The approved %s uses %s, but this audience\'s merge fields have not been fully discovered. Refresh merge fields and try again.', $field, implode( ', ', $unmapped ) )
			: sprintf( 'The approved %s uses %s, which this audience cannot substitute.', $field, implode( ', ', $unmapped ) );
	}
}
