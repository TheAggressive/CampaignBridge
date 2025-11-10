/**
 * CampaignBridge Post Title Block
 *
 * Displays the title of a selected post within email campaigns. This block consumes
 * context from parent post card blocks to dynamically display post titles with
 * customizable typography and styling.
 *
 * Features:
 * - Dynamic title rendering from post context
 * - Full typography controls (font size, weight, alignment, etc.)
 * - Color support (text and background)
 * - Spacing and shadow controls
 * - Automatic fallback for missing post data
 *
 * Context Dependencies:
 * - campaignbridge:postId - The post ID to display title for
 * - campaignbridge:postType - The post type for data retrieval
 *
 * @module post-title
 */

import type { BlockConfiguration } from '@wordpress/blocks';
import { registerBlockType } from '@wordpress/blocks';
import type { ComponentType } from 'react';

import metadata from './block.json';
import Edit from './edit';

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
 * Defines the edit component for the post title block.
 * Note: This block uses server-side rendering, so no save component is needed.
 */
export interface PostTitleBlockSettings {
  edit: ComponentType<any>;
}

export const settings: PostTitleBlockSettings = {
  edit: Edit,
};

/**
 * Initialize and register the post title block
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
