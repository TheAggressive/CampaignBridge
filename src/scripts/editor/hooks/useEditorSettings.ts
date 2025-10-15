import apiFetch from '@wordpress/api-fetch';
import { useEffect, useRef, useState } from '@wordpress/element';

// Editor settings-specific constants (exported for reuse if needed)
export const EDITOR_SETTINGS_CONSTANTS = {
  API_PATHS: {
    EDITOR_SETTINGS: '/campaignbridge/v1/editor-settings',
  },
};

/**
 * @typedef {Object} EditorSettings
 * @property {Object} blockEditorSettings - WordPress block editor configuration
 * @property {string[]} allowedBlocks - List of allowed block types
 * @property {Object} editorConfig - Custom editor configuration
 * @property {boolean} hasCustomSettings - Whether custom settings are available
 */

/**
 * Enhanced custom hook to fetch block editor settings with caching and retry logic.
 *
 * Features:
 * - **Caching**: Prevents redundant API calls for the same post type
 * - **Retry Logic**: Automatically retries failed requests up to 3 times
 * - **Memory Management**: Proper cleanup to prevent memory leaks
 * - **Type Safety**: Comprehensive JSDoc with TypeScript-like typedefs
 * - **Error Recovery**: Graceful error handling with exponential backoff
 *
 * @param {string} postType - The post type to get editor settings for (default: 'post')
 * @return {{ settings: EditorSettings|null, error: Error|null, loading: boolean }}
 *
 * @example
 * ```jsx
 * const { settings, error, loading } = useEditorSettings('post');
 *
 * if (loading) return <Spinner />;
 * if (error) return <ErrorMessage error={error} />;
 *
 * return <Editor settings={settings} />;
 * ```
 */
export function useEditorSettings(postType = 'post') {
  const [settings, setSettings] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);

  // Cache for settings to prevent redundant API calls
  const settingsCacheRef = useRef(new Map());

  // Retry configuration
  const MAX_RETRIES = 3;
  const RETRY_DELAY_MS = 1000;

  useEffect(() => {
    let isMounted = true;

    const fetchSettingsWithRetry = async (retryCount = 0) => {
      try {
        setLoading(true);
        setError(null);

        // Check cache first
        if (settingsCacheRef.current.has(postType)) {
          const cachedSettings = settingsCacheRef.current.get(postType);
          if (isMounted) {
            setSettings(cachedSettings);
            setLoading(false);
          }
          return;
        }

        const response = await apiFetch({
          path: `${EDITOR_SETTINGS_CONSTANTS.API_PATHS.EDITOR_SETTINGS}?post_type=${encodeURIComponent(postType)}`,
        });

        // Validate response before caching
        if (response && typeof response === 'object') {
          settingsCacheRef.current.set(postType, response);

          if (isMounted) {
            setSettings(response);
          }
        } else {
          throw new Error('Invalid response format from editor settings API');
        }
      } catch (err) {
        console.error(
          `useEditorSettings: Error on attempt ${retryCount + 1}/${MAX_RETRIES}:`,
          err
        );
        if (isMounted) {
          const isRetryableError =
            err.code !== 'rest_invalid_param' && err.code !== 'rest_forbidden';

          if (isRetryableError && retryCount < MAX_RETRIES) {
            console.warn(
              `useEditorSettings: Retrying... (attempt ${retryCount + 1}/${MAX_RETRIES}):`,
              err
            );

            // Exponential backoff for retries
            const delayMs = RETRY_DELAY_MS * Math.pow(2, retryCount);
            setTimeout(() => {
              if (isMounted) {
                fetchSettingsWithRetry(retryCount + 1);
              }
            }, delayMs);
            return;
          }

          setError(err);
          console.error(
            'Failed to fetch editor settings after all retries:',
            err
          );

          // Set fallback settings if all retries fail
          const fallbackSettings = {
            blockEditorSettings: {},
            allowedBlocks: [],
            editorConfig: {},
            hasCustomSettings: false,
          };
          setSettings(fallbackSettings);
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    };

    fetchSettingsWithRetry();

    // Cleanup function to prevent state updates on unmounted component
    return () => {
      isMounted = false;
    };
  }, [postType]);

  // Clear cache method for manual cache invalidation if needed
  const clearCache = () => {
    settingsCacheRef.current.clear();
    setSettings(null);
  };

  return {
    settings,
    error,
    loading,
    clearCache, // Expose cache clearing utility
  };
}
