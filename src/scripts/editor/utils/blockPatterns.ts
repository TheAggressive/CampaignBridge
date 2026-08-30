import { __ } from '@wordpress/i18n';

export const blockPatternCategories = [
  {
    name: 'email-basic',
    label: __('Email blocks', 'campaignbridge'),
    description: __('Compiler-supported email compositions', 'campaignbridge'),
  },
];

export const blockPatterns = [
  {
    name: 'campaignbridge/email-introduction',
    title: __('Email introduction', 'campaignbridge'),
    description: __(
      'A heading, rich text, call to action, and divider',
      'campaignbridge'
    ),
    category: 'email-basic',
    content: `
      <!-- wp:campaignbridge/section -->
      <!-- wp:campaignbridge/heading {"content":"Your email heading","level":2} /-->
      <!-- wp:campaignbridge/text {"content":"Introduce your message and give readers a clear reason to continue."} /-->
      <!-- wp:campaignbridge/button {"label":"Learn more","url":"https://example.com"} /-->
      <!-- wp:campaignbridge/spacer {"height":24} /-->
      <!-- wp:campaignbridge/divider /-->
      <!-- /wp:campaignbridge/section -->
    `,
  },
  {
    name: 'campaignbridge/post-card',
    title: __('Post card', 'campaignbridge'),
    description: __(
      'Post image, title, excerpt, and call to action',
      'campaignbridge'
    ),
    category: 'email-basic',
    content: `
      <!-- wp:campaignbridge/post-card {"postType":"post","postId":0} -->
      <!-- wp:campaignbridge/post-image /-->
      <!-- wp:campaignbridge/post-title /-->
      <!-- wp:campaignbridge/post-excerpt /-->
      <!-- wp:campaignbridge/post-cta /-->
      <!-- /wp:campaignbridge/post-card -->
    `,
  },
];
