<?php // phpcs:ignore WordPress.Files.FileName
/**
 * PHPStan Rule: No Direct WordPress Storage Functions
 *
 * Prevents direct usage of WordPress storage functions that should go through
 * the CampaignBridge Storage wrapper for proper prefixing.
 *
 * @package CampaignBridge\Tools\PHPStan\Rules
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Tools\PHPStan\Rules;

use CampaignBridge\Core\Storage_Prefixes;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Rule that detects direct usage of WordPress storage functions.
 */
class NoDirectWordPressStorageFunctionsRule implements Rule {

	/**
	 * Functions that should not be called directly.
	 *
	 * @var array<string, string>
	 */
	private const FORBIDDEN_FUNCTIONS = array(
		'get_option'       => 'Use CampaignBridge\\Core\\Storage::get_option() instead',
		'update_option'    => 'Use CampaignBridge\\Core\\Storage::update_option() instead',
		'add_option'       => 'Use CampaignBridge\\Core\\Storage::add_option() instead',
		'delete_option'    => 'Use CampaignBridge\\Core\\Storage::delete_option() instead',
		'get_transient'    => 'Use CampaignBridge\\Core\\Storage::get_transient() instead',
		'set_transient'    => 'Use CampaignBridge\\Core\\Storage::set_transient() instead',
		'delete_transient' => 'Use CampaignBridge\\Core\\Storage::delete_transient() instead',
		'get_post_meta'    => 'Use CampaignBridge\\Core\\Storage::get_post_meta() instead',
		'update_post_meta' => 'Use CampaignBridge\\Core\\Storage::update_post_meta() instead',
		'add_post_meta'    => 'Use CampaignBridge\\Core\\Storage::add_post_meta() instead',
		'delete_post_meta' => 'Use CampaignBridge\\Core\\Storage::delete_post_meta() instead',
		'get_user_meta'    => 'Use CampaignBridge\\Core\\Storage::get_user_meta() instead',
		'update_user_meta' => 'Use CampaignBridge\\Core\\Storage::update_user_meta() instead',
		'add_user_meta'    => 'Use CampaignBridge\\Core\\Storage::add_user_meta() instead',
		'delete_user_meta' => 'Use CampaignBridge\\Core\\Storage::delete_user_meta() instead',
		'wp_cache_get'     => 'Use CampaignBridge\\Core\\Storage::wp_cache_get() instead',
		'wp_cache_set'     => 'Use CampaignBridge\\Core\\Storage::wp_cache_set() instead',
		'wp_cache_delete'  => 'Use CampaignBridge\\Core\\Storage::wp_cache_delete() instead',
	);

	/**
	 * Functions that are allowed in specific contexts.
	 *
	 * @var array<string, array<string>>
	 */
	private const ALLOWED_IN_FILES = array(
		'includes/Core/Storage.php'          => array( '*' ),
		'includes/Core/Storage_Prefixes.php' => array( '*' ),
		'uninstall.php'                      => array( '*' ),
	);

	/**
	 * Functions allowed in legacy code during migration and testing.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_IN_DIRECTORIES = array(
		'includes/Admin_Legacy/',
		'vendor/',
		'node_modules/',
		'tests/',
		'bin/',
	);

	/**
	 * Returns the rule node type.
	 *
	 * @return string
	 */
	public function getNodeType(): string {
		return FuncCall::class;
	}

	/**
	 * Processes the function call node.
	 *
	 * @param Node  $node  The function call node.
	 * @param Scope $scope The current scope.
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $node->name instanceof Node\Identifier ) {
			return array();
		}

		$function_name = $node->name->name;

		if ( ! isset( self::FORBIDDEN_FUNCTIONS[ $function_name ] ) ) {
			return array();
		}

		// Check if this function is allowed in the current file.
		$file_path     = $scope->getFile();
		$relative_path = $this->get_relative_path( $file_path );

		// Allow in specific files.
		if ( isset( self::ALLOWED_IN_FILES[ $relative_path ] ) ) {
			$allowed = self::ALLOWED_IN_FILES[ $relative_path ];
			if ( in_array( '*', $allowed, true ) || in_array( $function_name, $allowed, true ) ) {
				return array();
			}
		}

		// Allow in specific directories during migration and testing.
		foreach ( self::ALLOWED_IN_DIRECTORIES as $allowed_path ) {
			if ( str_starts_with( $relative_path, $allowed_path ) ) {
				return array();
			}
		}

		// Check if this is a method call on a class (not a direct function call).
		if ( $this->is_method_call( $node ) ) {
			return array();
		}

		$suggestion = self::FORBIDDEN_FUNCTIONS[ $function_name ];

		return array(
			RuleErrorBuilder::message(
				"Direct usage of WordPress storage functions is not allowed. {$suggestion}"
			)
			->identifier( 'campaignbridge.storage.directUsage' )
			->build(),
		);
	}

	/**
	 * Gets the relative path from the project root.
	 *
	 * @param string $absolute_path The absolute file path.
	 * @return string The relative path.
	 */
	private function get_relative_path( string $absolute_path ): string {
		$project_root = dirname( __DIR__, 2 ); // Go up to plugin root.
		return str_replace( $project_root . '/', '', $absolute_path );
	}

	/**
	 * Checks if the function call is actually a method call on an object.
	 *
	 * @param FuncCall $node The function call node.
	 * @return bool True if it's a method call.
	 */
	private function is_method_call( FuncCall $node ): bool {
		// This is a simplified check. In a real implementation, you'd need
		// more sophisticated AST analysis to detect method calls vs function calls.
		// For now, we'll rely on the file-based allowlist for Storage class methods.

		return false;
	}
}
