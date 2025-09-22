import { useDispatch, useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import { fullscreen } from "@wordpress/icons";
import { ToggleButton } from "./ToggleButton";
import { KeyboardShortcuts } from "@wordpress/components";

/**
 * Fullscreen Toggle Button Component
 *
 * A completely self-contained component for toggling the editor fullscreen mode.
 * Handles all state management, keyboard shortcuts, and functionality internally.
 *
 * Features:
 * - Self-contained state management using WordPress preferences
 * - Built-in keyboard shortcut (Ctrl+Shift+Alt+F)
 * - Accessible with ARIA attributes and tooltips
 * - Dynamic label based on current state
 * - Consistent styling with active state indicators
 * - No external dependencies - completely independent
 *
 * State Management:
 * - Uses preference key: "core/edit-post/fullscreenMode"
 * - Persists state across page reloads
 * - Manages its own toggle logic and keyboard shortcuts
 *
 * @returns {JSX.Element} The fullscreen toggle button
 *
 * @example
 * ```jsx
 * <FullscreenToggle />
 * ```
 */
export function FullscreenToggle() {
  const PREFERENCE_KEY = "core/edit-post/fullscreenMode";

  const isActive = useSelect(
    (select) => select("core/preferences").get(PREFERENCE_KEY, false),
    [PREFERENCE_KEY],
  );

  const { toggle: togglePreference } = useDispatch("core/preferences");

  const handleToggle = () => {
    togglePreference(PREFERENCE_KEY);
  };

  const label = isActive
    ? __("Exit Fullscreen Ctrl+Shift+Alt+F", "campaignbridge")
    : __("Enter Fullscreen Ctrl+Shift+Alt+F", "campaignbridge");

  return (
    <>
      <KeyboardShortcuts
        bindGlobal
        shortcuts={{ "ctrl+shift+alt+f": handleToggle }}
      />
      <ToggleButton
        className="cb-fullscreen-toggle"
        isActive={isActive}
        onClick={handleToggle}
        shortcut="ctrl+shift+alt+f"
        label={label}
        icon={fullscreen}
      />
    </>
  );
}
