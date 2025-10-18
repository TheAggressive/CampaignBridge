/**
 * CampaignBridge Block Registration - Core API
 *
 * Provides the main API for registering CampaignBridge blocks, similar to
 * WordPress core's registerCoreBlocks(). This is the primary entry point
 * for block registration in CampaignBridge.
 *
 * Usage:
 * ```typescript
 * import { registerCampaignBridgeBlocks } from './utils/registerCampaignBridgeBlocks';
 *
 * // Register all CampaignBridge blocks
 * registerCampaignBridgeBlocks();
 * ```
 */

// Re-export the core registration function (matches WordPress registerCoreBlocks API)
export { registerCampaignBridgeBlocks } from './block-registry';

// Re-export types for TypeScript users
export type { RegistrationStats } from './block-registry';
