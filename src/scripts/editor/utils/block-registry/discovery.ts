/**
 * Block Discovery Module
 *
 * Handles the automatic discovery and loading of block modules
 * from the blocks directory using webpack's require.context.
 */

import { blockContext, BLOCK_CONFIG } from './config';
import { blockRegistry } from './registry';
import type { BlockDiscovery, BlockModule, DiscoveredBlock } from './types';

/**
 * Creates a block discovery system for finding and loading block modules
 *
 * Provides functionality to automatically discover all block modules in the
 * blocks directory using webpack's require.context, validate them, and
 * prepare them for registration.
 *
 * @returns BlockDiscovery instance
 *
 * @example
 * ```typescript
 * const discovery = createBlockDiscovery();
 * const blocks = discovery.discoverAllBlocks();
 * console.log(`Found ${blocks.length} blocks`);
 * ```
 */
export const createBlockDiscovery = (): BlockDiscovery => {
  /**
   * Gets all block file paths that match the expected pattern
   *
   * Filters webpack context keys to find all index.js files within the blocks directory.
   */
  const getBlockPaths = (): string[] =>
    blockContext.keys().filter((path: string) => BLOCK_CONFIG.PATTERN.test(path));

  /**
   * Loads a block module from the specified file path
   *
   * Attempts to load the block module using webpack's require.context.
   * Silently handles loading errors to ensure one failed block doesn't break others.
   */
  const loadBlockModule = (blockPath: string): BlockModule | null => {
    try {
      return blockContext(blockPath);
    } catch (error) {
      // Silently handle individual block loading failures
      return null;
    }
  };

  /**
   * Extracts the block name from a file path
   *
   * Removes the leading "./" and trailing "/index.js" from the path
   * to get the clean block name (e.g., "hero", "testimonial").
   *
   * @example
   * extractBlockName('./hero/index.js') // returns 'hero'
   */
  const extractBlockName = (blockPath: string): string =>
    blockPath.replace('./', '').replace('/index.js', '');

  /**
   * Creates a full block name with the namespace prefix
   *
   * Combines the block namespace with the block name to create
   * the complete block identifier used in WordPress.
   *
   * @example
   * createFullBlockName('hero') // returns 'campaignbridge/hero'
   */
  const createFullBlockName = (blockName: string): string =>
    `${BLOCK_CONFIG.NAMESPACE}/${blockName}`;

  /**
   * Creates a discovered block object from a file path
   *
   * Helper function that combines path parsing, module loading, and validation
   * into a single operation that returns a standardized block object.
   */
  const createDiscoveredBlock = (blockPath: string): DiscoveredBlock | null => {
    const blockName = extractBlockName(blockPath);
    const fullBlockName = createFullBlockName(blockName);
    const blockModule = loadBlockModule(blockPath);

    if (!blockModule) {
      return null;
    }

    // Cache the discovered block for later use
    blockRegistry.cacheDiscoveredBlock(fullBlockName, blockModule);

    return {
      name: fullBlockName,
      path: blockPath,
      module: blockModule,
    };
  };

  /**
   * Filters out null values from an array of potentially null items
   */
  const isValidBlock = (block: DiscoveredBlock | null): block is DiscoveredBlock =>
    block !== null;

  /**
   * Discovers and loads all available block modules
   *
   * Scans the blocks directory for all index.js files, loads each module,
   * validates them, and caches successful discoveries. Failed loads are
   * handled gracefully and don't stop the discovery process.
   *
   * Uses functional programming patterns for cleaner, more maintainable code.
   *
   * @example
   * ```typescript
   * const blocks = discoverAllBlocks();
   * // Returns: [
   * //   { name: 'campaignbridge/hero', path: './hero/index.js', module: {...} },
   * //   { name: 'campaignbridge/testimonial', path: './testimonial/index.js', module: {...} }
   * // ]
   * ```
   */
  const discoverAllBlocks = (): DiscoveredBlock[] =>
    getBlockPaths()
      .map(createDiscoveredBlock)
      .filter(isValidBlock);

  return {
    getBlockPaths,
    loadBlockModule,
    extractBlockName,
    createFullBlockName,
    discoverAllBlocks,
  };
};

/**
 * Global block discovery instance
 * Shared across the application for consistent block discovery
 */
export const blockDiscovery = createBlockDiscovery();
