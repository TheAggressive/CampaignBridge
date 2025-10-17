/**
 * Block Registry Module
 *
 * Manages the registration state and caching of discovered blocks.
 * Ensures blocks are only registered once and provides fast lookups.
 */

import type { BlockRegistry, BlockModule } from './types';

/**
 * Creates a block registry for tracking registered blocks
 *
 * Provides a functional interface for managing the lifecycle of block registrations,
 * including caching discovered blocks and tracking registration status.
 *
 * @returns BlockRegistry instance
 *
 * @example
 * ```typescript
 * const registry = createBlockRegistry();
 * registry.markRegistered('campaignbridge/hero');
 * console.log(registry.isRegistered('campaignbridge/hero')); // true
 * ```
 */
export const createBlockRegistry = (): BlockRegistry => {
  // Use Set for O(1) lookup performance for registered blocks
  const registeredBlocks = new Set<string>();

  // Use Map for O(1) lookup of cached discovered blocks
  const discoveredBlocks = new Map<string, BlockModule>();

  return {
    /**
     * Check if a block has been registered
     */
    isRegistered: (blockName: string): boolean =>
      registeredBlocks.has(blockName),

    /**
     * Mark a block as registered
     */
    markRegistered: (blockName: string): void => {
      registeredBlocks.add(blockName);
    },

    /**
     * Cache a discovered block module for later use
     */
    cacheDiscoveredBlock: (blockName: string, module: BlockModule): void => {
      discoveredBlocks.set(blockName, module);
    },
  };
};

/**
 * Global block registry instance
 * Shared across the application to ensure consistent state
 */
export const blockRegistry = createBlockRegistry();
