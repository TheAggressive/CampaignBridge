/**
 * CampaignBridge Block Registration System - Main Entry Point
 *
 * Zero-configuration automatic block discovery and registration system.
 * Eliminates manual registration by dynamically discovering block modules.
 */

import { blockDiscovery } from './discovery';
import { blockRegistration } from './registration';
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
 * console.log(`Registered ${results.registered} blocks, ${results.failed} failed`);
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
export const registerCampaignBridgeBlocks = (): RegistrationStats => {
  const discoveredBlocks = blockDiscovery.discoverAllBlocks();

  const results: RegistrationStats = {
    discovered: discoveredBlocks.length,
    registered: 0,
    skipped: 0,
    failed: 0,
  };

  discoveredBlocks.forEach(({ name: blockName, module: blockModule }) => {
    const result = blockRegistration.registerBlockIfNeeded(
      blockName,
      blockModule
    );

    if (result.success) {
      if ('skipped' in result && result.skipped) {
        results.skipped++;
      } else {
        results.registered++;
      }
    } else {
      results.failed++;
    }
  });

  return results;
};

// Re-export types for external usage
export type {
  RegistrationStats,
  DiscoveredBlock,
  BlockModule,
  RegistrationResult,
} from './types';

// Re-export error classes for error handling
export {
  BlockNotFoundError,
  BlockValidationError,
} from './types';
