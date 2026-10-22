<?php
/**
 * Composition for explicit operator requests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Operations;

use CampaignBridge\Cron\Job_Dispatcher;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Connection_Result;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Services\Campaign\Campaign_Reconciler_Factory;
use CampaignBridge\Services\Campaign\Campaign_Workflow_Factory;
use CampaignBridge\Services\Job\Job_Factory;
use CampaignBridge\Services\Provider\Provider_Discovery_Factory;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Campaign_Reconcile_Result;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Job\Job_Recovery;
use CampaignBridge\Workflow\Job\Job_Time;
use CampaignBridge\Workflow\Operations\Operation_Refused;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Supplies canonical workflows and an explicit allowlist of diagnostic values. */
final class Operations_Factory {
	/**
	 * Authorized job operations with production storage and handler declarations.
	 *
	 * @throws Operation_Refused When job storage needs an upgrade.
	 */
	public static function jobs(): Job_Recovery {
		self::require_storage();

		return new Job_Recovery( new Job_Repository(), new Audit_Event_Repository(), new Database_Transaction(), new Random_Id_Generator(), new System_Clock(), Job_Factory::handlers() );
	}

	/**
	 * Read aggregate stored health; no provider requests, credentials, or payloads.
	 *
	 * @return array<string, mixed>
	 */
	public static function diagnostics(): array {
		global $wp_version;
		$schema     = Schema_Manager::is_current();
		$jobs       = new Job_Repository();
		$now        = ( new System_Clock() )->now();
		$connection = ( new Provider_Connection_Repository() )->get( 'mailchimp' );
		$next       = wp_next_scheduled( Job_Dispatcher::HOOK );

		return array(
			'plugin_version'    => \CampaignBridge_Plugin::VERSION,
			'wordpress_version' => $wp_version,
			'php_version'       => PHP_VERSION,
			'checked_at'        => $now,
			'schema_current'    => $schema,
			'cron_disabled'     => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'next_job_tick'     => false === $next ? null : gmdate( 'Y-m-d\TH:i:s\Z', $next ),
			'job_counts'        => $schema ? $jobs->counts_by_state() : null,
			'stalled_jobs'      => $schema ? $jobs->stalled( Job_Time::after( $now, -300 ), $now ) : null,
			'campaign_states'   => $schema ? ( new Campaign_Repository() )->state_counts() : null,
			'mailchimp'         => array(
				'connected'        => null !== $connection,
				'verified'         => $connection?->is_verified() ?? false,
				'last_verified_at' => $connection?->last_verified_at(),
			),
		);
	}

	/**
	 * Verify a stored connection with the provider's read-only check and audit it.
	 *
	 * @param int    $user_id Authorized connection manager.
	 * @param string $provider Provider slug.
	 * @throws Operation_Refused On unsupported provider or audit failure.
	 */
	public static function check_connection( int $user_id, string $provider ): Connection_Result {
		self::require_storage();
		if ( 'mailchimp' !== $provider ) {
			throw new Operation_Refused( 'This provider does not support a connection check.' );
		}
		$settings = Provider_Discovery_Factory::settings( $provider );
		try {
			$result = ( new Mailchimp_Provider() )->verify_connection( $settings );
		} finally {
			unset( $settings );
		}
		$event = Audit_Event::from_array(
			array(
				'schema_version' => Audit_Event::SCHEMA_VERSION,
				'id'             => ( new Random_Id_Generator() )->generate( 'audit' ),
				'actor_user_id'  => $user_id,
				'action'         => 'connection_check',
				'target_type'    => 'provider',
				'target_id'      => $provider,
				'result'         => $result->is_success() ? 'success' : 'failure',
				'context'        => array(
					'verified'       => $result->is_success(),
					'error_category' => $result->error()?->category(),
				),
				'created_at'     => ( new System_Clock() )->now(),
			)
		);
		if ( ! ( new Audit_Event_Repository() )->add( $event ) ) {
			throw new Operation_Refused( 'The connection check could not be recorded in the audit history.' );
		}

		return $result;
	}

	/**
	 * Resolve the authorized campaign before loading credentials for reconciliation.
	 *
	 * @param Campaign_Actor $actor Resolved user authority.
	 * @param string         $id Campaign ID.
	 * @throws Operation_Refused On refusal.
	 */
	public static function reconcile( Campaign_Actor $actor, string $id ): Campaign_Reconcile_Result {
		self::require_storage();
		$loaded   = Campaign_Workflow_Factory::create()->get( $actor, $id );
		$campaign = $loaded->campaign();
		if ( ! $loaded->is_success() || null === $campaign ) {
			throw new Operation_Refused( 'Campaign was not found or is not accessible to this user.' );
		}
		if ( ! $actor->can_deliver( $campaign ) ) {
			throw new Operation_Refused( 'Campaign operation is not allowed.' );
		}
		$provider   = (string) $campaign->provider();
		$reconciler = Campaign_Reconciler_Factory::create( $provider );
		if ( null === $reconciler ) {
			throw new Operation_Refused( 'The campaign provider does not support reconciliation.' );
		}
		$settings = Provider_Discovery_Factory::settings( $provider );
		try {
			return $reconciler->reconcile( $actor, $id, $settings );
		} finally {
			unset( $settings );
		}
	}

	/**
	 * Refuse actions whose audit history cannot be stored.
	 *
	 * @throws Operation_Refused When storage needs an upgrade.
	 */
	private static function require_storage(): void {
		if ( ! Schema_Manager::is_current() ) {
			throw new Operation_Refused( 'CampaignBridge storage needs an upgrade. Open the WordPress administration area first.' );
		}
	}
}
