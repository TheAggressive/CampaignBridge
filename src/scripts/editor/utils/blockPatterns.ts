/**
 * Simplified block patterns for CampaignBridge template editor.
 * Minimal patterns to reduce bundle size and complexity.
 */

import { __ } from '@wordpress/i18n';

/**
 * Basic block pattern categories.
 */
export const blockPatternCategories = [
  {
    name: 'email-basic',
    label: __('Email Blocks', 'campaignbridge'),
    description: __('Basic email template blocks', 'campaignbridge'),
  },
];

/**
 * Essential block patterns for email templates.
 */
export const blockPatterns = [
  {
    name: 'campaignbridge/columns-basic',
    title: __('Two Columns', 'campaignbridge'),
    description: __('Simple two-column layout', 'campaignbridge'),
    category: 'email-basic',
    content: `
      <!-- wp:columns -->
      <div class="wp-block-columns">
        <!-- wp:column -->
        <div class="wp-block-column">
          <!-- wp:paragraph -->
          <p>Left column content</p>
          <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
        <!-- wp:column -->
        <div class="wp-block-column">
          <!-- wp:paragraph -->
          <p>Right column content</p>
          <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
      </div>
      <!-- /wp:columns -->
    `,
  },
  {
    name: 'campaignbridge/button-cta',
    title: __('Call to Action', 'campaignbridge'),
    description: __('Simple call-to-action button', 'campaignbridge'),
    category: 'email-basic',
    content: `
      <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
      <div class="wp-block-buttons">
        <!-- wp:button -->
        <div class="wp-block-button">
          <a class="wp-block-button__link" href="#">Click Here</a>
        </div>
        <!-- /wp:button -->
      </div>
      <!-- /wp:buttons -->
    `,
  },
];
