import { useEffect } from "@wordpress/element";

/**
 * Custom hook for handling keyboard shortcuts
 *
 * Provides a clean API for registering and handling keyboard shortcuts
 * with proper event cleanup and cross-platform compatibility.
 *
 * @param {Object} shortcuts - Object containing shortcut configurations
 * @param {Function} shortcuts.fullscreen - Handler for fullscreen toggle
 * @param {Function} shortcuts.primarySidebar - Handler for primary sidebar toggle
 * @param {Function} shortcuts.secondarySidebar - Handler for secondary sidebar toggle
 * @returns {void}
 *
 * @example
 * ```javascript
 * useKeyboardShortcuts({
 *   fullscreen: toggleFullscreen,
 *   primarySidebar: togglePrimary,
 *   secondarySidebar: toggleSecondary,
 * });
 * ```
 */
export function useKeyboardShortcuts({
  fullscreen,
  primarySidebar,
  secondarySidebar,
}) {
  useEffect(() => {
    /**
     * Keyboard event handler for WordPress native shortcuts
     * @param {KeyboardEvent} event - The keyboard event
     */
    const onKeyDown = (event) => {
      const key = (event.key || "").toLowerCase();

      // Prevent default browser behavior for our shortcuts
      if (
        (event.ctrlKey && event.shiftKey && event.altKey && key === "f") ||
        (event.ctrlKey && event.shiftKey && key === ",") ||
        (event.shiftKey && event.altKey && key === "o")
      ) {
        event.preventDefault();
        event.stopPropagation();
      }

      // Ctrl+Shift+Alt+F toggles fullscreen
      if (event.ctrlKey && event.shiftKey && event.altKey && key === "f") {
        fullscreen();
        return;
      }

      // Ctrl+Shift+, toggles primary sidebar
      if (event.ctrlKey && event.shiftKey && key === ",") {
        primarySidebar();
        return;
      }

      // Shift+Alt+O toggles secondary sidebar
      if (event.shiftKey && event.altKey && key === "o") {
        secondarySidebar();
        return;
      }
    };

    // Add event listener
    document.addEventListener("keydown", onKeyDown, { capture: true });

    // Cleanup function
    return () => {
      document.removeEventListener("keydown", onKeyDown, { capture: true });
    };
  }, [fullscreen, primarySidebar, secondarySidebar]);
}
