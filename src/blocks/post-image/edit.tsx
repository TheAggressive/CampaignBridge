import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

export default function Edit({ context = {} }) {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const show = context['campaignbridge:showImage'] !== false;
  const postType = context['campaignbridge:postType'] || 'post';
  const post = useSelect(
    select =>
      postId
        ? (select('core') as any).getEntityRecord('postType', postType, postId)
        : null,
    [postType, postId]
  );
  const mediaId = (post && post.featured_media) || 0;
  const media = useSelect(
    select =>
      mediaId
        ? (select('core') as any).getEntityRecord(
            'postType',
            'attachment',
            mediaId
          )
        : null,
    [mediaId]
  );
  const url =
    (media &&
      media.media_details &&
      media.media_details.sizes &&
      media.media_details.sizes.full &&
      media.media_details.sizes.full.source_url) ||
    (media && media.source_url) ||
    '';
  const props = useBlockProps();
  return (
    <div {...props}>
      {show && url ? (
        <img
          src={url}
          alt=''
          style={{
            display: 'block',
            width: '100%',
            height: 'auto',
            border: 0,
          }}
        />
      ) : null}
    </div>
  );
}
