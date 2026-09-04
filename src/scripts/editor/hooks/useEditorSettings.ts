import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useState } from '@wordpress/element';
import type { EmailEditorSettings } from '../types';

const EDITOR_SETTINGS_PATH = '/campaignbridge/v1/editor-settings';
const settingsCache = new Map<string, Promise<EmailEditorSettings>>();

function getSettings(postType: string, postId: number) {
  const key = `${postType}:${postId}`;
  const cached = settingsCache.get(key);

  if (cached) {
    return cached;
  }

  const request = apiFetch<EmailEditorSettings>({
    path: `${EDITOR_SETTINGS_PATH}?post_type=${encodeURIComponent(postType)}&post_id=${postId}`,
  }).catch(error => {
    settingsCache.delete(key);
    throw error;
  });

  settingsCache.set(key, request);
  return request;
}

/**
 * Fetch the core block-editor settings for the current template context.
 */
export function useEditorSettings(postType: string, postId: number) {
  const [settings, setSettings] = useState<EmailEditorSettings>({});
  const [error, setError] = useState<Error | null>(null);
  const [loading, setLoading] = useState(true);
  const cacheKey = `${postType}:${postId}`;

  useEffect(() => {
    let active = true;

    setLoading(true);
    setError(null);

    void getSettings(postType, postId)
      .then(response => {
        if (active) {
          setSettings(response);
        }
      })
      .catch(reason => {
        if (active) {
          setError(
            reason instanceof Error
              ? reason
              : new Error('Unable to load editor settings.')
          );
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [postId, postType]);

  const clearCache = useCallback(() => {
    settingsCache.delete(cacheKey);
  }, [cacheKey]);

  return { settings, error, loading, clearCache };
}
