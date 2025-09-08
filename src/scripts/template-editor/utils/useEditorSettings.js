import apiFetch from "@wordpress/api-fetch";
import { useEffect, useState } from "@wordpress/element";

/**
 * Custom hook to fetch block editor settings from the WordPress REST API.
 *
 * @param {string} postType - The post type to get editor settings for (default: 'post')
 * @return {Object} Object containing { settings, error, loading }
 */
export function useEditorSettings(postType = "post") {
  const [settings, setSettings] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;

    const fetchSettings = async () => {
      try {
        setLoading(true);
        setError(null);

        const response = await apiFetch({
          path: `/campaignbridge/v1/editor-settings?post_type=${encodeURIComponent(postType)}`,
        });

        if (isMounted) {
          setSettings(response);
        }
      } catch (err) {
        if (isMounted) {
          setError(err);
          console.error("Failed to fetch editor settings:", err);
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    };

    fetchSettings();

    // Cleanup function to prevent state updates on unmounted component
    return () => {
      isMounted = false;
    };
  }, [postType]);

  return { settings, error, loading };
}
