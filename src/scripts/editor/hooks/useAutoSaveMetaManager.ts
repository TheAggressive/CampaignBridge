import { useEntityProp } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useMemo, useRef, useState } from '@wordpress/element';
import { AUTOSAVE_CONSTANTS, useAutoSave } from './useAutoSave';

/**
 * Auto-save manager for post meta.
 * - Updates editor state via setMeta (instant UI)
 * - Debounced persist via core-data saveEntityRecord (no dependency on core/editor context)
 * - Throttled success notices via callbacks you pass in
 *
 * @param {Object}   opts
 * @param {string}   opts.postType          e.g. 'cb_templates'
 * @param {number}   opts.postId
 * @param {string[]} opts.keys              meta keys this manager will handle
 * @param {function} [opts.onSuccess]       (msg) => void
 * @param {function} [opts.onError]         (msg) => void
 * @param {number}   [opts.debounceMs]      defaults to AUTOSAVE_CONSTANTS.DEFAULT_DEBOUNCE_MS
 */
export function useAutoSaveMetaManager({
  postType,
  postId,
  keys = [],
  onSuccess,
  onError,
  debounceMs = AUTOSAVE_CONSTANTS.DEFAULT_DEBOUNCE_MS,
}) {
  // --- lightweight input assert (non-breaking, helps find wrong callers)
  if (!postType || !postId) {
    console.error('[useAutoSaveMetaManager] Missing postType or postId', {
      postType,
      postId,
      keys,
    });
  }

  // Bind to post meta for this specific record
  const [meta = {}, setMeta] = useEntityProp(
    'postType',
    postType,
    'meta',
    postId
  );

  // Only persist once the post-type schema is loaded
  const ready = useSelect(
    select => {
      try {
        return !!(
          select('core') as {
            // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
            getPostType?: (slug: string) => unknown;
          }
        ).getPostType?.(postType);
      } catch {
        return false;
      }
    },
    [postType]
  );

  // Save status for UI
  const [saveStatus, setSaveStatus] = useState(
    AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVED
  );
  const lastNoticeAtRef = useRef(0);

  // core-data persister (NOT core/editor)
  const { saveEntityRecord } = useDispatch('core');

  // Project to a controlled values object
  const values = useMemo(() => {
    const out = {};
    for (const k of keys) out[k] = meta?.[k] ?? '';
    return out;
  }, [meta, keys]);

  /**
   * Debounced persist:
   * 1) merge into meta (setMeta) for immediate UI
   * 2) persist via core-data saveEntityRecord('postType', postType, postId, { meta: next })
   */
  const performSave = useCallback(
    async (partial /*, { signal } = {} */) => {
      try {
        if (!postType || !postId || !ready) {
          console.warn('[useAutoSaveMetaManager] Skipping save (not ready)', {
            postType,
            postId,
            ready,
          });
          return;
        }

        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVING);

        // 1) Update editor state (keeps UI authoritative)
        const next = { ...meta };
        for (const k of Object.keys(partial)) {
          if (keys.includes(k)) next[k] = partial[k];
        }
        setMeta(next);

        // 2) Persist to REST via core-data
        await saveEntityRecord('postType', postType, {
          id: postId,
          meta: next,
        });

        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVED);

        // Throttle success notices
        const now = Date.now();
        if (
          onSuccess &&
          now - lastNoticeAtRef.current >
            AUTOSAVE_CONSTANTS.NOTIFICATION_THROTTLE
        ) {
          onSuccess('Template saved');
          lastNoticeAtRef.current = now;
        }
      } catch (err) {
        console.error('Meta save failed:', err);
        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.ERROR);
        onError?.('Failed to save changes');
        throw err;
      }
    },
    [
      meta,
      setMeta,
      saveEntityRecord,
      keys,
      onSuccess,
      onError,
      postType,
      postId,
      ready,
    ]
  );

  // Your existing debounced/abortable runner
  const save = useAutoSave(performSave, debounceMs);

  // Update helpers
  const update = useCallback(
    (key, value) => {
      if (!keys.includes(key)) return;
      if ((meta?.[key] ?? '') === value) return; // avoid churn
      setMeta({ ...meta, [key]: value }); // immediate UI
      save({ [key]: value }); // debounced persist
    },
    [meta, setMeta, keys, save]
  );

  const setMany = useCallback(
    partial => {
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
    [meta, setMeta, keys, save]
  );

  const saveNow = useCallback((partial = {}) => save(partial, true), [save]);

  return {
    save,
    saveStatus,
    values,
    update,
    setMany,
    saveNow,
  };
}
