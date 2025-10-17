/**
 * CampaignBridge Block Registration System - Legacy Compatibility Layer
 *
 * @deprecated This file now serves as a compatibility layer.
 * Import from './block-registry' instead for the new modular architecture.
 *
 * This maintains backward compatibility while encouraging migration
 * to the new modular structure in the 'block-registry' subdirectory.
 */

// Re-export the new modular API for backward compatibility
export {
  BlockNotFoundError,
  BlockValidationError,
  registerCampaignBridgeBlocks,
  type BlockModule,
  type DiscoveredBlock,
  type RegistrationResult,
  type RegistrationStats,
} from './block-registry';
