/**
 * Type definitions for the CampaignBridge Block Registration System
 */

/**
 * Result of a block registration attempt
 */
export type RegistrationResult =
  | { success: true; skipped: true }
  | { success: true }
  | { success: false; error: Error };

/**
 * A block module that can be registered with WordPress
 */
export interface BlockModule {
  init: () => void;
}

/**
 * Information about a discovered block
 */
export interface DiscoveredBlock {
  name: string;
  path: string;
  module: BlockModule;
}

/**
 * Statistics from the block registration process
 */
export interface RegistrationStats {
  discovered: number;
  registered: number;
  skipped: number;
  failed: number;
}

/**
 * Interface for block registry operations
 */
export interface BlockRegistry {
  isRegistered: (blockName: string) => boolean;
  markRegistered: (blockName: string) => void;
  cacheDiscoveredBlock: (blockName: string, module: BlockModule) => void;
}

/**
 * Interface for block discovery operations
 */
export interface BlockDiscovery {
  getBlockPaths: () => string[];
  loadBlockModule: (blockPath: string) => BlockModule | null;
  extractBlockName: (blockPath: string) => string;
  createFullBlockName: (blockName: string) => string;
  discoverAllBlocks: () => DiscoveredBlock[];
}

/**
 * Interface for block registration operations
 */
export interface BlockRegistration {
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
 * Custom error types for better error handling
 */
export class BlockNotFoundError extends Error {
  constructor(blockName: string) {
    super(`Block module not found for "${blockName}"`);
    this.name = 'BlockNotFoundError';
  }
}

export class BlockValidationError extends Error {
  constructor(blockName: string, message: string) {
    super(`Block "${blockName}" validation failed: ${message}`);
    this.name = 'BlockValidationError';
  }
}
