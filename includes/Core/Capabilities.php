<?php
/**
 * CampaignBridge capability definitions.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for CampaignBridge capability names and role mapping.
 *
 * Every enforcement site — REST permission callbacks, admin menu visibility,
 * and provider authorization — must reference these constants rather than
 * hard-coding capability strings.
 */
final class Capabilities {
	/** Base capability: manage the CampaignBridge plugin. */
	public const MANAGE = 'campaignbridge_manage';

	/** Manage provider connections and credentials. */
	public const MANAGE_CONNECTIONS = 'campaignbridge_manage_connections';

	/** Create, edit, and delete email templates. */
	public const EDIT_TEMPLATES = 'campaignbridge_edit_templates';

	/** Create and edit campaigns. */
	public const CREATE_CAMPAIGNS = 'campaignbridge_create_campaigns';

	/** Approve, schedule, send, and cancel campaigns. */
	public const SEND_CAMPAIGNS = 'campaignbridge_send_campaigns';

	/** View delivery reports and audit logs. */
	public const VIEW_REPORTS = 'campaignbridge_view_reports';

	/**
	 * All CampaignBridge capabilities in registration order.
	 *
	 * @var array<int, string>
	 */
	public const ALL = array(
		self::MANAGE,
		self::MANAGE_CONNECTIONS,
		self::EDIT_TEMPLATES,
		self::CREATE_CAMPAIGNS,
		self::SEND_CAMPAIGNS,
		self::VIEW_REPORTS,
	);

	/**
	 * Grant every CampaignBridge capability to the administrator role.
	 *
	 * Idempotent: safe to call on every activation.
	 *
	 * @return void
	 */
	public static function register(): void {
		$role = \get_role( 'administrator' );
		if ( null === $role ) {
			return;
		}

		foreach ( self::ALL as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove every CampaignBridge capability from the administrator role.
	 *
	 * @return void
	 */
	public static function unregister(): void {
		$role = \get_role( 'administrator' );
		if ( null === $role ) {
			return;
		}

		foreach ( self::ALL as $cap ) {
			if ( $role->has_cap( $cap ) ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
