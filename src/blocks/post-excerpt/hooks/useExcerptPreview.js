import { useSelect } from "@wordpress/data";
import { useMemo } from "@wordpress/element";
import { decodeEntities } from "@wordpress/html-entities";

/**
 * Returns a memoized plain-text excerpt preview (client-side only).
 */
export function useExcerptPreview({ postId, postType, maxWords, showMore }) {
  const post = useSelect(
    (s) =>
      postId ? s("core").getEntityRecord("postType", postType, postId) : null,
    [postId, postType],
  );

  return useMemo(() => {
    const raw = post?.excerpt?.rendered || post?.content?.rendered || "";
    const text = decodeEntities(raw)
      .replace(/<[^>]*>/g, " ")
      .replace(/\s+/g, " ")
      .trim();
    const words = text.split(/\s+/).filter(Boolean);
    let out = words.slice(0, maxWords).join(" ");
    if (showMore && out.endsWith(".")) out = out.slice(0, -1).trim();
    return out;
  }, [post, maxWords, showMore]);
}
