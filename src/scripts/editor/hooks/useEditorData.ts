import { BlockInstance, parse } from '@wordpress/blocks';
import { useEffect, useState } from '@wordpress/element';
import { getPostRaw } from '../services/api';

/**
 * Represents raw post data returned from the WordPress REST API.
 *
 * @interface PostData
 * @property {Object} [content] - Post content data
 * @property {string} [content.raw] - Raw post content
 * @property {unknown} [key] - Additional post properties
 */
interface PostData {
  content?: {
    raw?: string;
  };
  [key: string]: unknown;
}

/**
 * Return type for the useEditorData hook.
 *
 * @interface UseEditorDataResult
 * @property {boolean} ready - Whether the editor data has been loaded and parsed
 * @property {BlockInstance[]} blocks - Array of parsed WordPress blocks
 * @property {Error | null} error - Error object if loading failed, null otherwise
 * @property {boolean} loading - Whether data is currently being loaded
 * @property {(blocks: BlockInstance[]) => void} setBlocks - Function to manually set blocks
 */
interface UseEditorDataResult {
  ready: boolean;
  blocks: BlockInstance[];
  error: Error | null;
  loading: boolean;
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  setBlocks: (blocks: BlockInstance[]) => void;
}

/**
 * Custom hook for loading and managing editor data (post content, blocks).
 *
 * Loads post content from the WordPress REST API, parses it into WordPress blocks,
 * and provides loading states and error handling.
 *
 * @param {number | null} postId - The post ID to load, or null for no data
 * @param {string} [postType="post"] - The post type to load data for
 * @returns {UseEditorDataResult} Object containing loading state, blocks, and control functions
 */
export function useEditorData(
  postId: number | null,
  postType: string = 'post'
): UseEditorDataResult {
  const [ready, setReady] = useState<boolean>(false);
  const [blocks, setBlocks] = useState<BlockInstance[]>([]);
  const [error, setError] = useState<Error | null>(null);
  const [loading, setLoading] = useState<boolean>(false);

  useEffect(() => {
    let isAlive = true;

    if (!postId) {
      setReady(true);
      return;
    }

    setLoading(true);
    setError(null);

    (async () => {
      try {
        const post = await getPostRaw(postId);

        if (!isAlive) return;

        const parsedBlocks = parse((post as PostData)?.content?.raw || '');
        setBlocks(parsedBlocks);
        setReady(true);
      } catch (err) {
        if (!isAlive) return;
        setError(err);
        console.error('useEditorData: Failed to load post data:', err);
      } finally {
        if (isAlive) {
          setLoading(false);
        }
      }
    })();

    return () => {
      isAlive = false;
    };
  }, [postId, postType]);

  return {
    ready,
    blocks,
    error,
    loading,
    setBlocks,
  };
}
