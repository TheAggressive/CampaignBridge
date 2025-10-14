import { useCallback, useEffect, useMemo } from "@wordpress/element";
import { getParam, setParamAndReload } from "../utils/url";

// Template routing-specific constants (exported for reuse if needed)
export const ROUTING_CONSTANTS = {
  URL_PARAMS: {
    TEMPLATE_ID: "post_id",
  },
};

/**
 * @typedef {Object} TemplateRoutingState
 * @property {number|null} currentId - Currently selected template ID from URL parameter
 * @property {(id: number) => void} selectTemplate - Function to select a new template and reload page
 * @property {boolean} isValidId - Whether currentId is a valid template ID
 */

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
 * - **Type Safety**: JSDoc typedefs for better IDE support and documentation
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
export function useTemplateRouting() {
  // Parse current template ID from URL with enhanced error handling
  const currentId = useMemo(() => {
    try {
      const raw = getParam(ROUTING_CONSTANTS.URL_PARAMS.TEMPLATE_ID);
      if (!raw) return null;

      const parsed = Number(raw);
      if (isNaN(parsed) || parsed <= 0) {
        if ((globalThis as any).process?.env?.NODE_ENV === "development") {
          console.warn(
            `useTemplateRouting: Invalid ${ROUTING_CONSTANTS.URL_PARAMS.TEMPLATE_ID} parameter: ${raw}`,
          );
        }
        return null;
      }

      return parsed;
    } catch (error) {
      if ((globalThis as any).process?.env?.NODE_ENV === "development") {
        console.error("useTemplateRouting: Error parsing template ID:", error);
      }
      return null;
    }
  }, []);

  // Computed property for ID validity
  const isValidId = useMemo(() => {
    return currentId !== null && Number.isInteger(currentId) && currentId > 0;
  }, [currentId]);

  // Enhanced template selection with validation
  const selectTemplate = useCallback((id) => {
    try {
      if (!id) {
        if ((globalThis as any).process?.env?.NODE_ENV === "development") {
          console.warn(
            "useTemplateRouting: Attempted to select invalid template ID:",
            id,
          );
        }
        return;
      }

      const numericId = Number(id);
      if (isNaN(numericId) || numericId <= 0 || !Number.isInteger(numericId)) {
        if ((globalThis as any).process?.env?.NODE_ENV === "development") {
          console.warn(`useTemplateRouting: Invalid template ID: ${id}`);
        }
        return;
      }

      setParamAndReload(ROUTING_CONSTANTS.URL_PARAMS.TEMPLATE_ID, numericId);
    } catch (error) {
      if ((globalThis as any).process?.env?.NODE_ENV === "development") {
        console.error("useTemplateRouting: Error selecting template:", error);
      }
    }
  }, []);

  // Handle browser back/forward navigation
  useEffect(() => {
    const handleUrlChange = () => {
      try {
        // Force page reload when URL changes via browser navigation
        if ((globalThis as any).process?.env?.NODE_ENV === "development") {
          console.log(
            "useTemplateRouting: URL changed via browser navigation, reloading...",
          );
        }
        window.location.reload();
      } catch (error) {
        if ((globalThis as any).process?.env?.NODE_ENV === "development") {
          console.error(
            "useTemplateRouting: Error handling URL change:",
            error,
          );
        }
      }
    };

    // Listen for browser navigation events
    window.addEventListener("popstate", handleUrlChange);

    // Cleanup event listener on unmount
    return () => {
      window.removeEventListener("popstate", handleUrlChange);
    };
  }, []);

  return {
    currentId,
    selectTemplate,
    isValidId,
  };
}
