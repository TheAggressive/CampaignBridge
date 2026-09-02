/**
 * Block Discovery Module
 *
 * Handles the automatic discovery and loading of block modules
 * from the blocks directory using webpack's require.context.
 */

import { BLOCK_CONFIG, blockContext } from './config';
import type { BlockDiscovery, BlockModule, DiscoveredBlock } from './types';

/**
 * Creates a block discovery system for finding and loading block modules
 *
 * Provides functionality to automatically discover all block modules in the
 * blocks directory using webpack's require.context.
 *
 * @returns BlockDiscovery instance
 */
export const createBlockDiscovery = (): BlockDiscovery => {
  /**
   * Extracts the block name from a file path
   *
   * Removes the leading "./" and trailing "/index.{ts,tsx}" from the path
   * to get the clean block name.
   */
  const extractBlockName = (blockPath: string): string =>
    blockPath.replace('./', '').replace(/\/index\.(ts|tsx)$/, '');

  /**
   * Creates a full block name with the namespace prefix
   */
  const createFullBlockName = (blockName: string): string =>
    `${BLOCK_CONFIG.NAMESPACE}/${blockName}`;

  /**
   * Creates a discovered block object from a file path
   */
  const createDiscoveredBlock = (blockPath: string): DiscoveredBlock => {
    const blockName = extractBlockName(blockPath);
    const fullBlockName = createFullBlockName(blockName);

    try {
      const blockModule: BlockModule = blockContext(blockPath);

      if (!blockModule || typeof blockModule.init !== 'function') {
        throw new Error(`Block module ${fullBlockName} has no init function.`);
      }

      // Webpack exposes ES module exports as a read-only namespace object.
      // Validate the canonical name exported from block.json instead of
      // attempting to mutate that namespace during discovery.
      if (blockModule.name !== fullBlockName) {
        throw new Error(
          `Block module name "${blockModule.name}" does not match "${fullBlockName}".`
        );
      }

      return {
        name: fullBlockName,
        module: blockModule,
      };
    } catch (error) {
      const reason = error instanceof Error ? error.message : String(error);
      throw new Error(`Unable to load ${fullBlockName}: ${reason}`);
    }
  };

  /**
   * Discovers and loads all available block modules
   */
  const discoverAllBlocks = (): DiscoveredBlock[] =>
    blockContext
      .keys()
      .filter((path: string) => BLOCK_CONFIG.PATTERN.test(path))
      .map(createDiscoveredBlock);

  return {
    discoverAllBlocks,
  };
};

/**
 * Global block discovery instance
 */
export const blockDiscovery = createBlockDiscovery();
