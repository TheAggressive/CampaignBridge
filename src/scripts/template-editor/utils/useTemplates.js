import { useEffect, useMemo, useState } from "@wordpress/element";
import { listTemplates } from "../services/api";

/**
 * useTemplates
 *
 * Fetches and manages the list of available templates. Provides loading and
 * error state and a refresh() function. Draft filtering is handled at the API
 * level; includeDrafts is reserved for future use.
 *
 * @param {Object}   [options]
 * @param {boolean}  [options.includeDrafts=true] Reserved; API currently fetches published only
 * @param {Function} [options.onError] Optional error callback(message)
 * @returns {{ items:Array, loading:boolean, error:string, refresh:Function }}
 */
export function useTemplates(options = {}) {
  const { includeDrafts = true, onError } = options;
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const fetchList = async (signal) => {
    try {
      setLoading(true);
      const posts = await listTemplates();
      if (signal?.aborted) return;
      setItems(Array.isArray(posts) ? posts : []);
      setError("");
    } catch (e) {
      if (signal?.aborted) return;
      const msg = e?.message || "Failed to load templates.";
      setError(msg);
      if (typeof onError === "function") onError(msg);
    } finally {
      if (!signal?.aborted) setLoading(false);
    }
  };

  useEffect(() => {
    const controller =
      typeof AbortController !== "undefined" ? new AbortController() : null;
    fetchList(controller?.signal);
    return () => controller?.abort?.();
    // includeDrafts currently handled at API level via status param
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [includeDrafts]);

  const refresh = () => fetchList();

  const optionsMemo = useMemo(
    () => ({ items, loading, error }),
    [items, loading, error],
  );

  return { ...optionsMemo, refresh };
}
