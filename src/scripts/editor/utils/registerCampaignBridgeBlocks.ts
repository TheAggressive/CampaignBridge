/**
 * CampaignBridge Block Registration - Core API
 *
 * Provides the single API for registering compiler-supported CampaignBridge
 * email blocks.
 *
 * Usage:
 * ```typescript
 * import { registerCampaignBridgeBlocks } from './utils/registerCampaignBridgeBlocks';
 *
 * // Register all CampaignBridge blocks
 * registerCampaignBridgeBlocks();
 * ```
 */

// Re-export the registry entry point.
export { registerCampaignBridgeBlocks } from './block-registry';

// Re-export types for TypeScript users
export type { RegistrationStats } from './block-registry';
