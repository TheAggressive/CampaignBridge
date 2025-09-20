import { useMemo, useRef } from "@wordpress/element";

/**
 * useAutoSave
 *
 * Debounced, abortable autosave helper.
 * - Coalesces rapid onInput/onChange within a frame via `save.schedule(next)`.
 * - Debounces by `delayMs` before executing the save.
 * - Aborts any in-flight request when a newer save starts.
 * - Exposes `save.cancel()` and `save.flush()` to control pending work.
 *
 * @param {(next:any, ctx?:{signal?:AbortSignal})=>Promise<any>} performSave Async save function
 * @param {number} [delayMs=2500] Debounce delay in ms
 * @returns {((next:any, force?:boolean)=>Promise<any>|void) & {schedule:Function,cancel:Function,flush:Function}}
 */
const DEFAULT_DEBOUNCE_MS = 2500;

export function useAutoSave(performSave, delayMs = DEFAULT_DEBOUNCE_MS) {
  const debounceTimerRef = useRef(null);
  const rafIdRef = useRef(0);
  const latestRef = useRef(null);
  const abortRef = useRef(null);

  return useMemo(() => {
    const debounced = (next) => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
      debounceTimerRef.current = setTimeout(() => {
        // Abort any in-flight save before starting a new one
        if (abortRef.current) {
          abortRef.current.abort();
        }
        abortRef.current =
          typeof AbortController !== "undefined" ? new AbortController() : null;
        const signal = abortRef.current ? abortRef.current.signal : undefined;
        performSave(next, { signal });
      }, delayMs);
    };

    const saveFn = (next, force = false) => {
      if (force) {
        if (debounceTimerRef.current) {
          clearTimeout(debounceTimerRef.current);
          debounceTimerRef.current = null;
        }
        if (abortRef.current) {
          abortRef.current.abort();
        }
        abortRef.current =
          typeof AbortController !== "undefined" ? new AbortController() : null;
        const signal = abortRef.current ? abortRef.current.signal : undefined;
        return performSave(next, { signal });
      }
      debounced(next);
    };
    // Coalesce onInput/onChange within a frame
    saveFn.schedule = (next) => {
      latestRef.current = next;
      if (rafIdRef.current) return;
      rafIdRef.current = requestAnimationFrame(() => {
        rafIdRef.current = 0;
        saveFn(latestRef.current);
      });
    };
    // Expose cancel/flush helpers
    saveFn.cancel = () => {
      if (rafIdRef.current) {
        cancelAnimationFrame(rafIdRef.current);
        rafIdRef.current = 0;
      }
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
        debounceTimerRef.current = null;
      }
      if (abortRef.current) {
        abortRef.current.abort();
        abortRef.current = null;
      }
    };
    saveFn.flush = () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
        debounceTimerRef.current = null;
      }
      if (rafIdRef.current) {
        cancelAnimationFrame(rafIdRef.current);
        rafIdRef.current = 0;
      }
      if (latestRef.current != null) {
        if (abortRef.current) {
          abortRef.current.abort();
        }
        abortRef.current =
          typeof AbortController !== "undefined" ? new AbortController() : null;
        const signal = abortRef.current ? abortRef.current.signal : undefined;
        return performSave(latestRef.current, { signal });
      }
      return Promise.resolve();
    };
    return saveFn;
  }, [performSave, delayMs]);
}
