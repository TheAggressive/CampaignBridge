import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import type { EmailBlockEditProps } from '../types';

interface PostRecord {
  featured_media?: number;
}

interface MediaRecord {
  source_url?: string;
  media_details?: {
    sizes?: { full?: { source_url?: string } };
  };
}

interface CoreSelectors {
  getEntityRecord: (
    kind: string,
    name: string,
    id: number
  ) => PostRecord | MediaRecord | null;
}

export default function Edit({
  context = {},
}: EmailBlockEditProps<Record<string, never>>): JSX.Element {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const post = useSelect(
    select =>
      postId
        ? ((select('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            postType,
            postId
          ) as PostRecord | null)
        : null,
    [postType, postId]
  );
  const mediaId = (post && post.featured_media) || 0;
  const media = useSelect(
    select =>
      mediaId
        ? ((select('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            'attachment',
            mediaId
          ) as MediaRecord | null)
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
      {url ? (
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
