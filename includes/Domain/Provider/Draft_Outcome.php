<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Normalized outcome of one remote draft mutation.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What is known after a draft mutation, never more.
 *
 * - `created`: the draft exists with the uploaded content.
 * - `content_pending`: the draft exists (its ID is known) but the content
 *   upload did not complete; re-uploading is safe because it is idempotent.
 * - `failed`: the provider definitely did not create a draft.
 * - `ambiguous`: the provider may or may not have created a draft. This
 *   requires reconciliation and must never be retried blindly.
 */
final class Draft_Outcome {
	public const CREATED         = 'created';
	public const CONTENT_PENDING = 'content_pending';
	public const FAILED          = 'failed';
	public const AMBIGUOUS       = 'ambiguous';

	private function __construct(
		private readonly string $status,
		private readonly ?string $remote_id,
		private readonly ?Provider_Error $error
	) {}

	public static function created( string $remote_id ): self {
		return new self( self::CREATED, Discovery_Values::remote_id( $remote_id, 'Remote draft ID' ), null );
	}

	public static function content_pending( string $remote_id, Provider_Error $error ): self {
		return new self( self::CONTENT_PENDING, Discovery_Values::remote_id( $remote_id, 'Remote draft ID' ), $error );
	}

	/** Classify a failed create by what the error proves. */
	public static function from_create_error( Provider_Error $error ): self {
		$ambiguous = Provider_Error_Category::may_have_applied( $error->category() );

		return new self( $ambiguous ? self::AMBIGUOUS : self::FAILED, null, $error );
	}

	public function status(): string {
		return $this->status;
	}

	public function remote_id(): ?string {
		return $this->remote_id;
	}

	public function error(): ?Provider_Error {
		return $this->error;
	}
}
