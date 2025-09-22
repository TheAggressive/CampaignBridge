import { __ } from "@wordpress/i18n";
import { drawerRight } from "@wordpress/icons";
import {
  SIDEBAR_CONSTANTS,
  useSidebarState,
} from "../../hooks/useSidebarState";
import { ToggleButton } from "./ToggleButton";
import { KeyboardShortcuts } from "@wordpress/components";

/**
 * Primary Sidebar Toggle Button Component
 *
 * A toggle button component for the primary sidebar (block inspector).
 * Uses the centralized useSidebarState hook for consistent state management.
 *
 * Features:
 * - Uses centralized sidebar state management
 * - Built-in keyboard shortcut (Ctrl+Shift+,)
 * - Accessible with ARIA attributes and tooltips
 * - Consistent styling with active state indicators
 * - Integrates with WordPress interface store for close button support
 *
 * State Management:
 * - Uses the useSidebarState hook for consistent behavior
 * - Integrates with ComplementaryArea close button interactions
 * - Provides instant UI feedback and state restoration
 * - Reuses proven state management logic
 *
 * @returns {JSX.Element} The primary sidebar toggle button
 *
 * @example
 * ```jsx
 * <PrimarySidebarToggle />
 * ```
 */
export function PrimarySidebarToggle() {
  const { isPrimaryOpen, togglePrimary } = useSidebarState(
    SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
    SIDEBAR_CONSTANTS.SCOPES.SECONDARY,
  );

  return (
    <>
      <KeyboardShortcuts
        bindGlobal
        shortcuts={{ "ctrl+shift+,": togglePrimary }}
      />
      <ToggleButton
        className="cb-editor__toggle--primary"
        isActive={isPrimaryOpen}
        onClick={togglePrimary}
        shortcut="ctrl+shift+,"
        label={__("Sidebar Ctrl+Shift+,", "campaignbridge")}
        icon={drawerRight}
      />
    </>
  );
}
