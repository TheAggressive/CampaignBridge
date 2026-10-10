<?php
/**
 * Plugin Name: CampaignBridge E2E Operators
 * Description: Test-only operator accounts and pseudo-translation for browser tests. It lets an administrator create throwaway users with chosen CampaignBridge capabilities. Never install it on a production site.
 *
 * Installed only into disposable test sites (CI) or, temporarily, into a local
 * development site while browser tests run. It is not part of the release
 * package.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Throwaway operator accounts and a pseudo-locale for the campaign screens. */
final class CampaignBridge_E2E_Operators {
	private const LOGIN_PREFIX = 'e2e-operator-';
	private const PSEUDO_PARAM = 'campaignbridge_e2e_pseudo';

	/** Hook the control routes and the pseudo-locale. */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'pseudo_locale' ), 1 );
		add_filter( 'pre_load_script_translations', array( self::class, 'translations' ), 10, 4 );
	}

	/** Register the operator control routes. */
	public static function routes(): void {
		$admin = static fn () => current_user_can( 'manage_options' );

		register_rest_route(
			'campaignbridge-e2e/v1',
			'/operators',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static fn () => rest_ensure_response( array( 'ready' => true ) ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			'campaignbridge-e2e/v1',
			'/operators/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/**
	 * Create a subscriber holding exactly the requested CampaignBridge capabilities.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$requested = $request->get_param( 'capabilities' );
		$requested = is_array( $requested ) ? $requested : array();
		$allowed   = \CampaignBridge\Core\Capabilities::ALL;
		$unknown   = array_diff( $requested, $allowed );
		if ( array() !== $unknown ) {
			return new WP_Error( 'campaignbridge_e2e_unknown_capability', 'Only CampaignBridge capabilities can be granted.', array( 'status' => 400 ) );
		}

		$login    = self::LOGIN_PREFIX . strtolower( wp_generate_password( 10, false ) );
		$password = wp_generate_password( 24, false );
		$user_id  = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => $password,
				'user_email' => $login . '@example.test',
				'role'       => 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_userdata( $user_id );
		foreach ( $requested as $capability ) {
			$user->add_cap( (string) $capability );
		}

		return rest_ensure_response(
			array(
				'id'       => $user_id,
				'login'    => $login,
				'password' => $password,
			)
		);
	}

	/**
	 * Delete one operator this plugin created; any other user is refused.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		$user = get_userdata( (int) $request->get_param( 'id' ) );
		if ( ! $user || ! str_starts_with( $user->user_login, self::LOGIN_PREFIX ) ) {
			return new WP_Error( 'campaignbridge_e2e_not_an_operator', 'Only test operators can be deleted.', array( 'status' => 404 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user->ID, get_current_user_id() );

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/** Mark every CampaignBridge string, so an untranslated one is visible. */
	public static function pseudo_locale(): void {
		if ( ! self::pseudo() ) {
			return;
		}

		$mark = 'function(text){return "⟦"+text+"⟧";}';
		wp_add_inline_script(
			'wp-hooks',
			implode(
				'',
				array_map(
					static fn ( string $filter ): string => "wp.hooks.addFilter('i18n.{$filter}_campaignbridge','campaignbridge-e2e',{$mark});",
					array( 'gettext', 'gettext_with_context', 'ngettext', 'ngettext_with_context' )
				)
			)
		);
	}

	/**
	 * One real translation for the Campaigns screen, through the WordPress script-translation path.
	 *
	 * @param string|false|null $translations Earlier result.
	 * @param string|false      $file         Translation file.
	 * @param string            $handle       Script handle.
	 * @param string            $domain       Text domain.
	 * @return string|false|null
	 */
	public static function translations( $translations, $file, $handle, $domain ) {
		if ( ! self::pseudo() || 'campaignbridge' !== $domain || 'cb-campaignbridge-campaigns' !== $handle ) {
			return $translations;
		}

		return (string) wp_json_encode(
			array(
				'locale_data' => array(
					'messages' => array(
						''             => array(
							'domain' => 'messages',
							'lang'   => 'fr_FR',
						),
						'New campaign' => array( 'Nouvelle campagne' ),
					),
				),
			)
		);
	}

	/** Whether this admin request asked for the pseudo-locale. */
	private static function pseudo(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only test switch.
		return is_admin() && isset( $_GET[ self::PSEUDO_PARAM ] ) && current_user_can( 'read' );
	}
}

CampaignBridge_E2E_Operators::register();
