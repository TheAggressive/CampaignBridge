<?php
/**
 * Form Asset Methods Trait - Provides asset management API
 *
 * Contains all asset-related methods for scripts, styles,
 * and conditional loading while maintaining perfect static analysis.
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

/**
 * Form Asset Methods Trait
 *
 * @package CampaignBridge\Admin\Core
 */
trait Form_Asset_Methods {
	/**
	 * Enqueue optimized script for this form.
	 *
	 * @param string        $handle    Script handle.
	 * @param string        $src       Script source URL.
	 * @param array<string> $deps      Dependencies.
	 * @param string        $version   Version string.
	 * @param bool          $in_footer Whether to load in footer.
	 * @return void
	 */
	public function enqueue_script( string $handle, string $src, array $deps = array(), string $version = '', bool $in_footer = true ): void {
		$this->asset_optimizer->enqueue_script( $handle, $src, $deps, $version, $in_footer );
	}

	/**
	 * Enqueue optimized style for this form.
	 *
	 * @param string        $handle  Style handle.
	 * @param string        $src     Style source URL.
	 * @param array<string> $deps    Dependencies.
	 * @param string        $version Version string.
	 * @param string        $media   Media type.
	 * @return void
	 */
	public function enqueue_style( string $handle, string $src, array $deps = array(), string $version = '', string $media = 'all' ): void {
		$this->asset_optimizer->enqueue_style( $handle, $src, $deps, $version, $media );
	}

	/**
	 * Add conditional asset loading rule.
	 *
	 * @param string   $asset_handle Asset handle.
	 * @param callable $condition   Condition function.
	 * @param string   $type        Asset type ('script' or 'style').
	 * @return void
	 */
	public function add_asset_condition( string $asset_handle, callable $condition, string $type = 'script' ): void {
		$this->asset_optimizer->add_conditional_rule( $asset_handle, $condition, $type );
	}
}
