import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from '@wordpress/element';
import { listTemplates } from '../services/api';

// Template-specific constants (exported for reuse if needed)
export const TEMPLATES_CONSTANTS = {
  CACHE_DURATION_MS: 5 * 60 * 1000, // 5 minutes
  RETRY: {
    MAX_RETRIES: 3,
    DELAY_MS: 1000,
  },
  ERROR_MESSAGES: {
    LOAD_FAILED: 'Failed to load templates.',
    INVALID_RESPONSE: 'Invalid response format: expected array',
    API_NOT_AVAILABLE: 'Templates API not available.',
  },
};

// Template-specific error messages
const TEMPLATE_ERROR_MESSAGES = TEMPLATES_CONSTANTS.ERROR_MESSAGES;

/**
 * @typedef {Object} TemplateItem
 * @property {number} id - Template ID
 * @property {string} title - Template title
 * @property {string} status - Template status (published, draft, etc.)
 * @property {string} [content] - Template content
 * @property {string} [excerpt] - Template excerpt
 */

/**
 * @typedef {Object} TemplatesState
 * @property {TemplateItem[]} items - Array of template objects
 * @property {boolean} loading - Whether templates are currently loading
 * @property {string} error - Error message if loading failed
 * @property {Function} refresh - Function to manually refresh templates
 * @property {number} lastUpdated - Timestamp of last successful fetch
 * @property {boolean} isStale - Whether cached data is stale
 */

/**
 * Enhanced useTemplates
 *
 * Advanced template management with caching, retry logic, enhanced error handling,
 * and comprehensive validation.
 *
 * Features:
 * - **Time-based Caching**: Prevents redundant API calls with configurable cache duration
 * - **Retry Logic**: Automatically retries failed requests with exponential backoff
 * - **Enhanced Error Handling**: Specific error messages for different failure types
 * - **Response Validation**: Validates API responses before caching
 * - **Constants Integration**: Uses centralized constants for all configuration
 * - **Type Safety**: JSDoc typedefs for better IDE support and documentation
 * - **Performance Optimized**: Efficient memoization and callback stability
 * - **Memory Management**: Proper cleanup to prevent memory leaks
 *
 * @param {Object} [options]
 * @param {boolean} [options.includeDrafts=true] Reserved; API currently fetches published only
 * @param {Function} [options.onError] Optional error callback(message)
 * @param {boolean} [options.disableCache=false] Disable caching for fresh data
 * @returns {TemplatesState}
 *
 * @example
 * ```jsx
 * const {
 *   items,
 *   loading,
 *   error,
 *   refresh,
 *   lastUpdated,
 *   isStale
 * } = useTemplates({
 *   onError: (message) => console.error(message)
 * });
 *
 * if (loading) return <Spinner />;
 * if (error) return <ErrorMessage error={error} />;
 *
 * return (
 *   <div>
 *     <button onClick={refresh}>Refresh</button>
 *     <TemplateList items={items} />
 *     {isStale && <span>⚠️ Data may be outdated</span>}
 *   </div>
 * );
 * ```
 */
export function useTemplates(
  options: {
    includeDrafts?: boolean;
    // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
    onError?: (message: string) => void;
    disableCache?: boolean;
  } = {}
) {
  // eslint-disable-next-line no-unused-vars -- Reserved for future use; API currently fetches published only.
  const { includeDrafts = true, onError, disableCache = false } = options;
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Simple time-based caching
  const cacheRef = useRef({ data: null, timestamp: 0 });

  // Helper function to check if cache is stale
  const isCacheStale = useCallback(() => {
    const now = Date.now();
    return (
      disableCache ||
      !cacheRef.current.data ||
      now - cacheRef.current.timestamp > TEMPLATES_CONSTANTS.CACHE_DURATION_MS
    );
  }, [disableCache]);

  // Enhanced fetch function with retry logic
  const fetchListWithRetry = useCallback(
    async (signal, retryCount = 0) => {
      try {
        setLoading(true);
        setError('');

        // Check cache first if not disabled
        if (!disableCache && !isCacheStale()) {
          setItems(cacheRef.current.data);
          setLoading(false);
          return;
        }

        const posts = await listTemplates();
        if (signal?.aborted) return;

        // Validate response
        if (!Array.isArray(posts)) {
          throw new Error(TEMPLATE_ERROR_MESSAGES.INVALID_RESPONSE);
        }

        // Update cache
        const now = Date.now();
        cacheRef.current = { data: posts, timestamp: now };

        setItems(posts);
        setError('');
      } catch (e) {
        if (signal?.aborted) return;

        // Determine error message based on error type
        let msg = TEMPLATE_ERROR_MESSAGES.LOAD_FAILED;
        if (e?.message) msg = e.message;
        if (e?.code === 'rest_no_route') {
          msg = TEMPLATE_ERROR_MESSAGES.API_NOT_AVAILABLE;
        }

        // Retry logic for retryable errors
        const isRetryable =
          e?.code !== 'rest_forbidden' && e?.code !== 'rest_invalid_param';

        if (isRetryable && retryCount < TEMPLATES_CONSTANTS.RETRY.MAX_RETRIES) {
          const delayMs =
            TEMPLATES_CONSTANTS.RETRY.DELAY_MS * Math.pow(2, retryCount);
          if ((globalThis as any).process?.env?.NODE_ENV === 'development') {
            console.warn(
              `useTemplates: Retrying templates fetch (attempt ${retryCount + 1}/${TEMPLATES_CONSTANTS.RETRY.MAX_RETRIES}):`,
              e
            );
          }

          setTimeout(() => {
            if (!signal?.aborted) {
              fetchListWithRetry(signal, retryCount + 1);
            }
          }, delayMs);
          return;
        }

        setError(msg);
        setItems([]); // Provide empty array as fallback
        if (typeof onError === 'function') onError(msg);

        if ((globalThis as any).process?.env?.NODE_ENV === 'development') {
          console.error(
            'useTemplates: Failed to load templates after all retries:',
            e
          );
        }
      } finally {
        if (!signal?.aborted) {
          setLoading(false);
        }
      }
    },
    [onError, disableCache, isCacheStale]
  );

  useEffect(() => {
    const controller =
      typeof AbortController !== 'undefined' ? new AbortController() : null;
    fetchListWithRetry(controller?.signal);
    return () => controller?.abort?.();
  }, [fetchListWithRetry]);

  // Manual refresh function
  const refresh = useCallback(() => {
    const controller =
      typeof AbortController !== 'undefined' ? new AbortController() : null;
    fetchListWithRetry(controller?.signal);
    return () => controller?.abort?.();
  }, [fetchListWithRetry]);

  // Computed properties
  const lastUpdated = useMemo(() => cacheRef.current.timestamp, []);
  const isStale = useMemo(() => isCacheStale(), [isCacheStale]);

  const stateMemo = useMemo(
    () => ({ items, loading, error, lastUpdated, isStale }),
    [items, loading, error, lastUpdated, isStale]
  );

  return {
    ...stateMemo,
    refresh,
  };
}
