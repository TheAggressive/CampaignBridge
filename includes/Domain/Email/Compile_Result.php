<?php
/**
 * Immutable compiler result.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Successful artifact or fail-closed diagnostic result. */
final class Compile_Result {
	/**
	 * Create an immutable compiler result.
	 *
	 * @param string                           $html             Compiled HTML, empty on error.
	 * @param string                           $text             Compiled plain text, empty on error.
	 * @param array<int, Compile_Diagnostic>   $diagnostics      Compiler diagnostics.
	 * @param array<int, array<string, mixed>> $assets         Referenced external assets.
	 * @param string                           $fingerprint      Deterministic artifact fingerprint.
	 * @param string                           $compiler_version Compiler contract version.
	 * @param string                           $profile_version  Target profile version.
	 */
	public function __construct(
		private readonly string $html,
		private readonly string $text,
		private readonly array $diagnostics,
		private readonly array $assets,
		private readonly string $fingerprint,
		private readonly string $compiler_version,
		private readonly string $profile_version
	) {}

	/** Determine whether the result contains a sendable artifact. */
	public function is_success(): bool {
		foreach ( $this->diagnostics as $diagnostic ) {
			if ( $diagnostic->is_error() ) {
				return false;
			}
		}

		return true;
	}

	/** Get compiled HTML. */
	public function html(): string {
		return $this->html;
	}

	/** Get compiled plain text. */
	public function text(): string {
		return $this->text;
	}

	/**
	 * Get structured diagnostics.
	 *
	 * @return array<int, Compile_Diagnostic>
	 */
	public function diagnostics(): array {
		return $this->diagnostics;
	}

	/**
	 * Get referenced external assets.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function assets(): array {
		return $this->assets;
	}

	/** Get the artifact fingerprint. */
	public function fingerprint(): string {
		return $this->fingerprint;
	}

	/** Get the compiler version. */
	public function compiler_version(): string {
		return $this->compiler_version;
	}

	/** Get the target profile version. */
	public function profile_version(): string {
		return $this->profile_version;
	}
}
