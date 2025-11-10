import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { decodeEntities } from '@wordpress/html-entities';

export default function Edit({ context }) {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const post = useSelect(
    select =>
      postId
        ? (select('core') as any).getEntityRecord('postType', postType, postId)
        : null,
    [postType, postId]
  );
  const title = decodeEntities(
    (post && post.title && post.title.rendered) || ''
  );
  return (
    <h3 {...useBlockProps()} style={{ margin: '12px 0 8px', fontSize: 18 }}>
      {title || 'Post title'}
    </h3>
  );
}
