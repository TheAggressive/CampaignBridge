<?php
/**
 * Invalid post snapshot.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carries a safe reason for a malformed or unsupported post snapshot. */
final class Invalid_Post_Snapshot extends \InvalidArgumentException {}
