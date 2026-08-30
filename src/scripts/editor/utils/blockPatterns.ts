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
