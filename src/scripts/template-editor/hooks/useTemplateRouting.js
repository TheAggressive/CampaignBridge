import { useCallback, useMemo } from "@wordpress/element";
import { getParam, setParamAndReload } from "../utils/url";

/**
 * useTemplateRouting
 *
 * Centralizes reading and updating the current template selection via the
 * `post_id` URL parameter. Returns the current template id and a selector
 * to navigate to another template (updates URL and reloads).
 *
 * @returns {{ currentId: number|null, selectTemplate: (id:number)=>void }}
 */
export function useTemplateRouting() {
  const currentId = useMemo(() => {
    const raw = getParam("post_id");
    return raw ? Number(raw) : null;
  }, []);

  const selectTemplate = useCallback((id) => {
    if (id) setParamAndReload("post_id", id);
  }, []);

  return { currentId, selectTemplate };
}
