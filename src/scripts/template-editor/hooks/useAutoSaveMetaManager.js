// src/scripts/template-editor/hooks/useAutoSaveMetaManager.js
import { useEntityProp } from "@wordpress/core-data";
import { useDispatch } from "@wordpress/data";
import { useCallback, useMemo, useRef, useState } from "@wordpress/element";
import { AUTOSAVE_CONSTANTS, useAutoSave } from "./useAutoSave";

/**
 * Auto-save manager for post meta
 * - Debounced, abortable saves (via your useAutoSave)
 * - Updates editor state first (setMeta), then persists via savePost()
 * - Throttled success notices via callbacks you pass in
 *
 * @param {Object} opts
 * @param {string} opts.postType          e.g. 'cb_email_template'
 * @param {number} opts.postId
 * @param {string[]} opts.keys            meta keys this manager will handle
 * @param {function} [opts.onSuccess]     (msg) => void  // e.g. snackbar
 * @param {function} [opts.onError]       (msg) => void
 * @param {number} [opts.debounceMs]      defaults to AUTOSAVE_CONSTANTS.DEFAULT_DEBOUNCE_MS
 */
export function useAutoSaveMetaManager({
  postType,
  postId,
  keys = [],
  onSuccess,
  onError,
  debounceMs = AUTOSAVE_CONSTANTS.DEFAULT_DEBOUNCE_MS,
}) {
  // Guard against undefined postType or postId during initial render
  const [meta = {}, setMeta] = useEntityProp(
    "postType",
    postType || "post", // Fallback to default post type
    "meta",
    postId || 0, // Fallback to 0 (won't match any real post)
  );

  // Save status (optional but handy for UI)
  const [saveStatus, setSaveStatus] = useState(
    AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVED,
  );
  const lastNoticeAtRef = useRef(0);

  // Editor save dispatcher
  const { editPost } = useDispatch("core/editor");

  // Build a stable projector for controlled values
  const values = useMemo(() => {
    const out = {};
    for (const k of keys) out[k] = meta?.[k] ?? "";
    return out;
  }, [meta, keys]);

  // The concrete performSave for useAutoSave
  const performSave = useCallback(
    async (partial, { signal } = {}) => {
      try {
        // Guard: Don't save if we don't have valid post info
        if (!postType || !postId) {
          console.warn(
            "useAutoSaveMetaManager: Skipping save - missing postType or postId",
          );
          return;
        }

        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVING);

        // 1) Update editor state (so UI stays the source of truth)
        const next = { ...meta };
        for (const k of Object.keys(partial)) {
          if (keys.includes(k)) next[k] = partial[k];
        }
        setMeta(next);

        // 2) Update the post in the editor (this marks it as dirty for saving)
        editPost({ meta: next });

        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVED);

        // Throttled success toast
        const now = Date.now();
        if (
          onSuccess &&
          now - lastNoticeAtRef.current >
            AUTOSAVE_CONSTANTS.NOTIFICATION_THROTTLE
        ) {
          onSuccess("Template saved");
          lastNoticeAtRef.current = now;
        }
      } catch (err) {
        console.error("Meta save failed:", err);
        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.ERROR);
        onError?.("Failed to save changes");
        throw err;
      }
    },
    [meta, setMeta, editPost, keys, onSuccess, onError, postType, postId],
  );

  // Your existing debounced/abortable runner
  const save = useAutoSave(performSave, debounceMs);

  // Field updater that schedules a debounced save
  const update = useCallback(
    (key, value) => {
      if (!keys.includes(key)) return;
      // Update editor state immediately for snappy inputs
      setMeta({ ...meta, [key]: value });
      // Debounced persist with just the delta
      save({ [key]: value });
    },
    [meta, setMeta, keys, save],
  );

  // Batch updater (e.g., paste multiple fields)
  const setMany = useCallback(
    (partial) => {
      const next = { ...meta };
      let changed = false;
      for (const k of Object.keys(partial)) {
        if (!keys.includes(k)) continue;
        if (next[k] !== partial[k]) {
          next[k] = partial[k];
          changed = true;
        }
      }
      if (changed) {
        setMeta(next);
        save(partial);
      }
    },
    [meta, setMeta, keys, save],
  );

  // Expose an immediate save
  const saveNow = useCallback(
    (partial = {}) => save(partial, true), // your useAutoSave supports force via second arg
    [save],
  );

  return {
    save,
    saveStatus,
    values,
    update,
    setMany,
    saveNow,
  };
}
