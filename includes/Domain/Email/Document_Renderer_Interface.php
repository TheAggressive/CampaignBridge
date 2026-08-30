<?php
/**
 * Full email document renderer contract.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Document_Renderer_Interface {
	/**
	 * Render one complete transport document around compiled body HTML.
	 *
	 * @param string         $body    Compiled body HTML.
	 * @param Render_Context $context Immutable document context.
	 */
	public function render( string $body, Render_Context $context ): string;
}
