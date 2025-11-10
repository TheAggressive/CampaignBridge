import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

export default function Edit({ context }) {
  const postId = context['campaignbridge:postId'] || 0;
  const cta = context['campaignbridge:ctaLabel'] || 'Read more';
  const postType = context['campaignbridge:postType'] || 'post';
  const post = useSelect(
    select =>
      postId
        ? (select('core') as any).getEntityRecord('postType', postType, postId)
        : null,
    [postType, postId]
  );
  const link = (post && post.link) || '';
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
}
