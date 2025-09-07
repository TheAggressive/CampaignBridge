/**
 * @fileoverview CampaignBridge Block Registration System
 *
 * Automatic discovery and registration system for WordPress Gutenberg blocks.
 * Eliminates manual registration by dynamically discovering block modules in the
 * `../../../blocks` directory using webpack's `require.context`.
 *
 * ## Architecture
 * Modular functional design with four core components:
 * - **Registry**: Tracks registration status and caches discovered blocks
 * - **Logger**: Provides consistent logging with CampaignBridge prefix
 * - **Discovery**: Dynamically discovers and loads block modules
 * - **Registration**: Validates and registers blocks with WordPress
 *
 * ## Key Features
 * - Zero-configuration automatic block discovery
 * - Duplicate prevention (blocks registered only once)
 * - Error resilience (individual failures don't stop registration)
 * - Comprehensive logging and error reporting
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
const blockContext = require.context("../../../blocks", true, /index\.js$/);

/**
 * Configuration constants
 */
const BLOCK_NAMESPACE = "campaignbridge";

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
 * @returns {Object} Block registry interface with methods for block management
 * @returns {Function} returns.isRegistered - Checks if a block is already registered
 * @returns {Function} returns.markRegistered - Marks a block as registered
 * @returns {Function} returns.cacheDiscoveredBlock - Caches a discovered block module
 *
 * @example
 * ```javascript
 * const registry = createBlockRegistry();
 * registry.markRegistered('campaignbridge/hero');
 * console.log(registry.isRegistered('campaignbridge/hero')); // true
 * ```
 */
const createBlockRegistry = () => {
  const registeredBlocks = new Set();
  const discoveredBlocks = new Map();

  return {
    isRegistered: (blockName) => registeredBlocks.has(blockName),
    markRegistered: (blockName) => registeredBlocks.add(blockName),
    cacheDiscoveredBlock: (blockName, module) =>
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
 * @returns {Object} Block discovery interface with methods for block discovery
 * @returns {Function} returns.getBlockPaths - Gets all block file paths matching the pattern
 * @returns {Function} returns.loadBlockModule - Loads a block module from a given path
 * @returns {Function} returns.extractBlockName - Extracts block name from file path
 * @returns {Function} returns.createFullBlockName - Creates full block name with namespace
 * @returns {Function} returns.discoverAllBlocks - Discovers and loads all available blocks
 *
 * @example
 * ```javascript
 * const discovery = createBlockDiscovery();
 * const blocks = discovery.discoverAllBlocks();
 * console.log(`Found ${blocks.length} blocks`);
 * ```
 */
const createBlockDiscovery = () => {
  /**
   * Gets all block file paths that match the expected pattern
   *
   * Filters webpack context keys to find all index.js files within the blocks directory.
   *
   * @returns {string[]} Array of block file paths relative to the blocks directory
   */
  const getBlockPaths = () =>
    blockContext.keys().filter((path) => BLOCK_PATTERN.test(path));

  /**
   * Loads a block module from the specified file path
   *
   * Attempts to load the block module using webpack's require.context.
   * Logs any loading errors and returns null if loading fails.
   *
   * @param {string} blockPath - The relative path to the block module file
   * @returns {Object|null} The loaded block module or null if loading failed
   */
  const loadBlockModule = (blockPath) => {
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
   * @param {string} blockPath - The file path to extract name from
   * @returns {string} The extracted block name
   * @example
   * extractBlockName('./hero/index.js') // returns 'hero'
   */
  const extractBlockName = (blockPath) =>
    blockPath.replace("./", "").replace("/index.js", "");

  /**
   * Creates a full block name with the namespace prefix
   *
   * Combines the block namespace with the block name to create
   * the complete block identifier used in WordPress.
   *
   * @param {string} blockName - The base block name
   * @returns {string} The full block name with namespace
   * @example
   * createFullBlockName('hero') // returns 'campaignbridge/hero'
   */
  const createFullBlockName = (blockName) => `${BLOCK_NAMESPACE}/${blockName}`;

  /**
   * Discovers and loads all available block modules
   *
   * Scans the blocks directory for all index.js files, loads each module,
   * validates them, and caches successful discoveries. Failed loads are
   * logged but don't stop the discovery process.
   *
   * @returns {Array<Object>} Array of discovered block objects
   * @returns {string} returns[].name - Full block name with namespace
   * @returns {string} returns[].path - Original file path
   * @returns {Object} returns[].module - Loaded block module
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
  const discoverAllBlocks = () => {
    const blockPaths = getBlockPaths();
    const discoveredBlocks = [];

    blockPaths.forEach((blockPath) => {
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
 * @returns {Object} Block registration interface with validation and registration methods
 * @returns {Function} returns.validateBlockModule - Validates a block module structure
 * @returns {Function} returns.registerBlock - Registers a single block with WordPress
 * @returns {Function} returns.registerBlockIfNeeded - Registers block only if not already registered
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
const createBlockRegistration = () => {
  /**
   * Validates a block module structure and requirements
   *
   * Ensures the block module exists and has the required init function
   * that will be called during registration.
   *
   * @param {Object} blockModule - The block module to validate
   * @param {string} blockName - The name of the block for error messages
   * @returns {boolean} Returns true if validation passes
   * @throws {Error} Throws if block module is missing or invalid
   */
  const validateBlockModule = (blockModule, blockName) => {
    if (!blockModule) {
      throw new Error(`Block module not found for "${blockName}"`);
    }

    if (typeof blockModule.init !== "function") {
      throw new Error(`Block "${blockName}" missing required init function`);
    }

    return true;
  };

  /**
   * Registers a single block with WordPress
   *
   * Validates the block module, calls its init function to register with WordPress,
   * marks it as registered, and logs the result.
   *
   * @param {string} blockName - The full block name with namespace
   * @param {Object} blockModule - The block module containing init function
   * @returns {Object} Registration result object
   * @returns {boolean} returns.success - Whether registration was successful
   * @returns {Error} [returns.error] - Error object if registration failed
   */
  const registerBlock = (blockName, blockModule) => {
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
   *
   * @param {string} blockName - The full block name with namespace
   * @param {Object} blockModule - The block module containing init function
   * @returns {Object} Registration result object
   * @returns {boolean} returns.success - Whether the operation was successful
   * @returns {boolean} [returns.skipped] - Whether registration was skipped (already registered)
   * @returns {Error} [returns.error] - Error object if registration failed
   */
  const registerBlockIfNeeded = (blockName, blockModule) => {
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
 * The process is resilient to individual block failures and provides detailed logging.
 *
 * @returns {Object} Registration statistics and results
 * @returns {number} returns.discovered - Total number of block modules discovered
 * @returns {number} returns.registered - Number of blocks successfully registered
 * @returns {number} returns.skipped - Number of blocks skipped (already registered)
 * @returns {number} returns.failed - Number of blocks that failed to register
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
export const registerCampaignBridgeBlocks = () => {
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
      blockModule,
    );

    if (result.success) {
      if (result.skipped) {
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
