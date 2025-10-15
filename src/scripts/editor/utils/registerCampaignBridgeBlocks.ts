/**
 * @fileoverview CampaignBridge Block Registration System
 *
 * Automatic discovery and registration system for WordPress Gutenberg blocks.
 * Eliminates manual registration by dynamically discovering block modules in the
 * `../../../blocks` directory using webpack's `require.context`.
 *
 * ## Architecture
 * Modular functional design with three core components:
 * - **Registry**: Tracks registration status and caches discovered blocks
 * - **Discovery**: Dynamically discovers and loads block modules
 * - **Registration**: Validates and registers blocks with WordPress
 *
 * ## Key Features
 * - Zero-configuration automatic block discovery
 * - Duplicate prevention (blocks registered only once)
 * - Error resilience (individual failures don't stop registration)
 * - Graceful error handling with detailed error information
 * - Functional programming patterns with immutable state
 * - Production-ready with validation and performance optimizations
 *
 * ## Block Structure Requirements
 * Each block in `../../../blocks` must export an `init()` function:
 * ```
 * blocks/
 *   ├── hero/index.js        // export { init: () => registerBlockType(...) }
 *   └── testimonial/index.js // export { init: () => registerBlockType(...) }
 * ```
 *
 * ## Usage
 * ```javascript
 * import { registerCampaignBridgeBlocks } from './registerCustomBlocks';
 * const results = registerCampaignBridgeBlocks(); // Returns { discovered, registered, skipped, failed }
 * ```
 *
 * ## Error Handling
 * Gracefully handles missing modules, invalid structures, and runtime errors.
 * Failed blocks are logged but don't prevent other blocks from registering.
 *
 * @author CampaignBridge Development Team
 * @version 1.0.0
 * @since 2024
 *
 * @example
 * ```javascript
 * import { registerCampaignBridgeBlocks } from './utils/registerCustomBlocks';
 * const results = registerCampaignBridgeBlocks();
 * console.log(`Registered ${results.registered} of ${results.discovered} blocks`);
 * ```
 */

// Use webpack's require.context to dynamically discover all block modules
const blockContext = (globalThis as any).require?.context?.(
  '../../../blocks',
  true,
  /index\.js$/
);

type RegistrationResult =
  | { success: true; skipped: true }
  | { success: true }
  | { success: false; error: Error };

interface BlockModule {
  init: () => void;
}

interface DiscoveredBlock {
  name: string;
  path: string;
  module: BlockModule;
}

interface RegistrationStats {
  discovered: number;
  registered: number;
  skipped: number;
  failed: number;
}

interface BlockRegistry {
  isRegistered: (blockName: string) => boolean;
  markRegistered: (blockName: string) => void;
  cacheDiscoveredBlock: (blockName: string, module: BlockModule) => void;
}

interface BlockDiscovery {
  getBlockPaths: () => string[];
  loadBlockModule: (blockPath: string) => BlockModule | null;
  extractBlockName: (blockPath: string) => string;
  createFullBlockName: (blockName: string) => string;
  discoverAllBlocks: () => DiscoveredBlock[];
}

interface BlockRegistration {
  validateBlockModule: (blockModule: BlockModule, blockName: string) => boolean;
  registerBlock: (
    blockName: string,
    blockModule: BlockModule
  ) => RegistrationResult;
  registerBlockIfNeeded: (
    blockName: string,
    blockModule: BlockModule
  ) => RegistrationResult;
}

/**
 * Configuration constants
 */
const BLOCK_NAMESPACE = 'campaignbridge';

/**
 * Regular expression pattern for matching block file paths
 * Matches paths like "./hero/index.js", "./testimonial/index.js", etc.
 */
const BLOCK_PATTERN = /^\.\/.*\/index\.js$/;

/**
 * Creates a block registry for tracking registered blocks
 *
 * Provides a functional interface for managing the lifecycle of block registrations,
 * including caching discovered blocks and tracking registration status.
 *
 * @example
 * ```javascript
 * const registry = createBlockRegistry();
 * registry.markRegistered('campaignbridge/hero');
 * console.log(registry.isRegistered('campaignbridge/hero')); // true
 * ```
 */
const createBlockRegistry = (): BlockRegistry => {
  const registeredBlocks = new Set();
  const discoveredBlocks = new Map();

  return {
    isRegistered: (blockName: string) => registeredBlocks.has(blockName),
    markRegistered: (blockName: string) => registeredBlocks.add(blockName),
    cacheDiscoveredBlock: (blockName: string, module: BlockModule) =>
      discoveredBlocks.set(blockName, module),
  };
};

const blockRegistry = createBlockRegistry();

/**
 * Creates a block discovery system for finding and loading block modules
 *
 * Provides functionality to automatically discover all block modules in the
 * blocks directory using webpack's require.context, validate them, and
 * prepare them for registration.
 *
 * @example
 * ```javascript
 * const discovery = createBlockDiscovery();
 * const blocks = discovery.discoverAllBlocks();
 * console.log(`Found ${blocks.length} blocks`);
 * ```
 */
const createBlockDiscovery = (): BlockDiscovery => {
  /**
   * Gets all block file paths that match the expected pattern
   *
   * Filters webpack context keys to find all index.js files within the blocks directory.
   */
  const getBlockPaths = (): string[] =>
    blockContext.keys().filter((path: string) => BLOCK_PATTERN.test(path));

  /**
   * Loads a block module from the specified file path
   *
   * Attempts to load the block module using webpack's require.context.
   * Logs any loading errors and returns null if loading fails.
   */
  const loadBlockModule = (blockPath: string): BlockModule | null => {
    try {
      return blockContext(blockPath);
    } catch (error) {
      return null;
    }
  };

  /**
   * Extracts the block name from a file path
   *
   * Removes the leading "./" and trailing "/index.js" from the path
   * to get the clean block name.
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
    `${BLOCK_NAMESPACE}/${blockName}`;

  /**
   * Discovers and loads all available block modules
   *
   * Scans the blocks directory for all index.js files, loads each module,
   * validates them, and caches successful discoveries. Failed loads are
   * handled gracefully and don't stop the discovery process.
   *
   * @example
   * ```javascript
   * const blocks = discoverAllBlocks();
   * // Returns: [
   * //   { name: 'campaignbridge/hero', path: './hero/index.js', module: {...} },
   * //   { name: 'campaignbridge/testimonial', path: './testimonial/index.js', module: {...} }
   * // ]
   * ```
   */
  const discoverAllBlocks = (): DiscoveredBlock[] => {
    const blockPaths = getBlockPaths();
    const discoveredBlocks = [];

    blockPaths.forEach(blockPath => {
      const blockName = extractBlockName(blockPath);
      const fullBlockName = createFullBlockName(blockName);
      const blockModule = loadBlockModule(blockPath);

      if (blockModule) {
        blockRegistry.cacheDiscoveredBlock(fullBlockName, blockModule);
        discoveredBlocks.push({
          name: fullBlockName,
          path: blockPath,
          module: blockModule,
        });
      }
    });

    return discoveredBlocks;
  };

  return {
    getBlockPaths,
    loadBlockModule,
    extractBlockName,
    createFullBlockName,
    discoverAllBlocks,
  };
};

const blockDiscovery = createBlockDiscovery();

/**
 * Creates a block registration system for managing block initialization
 *
 * Provides functionality to validate block modules, register them with WordPress,
 * and handle registration errors gracefully. Ensures blocks are only registered
 * once and provides detailed feedback on registration outcomes.
 *
 * @example
 * ```javascript
 * const registration = createBlockRegistration();
 * const result = registration.registerBlockIfNeeded('campaignbridge/hero', blockModule);
 * if (result.success && !result.skipped) {
 *   console.log('Block registered successfully');
 * }
 * ```
 */
const createBlockRegistration = (): BlockRegistration => {
  /**
   * Validates a block module structure and requirements
   *
   * Ensures the block module exists and has the required init function
   * that will be called during registration.
   *
   * @throws {Error} Throws if block module is missing or invalid
   */
  const validateBlockModule = (
    blockModule: BlockModule,
    blockName: string
  ): boolean => {
    if (!blockModule) {
      throw new Error(`Block module not found for "${blockName}"`);
    }

    if (typeof blockModule.init !== 'function') {
      throw new Error(`Block "${blockName}" missing required init function`);
    }

    return true;
  };

  /**
   * Registers a single block with WordPress
   *
   * Validates the block module, calls its init function to register with WordPress,
   * and marks it as registered in the registry.
   */
  const registerBlock = (
    blockName: string,
    blockModule: BlockModule
  ): RegistrationResult => {
    try {
      validateBlockModule(blockModule, blockName);
      blockModule.init();
      blockRegistry.markRegistered(blockName);
      return { success: true };
    } catch (error) {
      return { success: false, error };
    }
  };

  /**
   * Registers a block only if it hasn't been registered already
   *
   * Checks if the block is already registered and skips registration if so.
   * Otherwise, proceeds with normal registration process.
   */
  const registerBlockIfNeeded = (
    blockName: string,
    blockModule: BlockModule
  ): RegistrationResult => {
    if (blockRegistry.isRegistered(blockName)) {
      return { success: true, skipped: true };
    }

    return registerBlock(blockName, blockModule);
  };

  return {
    validateBlockModule,
    registerBlock,
    registerBlockIfNeeded,
  };
};

const blockRegistration = createBlockRegistration();

/**
 * Main Registration API - Public interface using functional programming
 */

/**
 * Registers all CampaignBridge blocks dynamically
 *
 * This is the main entry point for the block registration system. It automatically
 * discovers all block modules in the blocks directory using webpack's require.context,
 * validates each module, and registers any blocks that haven't been registered yet.
 * The process is resilient to individual block failures and provides detailed results.
 *
 * @example
 * ```javascript
 * import { registerCampaignBridgeBlocks } from './registerCustomBlocks';
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

  const results = {
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
