<?php
/**
 * Production lock composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Lock;

use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Lock_Repository;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Lock\Lock_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds the lock manager every delivery, reconciliation, and discovery workflow shares. */
final class Lock_Factory {
	/** A lock manager with its own owner identity for this request or worker. */
	public static function manager(): Lock_Manager {
		return new Lock_Manager( new Lock_Repository(), new Audit_Event_Repository(), new System_Clock() );
	}
}
