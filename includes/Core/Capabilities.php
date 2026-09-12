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
	 * Current capability schema version.
	 *
	 * Increment this constant when the set of capabilities changes so that
	 * existing installations are repaired on the next admin request.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Option name that stores the last-applied capability schema version.
	 */
	private const SCHEMA_OPTION = 'campaignbridge_capability_schema';

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
	 * Ensure capabilities are registered, repairing existing installations
	 * when the schema version has changed.
	 *
	 * Performs a single cached option read and only modifies roles when the
	 * stored version does not match the current schema version. Safe to call
	 * on every admin request.
	 *
	 * @return void
	 */
	public static function ensure_registered(): void {
		$stored_version = (int) \get_option( self::SCHEMA_OPTION, 0 );

		if ( self::SCHEMA_VERSION === $stored_version ) {
			return;
		}

		if ( null === \get_role( 'administrator' ) ) {
			return;
		}

		self::register();
		\update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION ); // phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification -- Internal schema repair, not user-facing.
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
