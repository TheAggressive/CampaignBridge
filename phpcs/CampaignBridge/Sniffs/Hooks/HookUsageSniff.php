<?php
/**
 * Custom PHPCS sniff to enforce proper WordPress hook usage patterns.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Standard\Sniffs\Hooks;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Hook Usage Sniff class.
 *
 * Validates proper WordPress hook registration and usage patterns.
 */
class HookUsageSniff implements Sniff {

	/**
	 * WordPress hook functions that should be validated.
	 *
	 * @var array<string>
	 */
	private const HOOK_FUNCTIONS = array(
		'add_action',
		'add_filter',
		'do_action',
		'apply_filters',
		'remove_action',
		'remove_filter',
		'remove_all_actions',
		'remove_all_filters',
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

		// Check if this is a WordPress hook function.
		if ( in_array( $function_name, self::HOOK_FUNCTIONS, true ) ) {
			$this->validateHookUsage( $phpcs_file, $stack_ptr, $function_name );
		}
	}

	/**
	 * Validates proper hook function usage.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The hook function name.
	 *
	 * @return void
	 */
	private function validateHookUsage( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		$tokens = $phpcs_file->getTokens();

		// Check if hook function has proper parameters.
		if ( ! $this->hasValidParameters( $phpcs_file, $stack_ptr, $function_name ) ) {
			$error = sprintf(
				'%s() should have proper parameters for hook name, callback, priority, and accepted args',
				$function_name
			);
			$phpcs_file->addWarning( $error, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Hooks.HookUsage.InvalidHookParameters' );
		}

		// Check for hardcoded hook priorities (should use constants or meaningful values).
		$this->validateHookPriority( $phpcs_file, $stack_ptr, $function_name );

		// Check for proper callback validation.
		$this->validateCallback( $phpcs_file, $stack_ptr, $function_name );
	}

	/**
	 * Checks if the hook function has valid parameters.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The hook function name.
	 *
	 * @return bool True if parameters are valid.
	 */
	private function hasValidParameters( File $phpcs_file, int $stack_ptr, string $function_name ): bool {
		// For basic validation, just ensure the function call exists
		// More sophisticated validation could check parameter types and counts.
		$next_token = $phpcs_file->findNext( T_WHITESPACE, $stack_ptr + 1, null, true );
		return $next_token && T_OPEN_PARENTHESIS === $phpcs_file->getTokens()[ $next_token ]['code'];
	}

	/**
	 * Validates hook priority usage.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The hook function name.
	 *
	 * @return void
	 */
	private function validateHookPriority( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		// This would require more sophisticated token analysis to find priority parameters
		// For now, this is a placeholder for future enhancement.
	}

	/**
	 * Validates callback parameters.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The hook function name.
	 *
	 * @return void
	 */
	private function validateCallback( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		// Check for common callback validation issues
		// This would require analyzing the callback parameter.
	}
}
