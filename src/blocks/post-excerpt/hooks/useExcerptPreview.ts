import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Returns a memoized plain-text excerpt preview for the editor.
 * @param {Object}      params          - Hook parameters
 * @param {number|null} params.postId   - The post ID to get excerpt for
 * @param {string}      params.postType - The post type
 * @param {number}      params.maxWords - Maximum number of words to show
 * @return {string} The excerpt preview text
 */
interface ExcerptPreviewOptions {
  postId: number;
  postType: string;
  maxWords: number;
}

interface PostPreviewRecord {
  excerpt?: { rendered?: string };
  content?: { rendered?: string };
}

interface CoreSelectors {
  getEntityRecord: (
    kind: string,
    name: string,
    id: number
  ) => PostPreviewRecord | null;
}

export function useExcerptPreview({
  postId,
  postType,
  maxWords,
}: ExcerptPreviewOptions): string {
  const post = useSelect(
    s =>
      postId
        ? (s('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            postType,
            postId
          )
        : null,
    [postId, postType]
  );

  return useMemo(() => {
    const raw = post?.excerpt?.rendered || post?.content?.rendered || '';
    const text = decodeEntities(raw)
      .replace(/<[^>]*>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    const words = text.split(/\s+/).filter(Boolean);
    return words.slice(0, maxWords).join(' ');
  }, [post, maxWords]);
}
