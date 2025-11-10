import { useMemo, useRef } from '@wordpress/element';

// AutoSave-specific constants (exported for reuse if needed)
export const AUTOSAVE_CONSTANTS = {
  DEFAULT_DEBOUNCE_MS: 2000,
  MIN_DEBOUNCE_MS: 100,
  MAX_DEBOUNCE_MS: 10000,
  NOTIFICATION_THROTTLE: 8000,
  SAVE_STATUS: {
    SAVED: 'saved',
    SAVING: 'saving',
    ERROR: 'error',
  },
};

/**
 * useAutoSave
 *
 * Advanced debounced, abortable autosave utility with comprehensive control methods.
 *
 * Features:
 * - **Request Coalescing**: Merges rapid calls within animation frames via `save.schedule()`
 * - **Configurable Debouncing**: Customizable delay with validation
 * - **Request Cancellation**: Automatically aborts previous requests when new ones start
 * - **Manual Control**: Exposes `cancel()`, `flush()`, and `schedule()` methods
 * - **Error Handling**: Graceful error handling with proper cleanup
 * - **Cross-browser Compatible**: Works without AbortController if not available
 *
 * @param {(next:any, ctx?:{signal?:AbortSignal})=>Promise<any>} performSave Async save function
 * @param {number} [delayMs] Debounce delay in ms (uses 2500ms default, range: 100-10000ms)
 * @returns {Object} AutoSave API with methods and utilities
 *
 * @example
 * ```jsx
 * // With custom delay
 * const save = useAutoSave(performSave, 2500);
 *
 * // With default delay (2500ms)
 * const save = useAutoSave(performSave);
 *
 * // Immediate save
 * save(data);
 *
 * // Schedule coalesced save (merges rapid calls)
 * save.schedule(data);
 *
 * // Cancel all pending saves
 * save.cancel();
 *
 * // Force flush pending save
 * save.flush();
 * ```
 */
function validateDelayMs(delayMs: number | undefined | null): number {
  const { MIN_DEBOUNCE_MS, MAX_DEBOUNCE_MS, DEFAULT_DEBOUNCE_MS } =
    AUTOSAVE_CONSTANTS;

  // Handle undefined/null values explicitly
  if (delayMs == null) {
    return DEFAULT_DEBOUNCE_MS;
  }

  if (
    typeof delayMs !== 'number' ||
    delayMs < MIN_DEBOUNCE_MS ||
    delayMs > MAX_DEBOUNCE_MS
  ) {
    console.warn(
      `useAutoSave: delayMs ${delayMs}ms is invalid, using default ${DEFAULT_DEBOUNCE_MS}ms`
    );
    return DEFAULT_DEBOUNCE_MS;
  }
  return delayMs;
}

/**
 * Creates a debounced save function with proper cleanup and error handling
 */
function createDebouncedSave(
  debounceTimerRef: { current: ReturnType<typeof setTimeout> | null },
  abortRef: { current: AbortController | null },
  // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
  performSave: (next: any, ctx?: { signal?: AbortSignal }) => Promise<any>,
  delayMs: number
) {
  return (next: any) => {
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    debounceTimerRef.current = setTimeout(() => {
      executeSave(abortRef, performSave, next);
    }, delayMs);
  };
}

/**
 * Creates a force save function that bypasses debouncing
 */
function createForceSave(
  debounceTimerRef: { current: ReturnType<typeof setTimeout> | null },
  abortRef: { current: AbortController | null },
  // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
  performSave: (next: any, ctx?: { signal?: AbortSignal }) => Promise<any>
) {
  return (next: any) => {
    // Cancel any pending debounced save
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
      debounceTimerRef.current = null;
    }

    return executeSave(abortRef, performSave, next);
  };
}

/**
 * Executes the save operation with proper error handling and cleanup
 */
async function executeSave(
  abortRef: { current: AbortController | null },
  // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
  performSave: (next: any, ctx?: { signal?: AbortSignal }) => Promise<any>,
  next: any
) {
  try {
    // Abort any in-flight save before starting a new one
    if (abortRef.current) {
      abortRef.current.abort();
    }

    abortRef.current = createAbortController();
    const signal = abortRef.current?.signal;

    const result = await performSave(next, { signal });
    return result;
  } catch (error) {
    if ((error as Error).name !== 'AbortError') {
      console.error('useAutoSave: Save operation failed:', error);
    }
    throw error;
  }
}

/**
 * Creates an AbortController with cross-browser compatibility
 */
function createAbortController(): AbortController | null {
  if (typeof AbortController === 'undefined') {
    return null;
  }
  return new AbortController();
}

/**
 * Creates the schedule method for frame-coalesced updates
 */
function createScheduleMethod(
  latestRef: { current: any },
  rafIdRef: { current: number },
  // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
  saveFn: (next: any, force?: boolean) => void
) {
  return (next: any) => {
    latestRef.current = next;
    if (rafIdRef.current) return; // Already scheduled

    rafIdRef.current = requestAnimationFrame(() => {
      rafIdRef.current = 0;
      saveFn(latestRef.current);
    });
  };
}

/**
 * Creates the cancel method to stop all pending operations
 */
function createCancelMethod(
  debounceTimerRef: { current: ReturnType<typeof setTimeout> | null },
  rafIdRef: { current: number },
  abortRef: { current: AbortController | null }
) {
  return () => {
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
}

/**
 * Creates the flush method to force pending saves
 */
function createFlushMethod(
  debounceTimerRef: { current: ReturnType<typeof setTimeout> | null },
  rafIdRef: { current: number },
  latestRef: { current: any },
  abortRef: { current: AbortController | null },
  // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
  performSave: (next: any, ctx?: { signal?: AbortSignal }) => Promise<any>
) {
  return () => {
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
      abortRef.current = createAbortController();
      const signal = abortRef.current?.signal;
      return performSave(latestRef.current, { signal });
    }

    return Promise.resolve();
  };
}

/**
 * AutoSave function type with attached helper methods
 */
interface AutoSaveFunction {
  // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
  (next: any, force?: boolean): void | Promise<any>;
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  schedule: (next: any) => void;
  cancel: () => void;
  flush: () => Promise<any>;
}

/**
 * Main useAutoSave hook - now using extracted helper functions
 */
export function useAutoSave(
  // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
  performSave: (next: any, ctx?: { signal?: AbortSignal }) => Promise<any>,
  delayMsInput: number | undefined = undefined
): AutoSaveFunction {
  // Validate and normalize delay parameter
  const delayMs = validateDelayMs(delayMsInput);

  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const rafIdRef = useRef<number>(0);
  const latestRef = useRef<any>(null);
  const abortRef = useRef<AbortController | null>(null);

  return useMemo(() => {
    // Create all the helper functions with proper error handling
    const debounced = createDebouncedSave(
      debounceTimerRef,
      abortRef,
      performSave,
      delayMs
    );
    const forceSave = createForceSave(debounceTimerRef, abortRef, performSave);

    // Main save function with force option
    const saveFn = ((next: any, force = false) => {
      if (force) {
        return forceSave(next);
      }
      debounced(next);
    }) as AutoSaveFunction;

    // Attach helper methods to the save function
    saveFn.schedule = createScheduleMethod(latestRef, rafIdRef, saveFn);
    saveFn.cancel = createCancelMethod(debounceTimerRef, rafIdRef, abortRef);
    saveFn.flush = createFlushMethod(
      debounceTimerRef,
      rafIdRef,
      latestRef,
      abortRef,
      performSave
    );

    return saveFn;
  }, [performSave, delayMs]);
}
