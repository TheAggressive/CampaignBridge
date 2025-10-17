/**
 * Block Registration Module
 *
 * Handles the validation and registration of block modules with WordPress.
 * Ensures blocks are properly validated before registration and handles errors gracefully.
 */

import { blockRegistry } from './registry';
import type {
  BlockModule,
  BlockNotFoundError,
  BlockRegistration,
  BlockValidationError,
  RegistrationResult,
} from './types';

/**
 * Creates a block registration system for managing block initialization
 *
 * Provides functionality to validate block modules, register them with WordPress,
 * and handle registration errors gracefully. Ensures blocks are only registered
 * once and provides detailed feedback on registration outcomes.
 *
 * @returns BlockRegistration instance
 *
 * @example
 * ```typescript
 * const registration = createBlockRegistration();
 * const result = registration.registerBlockIfNeeded('campaignbridge/hero', blockModule);
 * if (result.success && !result.skipped) {
 *   console.log('Block registered successfully');
 * }
 * ```
 */
export const createBlockRegistration = (): BlockRegistration => {
  /**
   * Validates a block module structure and requirements
   *
   * Ensures the block module exists and has the required init function
   * that will be called during registration.
   *
   * @throws {BlockNotFoundError} If block module is missing
   * @throws {BlockValidationError} If block module is invalid
   */
  const validateBlockModule = (
    blockModule: BlockModule,
    blockName: string
  ): boolean => {
    if (!blockModule) {
      throw new BlockNotFoundError(blockName);
    }

    if (typeof blockModule.init !== 'function') {
      throw new BlockValidationError(
        blockName,
        'missing required init function'
      );
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
      return {
        success: false,
        error: error instanceof Error ? error : new Error(String(error)),
      };
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

/**
 * Global block registration instance
 * Shared across the application for consistent block registration
 */
export const blockRegistration = createBlockRegistration();
