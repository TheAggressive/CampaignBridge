/**
 * Type definitions for the CampaignBridge Block Registration System
 */

/**
 * A block module that can be registered with WordPress
 */
export interface BlockModule {
  init: () => void;
  name: string;
}

/**
 * Information about a discovered block
 */
export interface DiscoveredBlock {
  name: string;
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
 * Interface for block discovery operations
 */
export interface BlockDiscovery {
  discoverAllBlocks: () => DiscoveredBlock[];
}
