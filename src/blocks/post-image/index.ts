/**
 * CampaignBridge Post Image Block
 *
 * Displays the featured image of a selected post within email campaigns. This block
 * consumes context from parent post card blocks to dynamically display post featured
 * images with customizable styling and responsive behavior.
 *
 * Features:
 * - Dynamic image rendering from post featured image
 * - Responsive image sizing and aspect ratio controls
 * - Border, shadow, and spacing customization
 * - Color overlay and link support
 * - Automatic fallback for posts without featured images
 * - Email-optimized image rendering
 *
 * Context Dependencies:
 * - campaignbridge:postId - The post ID to display image for
 * - campaignbridge:postType - The post type for data retrieval
 * - campaignbridge:showImage - Optional flag to conditionally show/hide image
 *
 * @module post-image
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
 * Defines the edit component for the post image block.
 * Note: This block uses server-side rendering, so no save component is needed.
 */
export interface PostImageBlockSettings {
  edit: ComponentType<any>;
}

export const settings: PostImageBlockSettings = {
  edit: Edit,
};

/**
 * Initialize and register the post image block
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
