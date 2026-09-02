<?php
/**
 * Block bootstrap integration tests.
 *
 * @package CampaignBridge\Tests\Integration
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Blocks\Blocks;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_Block_Type_Registry;

/**
 * Verify block registration across WordPress bootstrap timing.
 */
class Block_Bootstrap_Test extends Test_Case {
	/**
	 * Initialization during WordPress's init hook must register immediately.
	 */
	public function test_late_init_registers_blocks_immediately(): void {
		if ( ! Blocks::blocks_available() ) {
			$this->markTestSkipped( 'Blocks are not built. Run pnpm build:blocks to generate blocks.' );
		}

		$expected_blocks = array(
			'campaignbridge/button',
			'campaignbridge/container',
			'campaignbridge/divider',
			'campaignbridge/heading',
			'campaignbridge/image',
			'campaignbridge/post-card',
			'campaignbridge/post-cta',
			'campaignbridge/post-excerpt',
			'campaignbridge/post-image',
			'campaignbridge/post-title',
			'campaignbridge/section',
			'campaignbridge/spacer',
			'campaignbridge/text',
		);
		$registry        = WP_Block_Type_Registry::get_instance();

		foreach ( $expected_blocks as $block_name ) {
			if ( $registry->is_registered( $block_name ) ) {
				$registry->unregister( $block_name );
			}
		}

		Blocks::init();

		foreach ( $expected_blocks as $block_name ) {
			$this->assertTrue(
				$registry->is_registered( $block_name ),
				"Block '{$block_name}' should register when initialization occurs during init"
			);
		}
	}
}
