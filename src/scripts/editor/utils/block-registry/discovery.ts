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
  const createDiscoveredBlock = (blockPath: string): DiscoveredBlock | null => {
    const blockName = extractBlockName(blockPath);
    const fullBlockName = createFullBlockName(blockName);

    try {
      const blockModule: BlockModule = blockContext(blockPath);

      if (!blockModule || typeof blockModule.init !== 'function') {
        return null;
      }

      // Set the block name on the module for easier access
      blockModule.name = fullBlockName;

      return {
        name: fullBlockName,
        module: blockModule,
      };
    } catch {
      // Silently handle individual block loading failures.
      return null;
    }
  };

  /**
   * Discovers and loads all available block modules
   */
  const discoverAllBlocks = (): DiscoveredBlock[] =>
    blockContext
      .keys()
      .filter((path: string) => BLOCK_CONFIG.PATTERN.test(path))
      .map(createDiscoveredBlock)
      .filter((block): block is DiscoveredBlock => block !== null);

  return {
    discoverAllBlocks,
  };
};

/**
 * Global block discovery instance
 */
export const blockDiscovery = createBlockDiscovery();
