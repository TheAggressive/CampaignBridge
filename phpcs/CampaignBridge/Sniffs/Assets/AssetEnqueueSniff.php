<?php
/**
 * Custom PHPCS sniff to enforce proper asset enqueuing via Asset_Manager.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.4
 */

declare(strict_types=1);

namespace CampaignBridge\Standard\Sniffs\Assets;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Asset Enqueue Sniff class.
 *
 * Detects direct wp_enqueue_style() and wp_enqueue_script() calls that should
 * use Asset_Manager::enqueue_asset() or Asset_Manager::enqueue_asset_style/script() instead.
 */
class AssetEnqueueSniff implements Sniff {

	/**
	 * Functions that should use Asset_Manager instead.
	 *
	 * @var array<string>
	 */
	private const FORBIDDEN_FUNCTIONS = array(
		'wp_enqueue_style',
		'wp_enqueue_script',
	);

	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array<int>
	 */
	public function register(): array {
		return array( T_STRING );
	}

	/**
	 * Processes the tokens that this sniff is interested in.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ): void {
		$tokens = $phpcs_file->getTokens();
		$token  = $tokens[ $stack_ptr ];

		$function_name = $token['content'];

		// Check if this is a forbidden function.
		if ( in_array( $function_name, self::FORBIDDEN_FUNCTIONS, true ) ) {
			// Skip if it's within Asset_Manager class itself.
			if ( $this->is_within_asset_manager( $phpcs_file, $stack_ptr ) ) {
				return;
			}

			// Skip if it's within Asset_Manager methods (internal usage).
			if ( $this->is_within_asset_manager_methods( $phpcs_file, $stack_ptr ) ) {
				return;
			}

			// Skip Asset_Manager calls in Admin.php (they're legitimate).
			if ( $this->is_asset_manager_call_in_admin( $phpcs_file, $stack_ptr ) ) {
				return;
			}

			$this->report_asset_violation( $phpcs_file, $stack_ptr, $function_name );
		}
	}

	/**
	 * Check if we're within the Asset_Manager class itself.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if within Asset_Manager class.
	 */
	private function is_within_asset_manager( File $phpcs_file, int $stack_ptr ): bool {
		$class_name = $this->get_current_class_name( $phpcs_file, $stack_ptr );
		return 'Asset_Manager' === $class_name;
	}

	/**
	 * Check if this is an Asset_Manager call in Admin.php.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if it's an Asset_Manager call in Admin.php.
	 */
	private function is_asset_manager_call_in_admin( File $phpcs_file, int $stack_ptr ): bool {
		$file_name = basename( $phpcs_file->getFilename() );
		if ( 'Admin.php' !== $file_name ) {
			return false;
		}

		// Look for Asset_Manager:: pattern before the function call.
		$tokens       = $phpcs_file->getTokens();
		$current_line = $tokens[ $stack_ptr ]['line'];

		// Check a few tokens back to see if there's Asset_Manager::
		for ( $i = $stack_ptr - 1; $i >= max( 0, $stack_ptr - 10 ); $i-- ) {
			if ( $tokens[ $i ]['line'] !== $current_line ) {
				break; // Don't go to previous lines
			}

			if ( $tokens[ $i ]['content'] === 'Asset_Manager' &&
				isset( $tokens[ $i + 1 ] ) &&
				$tokens[ $i + 1 ]['content'] === '::' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if we're within Asset_Manager internal methods.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if within Asset_Manager internal methods.
	 */
	private function is_within_asset_manager_methods( File $phpcs_file, int $stack_ptr ): bool {
		$class_name = $this->get_current_class_name( $phpcs_file, $stack_ptr );
		if ( 'Asset_Manager' !== $class_name ) {
			return false;
		}

		$method_name      = $this->get_current_method_name( $phpcs_file, $stack_ptr );
		$internal_methods = array(
			'enqueue_asset_style_internal',
			'enqueue_asset_script_internal',
			'prepare_asset_enqueue',
		);

		return in_array( $method_name, $internal_methods, true );
	}

	/**
	 * Gets the current class name at the given position.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return string|false The class name or false if not found.
	 */
	private function get_current_class_name( File $phpcs_file, int $stack_ptr ): string|false {
		$tokens = $phpcs_file->getTokens();

		// Look backwards for class declaration.
		for ( $i = $stack_ptr; $i >= 0; $i-- ) {
			$token = $tokens[ $i ];

			if ( T_CLASS === $token['code'] ) {
				// Found a class, get the class name.
				$class_name_token = $phpcs_file->findNext( T_STRING, $i + 1 );
				if ( false !== $class_name_token ) {
					return $tokens[ $class_name_token ]['content'];
				}
			}

			// Stop at function boundaries or if we go too far back.
			if ( T_FUNCTION === $token['code'] || $i < $stack_ptr - 1000 ) {
				break;
			}
		}

		return false;
	}

	/**
	 * Gets the current method name at the given position.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return string|false The method name or false if not found.
	 */
	private function get_current_method_name( File $phpcs_file, int $stack_ptr ): string|false {
		$tokens = $phpcs_file->getTokens();

		// Look backwards for function declaration.
		for ( $i = $stack_ptr; $i >= 0; $i-- ) {
			$token = $tokens[ $i ];

			if ( T_FUNCTION === $token['code'] ) {
				// Found a function, get the function name.
				$function_name_token = $phpcs_file->findNext( T_STRING, $i + 1 );
				if ( false !== $function_name_token ) {
					return $tokens[ $function_name_token ]['content'];
				}
			}

			// Stop at class boundaries or if we go too far back.
			if ( T_CLASS === $token['code'] || $i < $stack_ptr - 500 ) {
				break;
			}
		}

		return false;
	}

	/**
	 * Reports a violation for direct asset enqueuing.
	 *
	 * @param File   $phpcs_file   The file being scanned.
	 * @param int    $stack_ptr    The position of the current token in the stack.
	 * @param string $function_name The function name that was used.
	 *
	 * @return void
	 */
	private function report_asset_violation( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		$replacement = 'Asset_Manager::enqueue_asset() or Asset_Manager::enqueue_asset_' .
			( 'wp_enqueue_style' === $function_name ? 'style' : 'script' ) . '()';

		$warning = sprintf(
			'Direct asset enqueuing function %s() detected. Use %s for proper dependency management and versioning.',
			$function_name,
			$replacement
		);

		$phpcs_file->addWarning(
			$warning,
			$stack_ptr,
			'CampaignBridge.Standard.Sniffs.Assets.AssetEnqueue.DirectAssetEnqueue'
		);
	}
}
