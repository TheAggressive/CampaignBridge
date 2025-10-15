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

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

/**
 * Block metadata imported from block.json
 * @type {Object}
 */
export { metadata };

/**
 * Block name extracted from metadata
 * @type {string}
 */
export const { name } = metadata;

/**
 * Block settings configuration
 *
 * Defines the edit component for the post title block.
 * Note: This block uses server-side rendering, so no save component is needed.
 *
 * @type {Object}
 * @property {Function} edit - The edit component for block editing
 */
export const settings = {
  edit: Edit,
};

/**
 * Initialize and register the post title block
 *
 * Registers the block with WordPress using the metadata and settings.
 * This function is called immediately to register the block on load.
 *
 * @return {void}
 */
export const init = () => registerBlockType({ name, ...metadata }, settings);

// Initialize the block immediately
init();
