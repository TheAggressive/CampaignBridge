/**
 * Configuration constants for the CampaignBridge Block Registration System
 */

/**
 * Block registration configuration
 */
export const BLOCK_CONFIG = {
  /** Namespace prefix for all CampaignBridge blocks */
  NAMESPACE: 'campaignbridge',

  /** Directory path relative to this file where blocks are located */
  DIRECTORY: '../../../blocks',

  /** File pattern for matching block index files */
  PATTERN: /^\.\/.*\/index\.js$/,

  /** Enable subdirectories in require.context */
  USE_SUBDIRECTORIES: true,
} as const;

/**
 * Type declaration for webpack's require.context
 * This allows TypeScript to understand the webpack API
 */
declare const require: {
  context: (
    directory: string,
    useSubdirectories: boolean,
    regExp: RegExp
  ) => {
    keys: () => string[];
    (id: string): any;
  };
};

/**
 * Webpack context for dynamic block discovery
 * This will be processed by webpack at build time and replaced with static imports
 */
export const blockContext = (require as any).context(
  '../../../../blocks',
  true,
  /index\.js$/
);
