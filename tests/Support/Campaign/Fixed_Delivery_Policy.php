<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment
/**
 * Settable delivery policy for workflow tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Support\Campaign;

use CampaignBridge\Domain\Campaign\Delivery_Policy;
use CampaignBridge\Domain\Campaign\Delivery_Policy_Source;

/** Serves whatever policy a test sets; unrestricted by default. */
final class Fixed_Delivery_Policy implements Delivery_Policy_Source {
	public Delivery_Policy $policy;

	public function __construct() {
		$this->policy = Delivery_Policy::unrestricted();
	}

	public function current(): Delivery_Policy {
		return $this->policy;
	}
}
