<?php
/**
 * An operator-safe refusal that a delivery adapter may display.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Operations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carries only application-authored messages, never external exception details. */
final class Operation_Refused extends \RuntimeException {}
