/**
 * Block patterns for the CampaignBridge template editor.
 *
 * Defines reusable block patterns and categories for email template creation.
 */

import { __ } from "@wordpress/i18n";

/**
 * Block pattern categories for organizing patterns.
 */
export const blockPatternCategories = [
  {
    name: "email-headers",
    label: __("Email Headers", "campaignbridge"),
    description: __("Header patterns for email templates", "campaignbridge"),
  },
  {
    name: "email-content",
    label: __("Email Content", "campaignbridge"),
    description: __("Content patterns for email templates", "campaignbridge"),
  },
  {
    name: "email-footers",
    label: __("Email Footers", "campaignbridge"),
    description: __("Footer patterns for email templates", "campaignbridge"),
  },
  {
    name: "call-to-action",
    label: __("Call to Action", "campaignbridge"),
    description: __(
      "Call-to-action patterns for email templates",
      "campaignbridge",
    ),
  },
];

/**
 * Block patterns for email templates.
 */
export const blockPatterns = [
  {
    name: "campaignbridge/header-simple",
    title: __("Simple Header", "campaignbridge"),
    description: __("A simple header with logo and text", "campaignbridge"),
    category: "email-headers",
    content: `
      <!-- wp:group {"className":"email-header"} -->
      <div class="wp-block-group email-header">
        <!-- wp:columns -->
        <div class="wp-block-columns">
          <!-- wp:column {"width":"33.33%"} -->
          <div class="wp-block-column" style="flex-basis:33.33%">
            <!-- wp:image {"sizeSlug":"medium","linkDestination":"none"} -->
            <figure class="wp-block-image size-medium">
              <img src="" alt="Logo" />
            </figure>
            <!-- /wp:image -->
          </div>
          <!-- /wp:column -->

          <!-- wp:column {"width":"66.67%"} -->
          <div class="wp-block-column" style="flex-basis:66.67%">
            <!-- wp:heading {"level":2} -->
            <h2>Welcome to Our Newsletter</h2>
            <!-- /wp:heading -->
          </div>
          <!-- /wp:column -->
        </div>
        <!-- /wp:columns -->
      </div>
      <!-- /wp:group -->
    `,
  },
  {
    name: "campaignbridge/hero-section",
    title: __("Hero Section", "campaignbridge"),
    description: __(
      "Eye-catching hero section with image and text",
      "campaignbridge",
    ),
    category: "email-content",
    content: `
      <!-- wp:group {"className":"email-hero"} -->
      <div class="wp-block-group email-hero">
        <!-- wp:columns -->
        <div class="wp-block-columns">
          <!-- wp:column {"width":"50%"} -->
          <div class="wp-block-column" style="flex-basis:50%">
            <!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large">
              <img src="" alt="Hero Image" />
            </figure>
            <!-- /wp:image -->
          </div>
          <!-- /wp:column -->

          <!-- wp:column {"width":"50%"} -->
          <div class="wp-block-column" style="flex-basis:50%">
            <!-- wp:heading {"level":1} -->
            <h1>Exciting News!</h1>
            <!-- /wp:heading -->

            <!-- wp:paragraph -->
            <p>Discover what's new and exciting in our latest update.</p>
            <!-- /wp:paragraph -->

            <!-- wp:buttons -->
            <div class="wp-block-buttons">
              <!-- wp:button -->
              <div class="wp-block-button">
                <a class="wp-block-button__link" href="#">Learn More</a>
              </div>
              <!-- /wp:button -->
            </div>
            <!-- /wp:buttons -->
          </div>
          <!-- /wp:column -->
        </div>
        <!-- /wp:columns -->
      </div>
      <!-- /wp:group -->
    `,
  },
  {
    name: "campaignbridge/two-column-content",
    title: __("Two Column Content", "campaignbridge"),
    description: __(
      "Two-column layout for content presentation",
      "campaignbridge",
    ),
    category: "email-content",
    content: `
      <!-- wp:columns -->
      <div class="wp-block-columns">
        <!-- wp:column -->
        <div class="wp-block-column">
          <!-- wp:heading {"level":3} -->
          <h3>Column One</h3>
          <!-- /wp:heading -->

          <!-- wp:paragraph -->
          <p>Add your content for the first column here.</p>
          <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column -->
        <div class="wp-block-column">
          <!-- wp:heading {"level":3} -->
          <h3>Column Two</h3>
          <!-- /wp:heading -->

          <!-- wp:paragraph -->
          <p>Add your content for the second column here.</p>
          <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
      </div>
      <!-- /wp:columns -->
    `,
  },
  {
    name: "campaignbridge/call-to-action",
    title: __("Call to Action", "campaignbridge"),
    description: __("Prominent call-to-action section", "campaignbridge"),
    category: "call-to-action",
    content: `
      <!-- wp:group {"className":"cta-section"} -->
      <div class="wp-block-group cta-section">
        <!-- wp:heading {"textAlign":"center","level":2} -->
        <h2 class="has-text-align-center">Ready to Get Started?</h2>
        <!-- /wp:heading -->

        <!-- wp:paragraph {"align":"center"} -->
        <p class="has-text-align-center">Join thousands of satisfied customers today.</p>
        <!-- /wp:paragraph -->

        <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
        <div class="wp-block-buttons">
          <!-- wp:button {"className":"is-style-fill"} -->
          <div class="wp-block-button is-style-fill">
            <a class="wp-block-button__link" href="#">Get Started Now</a>
          </div>
          <!-- /wp:button -->
        </div>
        <!-- /wp:buttons -->
      </div>
      <!-- /wp:group -->
    `,
  },
  {
    name: "campaignbridge/footer-simple",
    title: __("Simple Footer", "campaignbridge"),
    description: __("Basic footer with contact information", "campaignbridge"),
    category: "email-footers",
    content: `
      <!-- wp:group {"className":"email-footer"} -->
      <div class="wp-block-group email-footer">
        <!-- wp:separator -->
        <hr class="wp-block-separator has-alpha-channel-opacity" />
        <!-- /wp:separator -->

        <!-- wp:columns -->
        <div class="wp-block-columns">
          <!-- wp:column {"width":"50%"} -->
          <div class="wp-block-column" style="flex-basis:50%">
            <!-- wp:paragraph {"fontSize":"small"} -->
            <p class="has-small-font-size">© 2024 Company Name. All rights reserved.</p>
            <!-- /wp:paragraph -->
          </div>
          <!-- /wp:column -->

          <!-- wp:column {"width":"50%"} -->
          <div class="wp-block-column" style="flex-basis:50%">
            <!-- wp:paragraph {"align":"right","fontSize":"small"} -->
            <p class="has-text-align-right has-small-font-size">
              <a href="#">Unsubscribe</a> | <a href="#">Privacy Policy</a>
            </p>
            <!-- /wp:paragraph -->
          </div>
          <!-- /wp:column -->
        </div>
        <!-- /wp:columns -->
      </div>
      <!-- /wp:group -->
    `,
  },
];
