import { BlockInstance, parse } from "@wordpress/blocks";
import { useEffect, useState } from "@wordpress/element";
import { getPostRaw } from "../services/api";

interface PostData {
  content?: {
    raw?: string;
  };
  [key: string]: unknown;
}

interface UseEditorDataResult {
  ready: boolean;
  blocks: BlockInstance[];
  error: Error | null;
  loading: boolean;
  setBlocks: (blocks: BlockInstance[]) => void;
}

/**
 * Custom hook for loading and managing editor data (post content, blocks)
 */
export function useEditorData(
  postId: number | null,
  postType: string = "post",
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

        const parsedBlocks = parse((post as PostData)?.content?.raw || "");
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
