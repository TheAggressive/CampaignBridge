<?php
/**
 * Constant-time email renderer registry.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable O(1) block-name-to-renderer lookup. */
final class Renderer_Registry {
	/**
	 * Registered renderer map.
	 *
	 * @var array<string, Renderer_Interface>
	 */
	private array $renderers = array();

	/**
	 * Build the immutable registry.
	 *
	 * @param array<int, Renderer_Interface> $renderers Registered renderers.
	 * @throws \DomainException When a block has duplicate renderers.
	 */
	public function __construct( array $renderers ) {
		foreach ( $renderers as $renderer ) {
			$name = $renderer->block_name();
			if ( isset( $this->renderers[ $name ] ) ) {
				throw new \DomainException( sprintf( 'Duplicate email renderer: %s', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal boot-time exception.
			}

			$this->renderers[ $name ] = $renderer;
		}
	}

	/**
	 * Get a renderer in constant time.
	 *
	 * @param string $block_name Stable block name.
	 */
	public function get( string $block_name ): ?Renderer_Interface {
		return $this->renderers[ $block_name ] ?? null;
	}

	/**
	 * Get registered block names.
	 *
	 * @return array<int, string>
	 */
	public function block_names(): array {
		return array_keys( $this->renderers );
	}
}
