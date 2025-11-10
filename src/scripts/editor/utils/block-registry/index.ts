/**
 * CampaignBridge Block Registration System - Main Entry Point
 *
 * Zero-configuration automatic block discovery and registration system.
 * Eliminates manual registration by dynamically discovering block modules.
 */

// Declare WordPress global for TypeScript.

declare global {
  // eslint-disable-next-line no-unused-vars -- Global type declaration.
  const wp: {
    blocks?: {
      // eslint-disable-next-line no-unused-vars -- Parameter type declaration.
      getBlockType?: (name: string) => any;
    };
  };
}

import { blockDiscovery } from './discovery';
import type { RegistrationStats } from './types';

/**
 * Registers all CampaignBridge blocks dynamically
 *
 * This is the main entry point for the block registration system. It automatically
 * discovers all block modules in the blocks directory using webpack's require.context,
 * validates each module, and registers any blocks that haven't been registered yet.
 * The process is resilient to individual block failures and provides detailed results.
 *
 * @returns Registration statistics
 *
 * @example
 * ```typescript
 * import { registerCampaignBridgeBlocks } from './utils/blocks';
 *
 * // Register all blocks
 * const results = registerCampaignBridgeBlocks();
 *
 * // Results object structure:
 * // {
 * //   discovered: 5,
 * //   registered: 3,
 * //   skipped: 1,
 * //   failed: 1
 * // }
 * ```
 */
/**
 * Gets all CampaignBridge block modules (like __experimentalGetCoreBlocks)
 *
 * @returns Array of block modules with init functions
 */
const __getCampaignBridgeBlocks = () => {
  const discoveredBlocks = blockDiscovery.discoverAllBlocks();
  return discoveredBlocks.map(({ module }) => module);
};

/**
 * Function to register CampaignBridge blocks provided by the block editor.
 *
 * @param {Array} blocks An optional array of the CampaignBridge blocks being registered.
 *
 * @example
 * ```typescript
 * import { registerCampaignBridgeBlocks } from './utils/block-registry';
 *
 * registerCampaignBridgeBlocks();
 * ```
 */
export const registerCampaignBridgeBlocks = (
  blocks = __getCampaignBridgeBlocks()
): RegistrationStats => {
  const results: RegistrationStats = {
    discovered: blocks.length,
    registered: 0,
    skipped: 0,
    failed: 0,
  };

  blocks.forEach(blockModule => {
    try {
      // Call init function (just like WordPress core)
      if (blockModule.init && typeof blockModule.init === 'function') {
        blockModule.init();

        // Verify the block was registered
        if (typeof wp !== 'undefined' && wp.blocks && wp.blocks.getBlockType) {
          const blockType = wp.blocks.getBlockType(blockModule.name);
          if (blockType) {
            results.registered++;
          } else {
            results.failed++;
          }
        } else {
          // Fallback if wp.blocks is not available yet
          results.registered++;
        }
      } else {
        results.failed++;
      }
    } catch {
      results.failed++;
    }
  });
  return results;
};

// Note: Blocks are registered manually in editor.tsx, similar to registerCoreBlocks()

// Re-export types for external usage
export type { BlockModule, DiscoveredBlock, RegistrationStats } from './types';
