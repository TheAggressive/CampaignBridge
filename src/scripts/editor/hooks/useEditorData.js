import { parse } from "@wordpress/blocks";
import { useEffect, useState } from "@wordpress/element";
import { getPostRaw } from "../services/api";

/**
 * Custom hook for loading and managing editor data (post content, blocks)
 *
 * @param {number} postId - The ID of the post/template to load
 * @param {string} postType - The post type (default: 'post')
 * @returns {Object} Editor data state and loading status
 */
export function useEditorData(postId, postType = "post") {
  const [ready, setReady] = useState(false);
  const [blocks, setBlocks] = useState([]);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

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

        const parsedBlocks = parse(post?.content?.raw || "");
        setBlocks(parsedBlocks);
        setReady(true);
      } catch (err) {
        if (!isAlive) return;
        setError(err);
        console.error("useEditorData: Failed to load post data:", err);
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
