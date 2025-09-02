/**
 * CampaignBridge Block Registration System
 *
 * A functional, DRY, and future-proof block registration system
 * using arrow functions and modern JavaScript patterns.
 */

// Use webpack's require.context to dynamically discover all block modules
const blockContext = require.context("../../../blocks", true, /index\.js$/);

/**
 * Configuration constants
 */
const BLOCK_NAMESPACE = "campaignbridge";
const BLOCK_PATTERN = /index\.js$/;

/**
 * Block Registry - Functional approach for tracking registered blocks
 */
const createBlockRegistry = () => {
  const registeredBlocks = new Set();
  const discoveredBlocks = new Map();

  return {
    isRegistered: (blockName) => registeredBlocks.has(blockName),
    markRegistered: (blockName) => registeredBlocks.add(blockName),
    getRegisteredCount: () => registeredBlocks.size,
    getRegisteredBlocks: () => Array.from(registeredBlocks),
    cacheDiscoveredBlock: (blockName, module) =>
      discoveredBlocks.set(blockName, module),
    getDiscoveredBlock: (blockName) => discoveredBlocks.get(blockName),
    clear: () => {
      registeredBlocks.clear();
      discoveredBlocks.clear();
    },
  };
};

const blockRegistry = createBlockRegistry();

/**
 * Logger - Functional logging with consistent formatting
 */
const createBlockLogger = () => {
  const log = (level, message, data = null) => {
    const prefix = "CampaignBridge";
    const formattedMessage = `[${prefix}] ${message}`;

    switch (level) {
      case "info":
        console.log(formattedMessage, data || "");
        break;
      case "warn":
        console.warn(formattedMessage, data || "");
        break;
      case "error":
        console.error(formattedMessage, data || "");
        break;
      default:
        console.log(formattedMessage, data || "");
    }
  };

  return {
    log,
    registrationSuccess: (blockName) =>
      log("info", `Registered block "${blockName}"`),
    registrationSkipped: (blockName) =>
      log("info", `Block "${blockName}" already registered`),
    registrationFailed: (blockName, error) =>
      log("error", `Failed to register block "${blockName}"`, error),
    discoveryComplete: (results) =>
      log(
        "info",
        `Discovery complete - ${results.registered} registered, ${results.skipped} skipped, ${results.failed} failed`,
      ),
  };
};

const logger = createBlockLogger();

/**
 * Block Discovery - Functional approach for finding and loading blocks
 */
const createBlockDiscovery = () => {
  const getBlockPaths = () =>
    blockContext.keys().filter((path) => BLOCK_PATTERN.test(path));

  const loadBlockModule = (blockPath) => {
    try {
      return blockContext(blockPath);
    } catch (error) {
      logger.registrationFailed(blockPath, error);
      return null;
    }
  };

  const extractBlockName = (blockPath) =>
    blockPath.replace("./", "").replace("/index.js", "");

  const createFullBlockName = (blockName) => `${BLOCK_NAMESPACE}/${blockName}`;

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
 * Block Registration - Functional approach for registration logic
 */
const createBlockRegistration = () => {
  const validateBlockModule = (blockModule, blockName) => {
    if (!blockModule) {
      throw new Error(`Block module not found for "${blockName}"`);
    }

    if (typeof blockModule.init !== "function") {
      throw new Error(`Block "${blockName}" missing required init function`);
    }

    return true;
  };

  const registerBlock = (blockName, blockModule) => {
    try {
      validateBlockModule(blockModule, blockName);
      blockModule.init();
      blockRegistry.markRegistered(blockName);
      logger.registrationSuccess(blockName);
      return { success: true };
    } catch (error) {
      logger.registrationFailed(blockName, error);
      return { success: false, error };
    }
  };

  const registerBlockIfNeeded = (blockName, blockModule) => {
    if (blockRegistry.isRegistered(blockName)) {
      logger.registrationSkipped(blockName);
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
 * Register all CampaignBridge blocks
 *
 * This function dynamically discovers all block modules in the blocks directory
 * and registers any that haven't been registered yet.
 *
 * @return {object} Registration results with statistics
 */
export const registerCustomBlocks = () => {
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

  logger.discoveryComplete(results);
  return results;
};
