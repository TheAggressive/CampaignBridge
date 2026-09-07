import { useEffect, useState } from '@wordpress/element';
import { fetchPosts, type PostItem } from '../../shared/posts';

/**
 * Returns a memoized plain-text excerpt preview for the editor.
 * The preview is resolved server-side so it always matches the compiled email.
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

export function useExcerptPreview({
  postId,
  postType,
  maxWords,
}: ExcerptPreviewOptions): string {
  const [post, setPost] = useState<PostItem | null>(null);

  useEffect(() => {
    if (!postId) {
      setPost(null);
      return;
    }

    let active = true;
    fetchPosts(postType, maxWords)
      .then(items => {
        if (active) {
          setPost(items.find(item => Number(item.id) === postId) ?? null);
        }
      })
      .catch(() => {
        if (active) {
          setPost(null);
        }
      });

    return () => {
      active = false;
    };
  }, [postId, postType, maxWords]);

  return post?.excerptPreview ?? '';
}
