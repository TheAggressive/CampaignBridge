import { useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';

registerBlockType('campaignbridge/email-post-button', {
  edit({ context }) {
    const postId = context['campaignbridge:postId'] || 0;
    const cta = context['campaignbridge:ctaLabel'] || 'Read more';
    const postType = context['campaignbridge:postType'] || 'post';
    const post = useSelect(
      (select) =>
        postId
          ? select('core').getEntityRecord('postType', postType, postId)
          : null,
      [postType, postId]
    );
    const link = post?.link || '';
    return (
      <p {...useBlockProps()}>
        <a
          href={link}
          style={{
            display: 'inline-block',
            background: '#111',
            color: '#fff',
            textDecoration: 'none',
            padding: '10px 16px',
            borderRadius: 4,
          }}
        >
          {cta}
        </a>
      </p>
    );
  },
});
