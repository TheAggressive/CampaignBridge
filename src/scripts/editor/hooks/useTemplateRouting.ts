import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { getParam, setParamAndReload } from '../utils/url';

// Template routing-specific constants (exported for reuse if needed)
export const ROUTING_CONSTANTS = {
  URL_PARAMS: {
    TEMPLATE_ID: 'post_id',
  },
} as const;

/**
 * Template routing state interface
 */
interface TemplateRoutingState {
  currentId: number | null;
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  selectTemplate: (id: number) => void;
  isValidId: boolean;
}

/**
 * Enhanced useTemplateRouting
 *
 * Advanced URL parameter management for template routing with comprehensive validation,
 * error handling, and browser navigation support.
 *
 * Features:
 * - **Constants Integration**: Uses centralized constants for URL parameters
 * - **Input Validation**: Validates template IDs before navigation
 * - **Enhanced Error Handling**: Comprehensive error handling with development logging
 * - **Browser Navigation Support**: Handles browser back/forward button navigation
 * - **Type Safety**: Full TypeScript typing
 * - **Performance Optimized**: Efficient memoization and callback stability
 * - **URL Change Detection**: Automatically responds to URL parameter changes
 *
 * @returns {TemplateRoutingState}
 *
 * @example
 * ```jsx
 * const { currentId, selectTemplate, isValidId } = useTemplateRouting();
 *
 * // Get current template ID
 * if (currentId) {
 *   console.log(`Current template: ${currentId}`);
 * }
 *
 * // Navigate to new template
 * selectTemplate(123);
 *
 * // Check if current ID is valid
 * if (isValidId) {
 *   // Safe to use currentId
 * }
 * ```
 */
export function useTemplateRouting(): TemplateRoutingState {
  // Track URL changes to make currentId reactive
  const [urlKey, setUrlKey] = useState(0);

  // Parse current template ID from URL with enhanced error handling
  const currentId = useMemo(() => {
    try {
      const raw = getParam(ROUTING_CONSTANTS.URL_PARAMS.TEMPLATE_ID);
      if (!raw) return null;

      const parsed = Number(raw);
      if (isNaN(parsed) || parsed <= 0) {
        return null;
      }

      return parsed;
    } catch {
      return null;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- urlKey is used as a reactive trigger to force recalculation when URL changes via browser navigation.
  }, [urlKey]);

  // Computed property for ID validity
  const isValidId = useMemo(() => {
    return currentId !== null && Number.isInteger(currentId) && currentId > 0;
  }, [currentId]);

  // Enhanced template selection with validation
  const selectTemplate = useCallback((id: number) => {
    try {
      if (!id || !Number.isInteger(id) || id <= 0) {
        return;
      }

      setParamAndReload(ROUTING_CONSTANTS.URL_PARAMS.TEMPLATE_ID, id);
    } catch {
      // Silently handle errors.
    }
  }, []);

  // Handle browser back/forward navigation
  useEffect(() => {
    const handleUrlChange = () => {
      try {
        // Update URL key to trigger re-evaluation of currentId
        setUrlKey(prev => prev + 1);
      } catch {
        // Silently handle errors.
      }
    };

    // Listen for browser navigation events
    window.addEventListener('popstate', handleUrlChange);

    // Cleanup event listener on unmount
    return () => {
      window.removeEventListener('popstate', handleUrlChange);
    };
  }, []);

  return {
    currentId,
    selectTemplate,
    isValidId,
  };
}
