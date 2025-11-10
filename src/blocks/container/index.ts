/**
 * CampaignBridge Container Block
 *
 * The root container block for CampaignBridge email templates. This block provides
 * the foundational layout structure with configurable padding, max-width, and
 * color support. It serves as the parent container for all other email content blocks.
 *
 * Features:
 * - Responsive max-width control
 * - Configurable inner and outer padding
 * - Background and text color support via WordPress color panel
 * - Inner blocks support for nested content
 * - Locked block to prevent accidental removal/movement
 *
 * @module container
 */

import type { BlockConfiguration } from '@wordpress/blocks';
import { registerBlockType } from '@wordpress/blocks';
import type { ComponentType } from 'react';

import metadata from './block.json';
import Edit from './edit';
import Save from './save';

/**
 * Block metadata imported from block.json
 */
export { metadata };

/**
 * Block name extracted from metadata
 */
export const { name }: { name: string } = metadata;

/**
 * Block settings configuration
 *
 * Defines the edit and save components for the container block.
 */
export interface ContainerBlockSettings {
  edit: ComponentType<any>;
  save: ComponentType<any> | (() => JSX.Element | null);
}

export const settings: ContainerBlockSettings = {
  edit: Edit,
  save: Save,
};

/**
 * Initialize and register the container block
 *
 * Registers the block with WordPress using the metadata and settings.
 * This function is called immediately to register the block on load.
 */
export const init = (): void => {
  registerBlockType(
    { name, ...metadata } as unknown as BlockConfiguration,
    settings
  );
};

// Initialize the block immediately
init();
