// ========== WORDPRESS IMPORTS ==========
import { Button, Icon } from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import {
  drawerRight,
  fullscreen as fullscreenIcon,
  listView,
} from "@wordpress/icons";

// ========== CUSTOM HOOKS ==========
import { useKeyboardShortcuts } from "../hooks/useKeyboardShortcuts";
import { SIDEBAR_CONSTANTS, useSidebarState } from "../hooks/useSidebarState";

// ========== LOCAL COMPONENTS ==========
import TemplateToolbar from "./TemplateToolbar";

/* Shared scopes for this screen - independent sidebars */
const SCOPE_PRIMARY = SIDEBAR_CONSTANTS.SCOPES.PRIMARY;
const SCOPE_SECONDARY = SIDEBAR_CONSTANTS.SCOPES.SECONDARY;

/* Keyboard shortcuts */
const SHORTCUTS = {
  PRIMARY_SIDEBAR: "ctrl+shift+,",
  SECONDARY_SIDEBAR: "shift+alt+o",
  FULLSCREEN: "ctrl+shift+alt+f",
};

/* CSS classes */
const CLASSES = {
  TOGGLE: "cb-editor__toggle",
  TOGGLE_PRIMARY: "cb-editor__toggle--primary",
  TOGGLE_SECONDARY: "cb-editor__toggle--secondary",
  TOGGLE_ACTIVE: "is-active",
  FULLSCREEN_TOGGLE: "cb-fullscreen-toggle",
  HEADER: "cb-editor__header",
  HEADER_LEFT: "cb-editor__header-left",
  HEADER_TITLE: "cb-editor__title",
  HEADER_ACTIONS: "cb-editor__header-actions",
};

// ========== COMPONENTS ==========

/**
 * Reusable Toggle Button Component
 *
 * A specialized button component for toggle controls with consistent styling,
 * accessibility features, and keyboard shortcut support.
 *
 * @param {Object} props - Component props
 * @param {string} props.className - Additional CSS classes
 * @param {boolean} props.isActive - Whether the toggle is currently active
 * @param {Function} props.onClick - Click handler function
 * @param {string} props.shortcut - Keyboard shortcut string
 * @param {string} props.label - Accessible label for the button
 * @param {React.ReactNode} props.children - Button content (typically an Icon)
 * @param {string} [props.variant="tertiary"] - Button variant
 * @returns {JSX.Element} The toggle button component
 */
function ToggleButton({
  className = "",
  isActive,
  onClick,
  shortcut,
  label,
  children,
  variant = "tertiary",
}) {
  const baseClass = `${CLASSES.TOGGLE} ${className} ${
    isActive ? CLASSES.TOGGLE_ACTIVE : ""
  }`;

  return (
    <Button
      className={baseClass}
      onClick={onClick}
      aria-pressed={isActive}
      aria-keyshortcuts={shortcut}
      label={label}
      showTooltip
      variant={variant}
    >
      {children}
    </Button>
  );
}

/**
 * Header Component
 *
 * Main header component for the CampaignBridge template editor providing
 * template selection, sidebar toggles with WordPress native keyboard shortcuts, and fullscreen functionality.
 * Contains the template selection toolbar and control buttons.
 * This component is designed to work with WordPress InterfaceSkeleton.
 *
 * Features:
 * - Template selection dropdown with search and creation
 * - Primary sidebar toggle (Ctrl+Shift+,)
 * - Secondary sidebar toggle (Shift+Alt+O)
 * - Fullscreen mode toggle (Ctrl+Shift+Alt+F)
 * - WordPress preference persistence for all states
 * - Accessibility support with ARIA attributes and keyboard shortcuts
 *
 * Keyboard Shortcuts (WordPress Native):
 * - Primary Sidebar: Ctrl+Shift+, (comma)
 * - Secondary Sidebar: Shift+Alt+O
 * - Fullscreen: Ctrl+Shift+Alt+F
 *
 * @param {Object} props - Component props
 * @param {Array} props.list - Array of available templates for the dropdown
 * @param {number|null} props.currentId - ID of the currently selected template
 * @param {boolean} props.loading - Whether templates are currently loading
 * @param {function} props.onSelect - Callback fired when a template is selected
 * @param {function} props.onNew - Callback fired when creating a new template
 * @returns {JSX.Element} The editor header with toolbar and controls
 *
 * @example
 * ```jsx
 * <Header
 *   list={templates}
 *   currentId={1}
 *   loading={false}
 *   onSelect={handleSelect}
 *   onNew={handleNew}
 *   saveStatus="saved"
 * />
 * ```
 */
export default function Header({ list, currentId, loading, onSelect, onNew }) {
  // ========== STATE MANAGEMENT ==========

  const isFullscreen = useSelect(
    (select) =>
      select("core/preferences").get(
        SIDEBAR_CONSTANTS.PREFERENCES.FULLSCREEN_MODE,
        false,
      ),
    [],
  );

  const { toggle: togglePreference } = useDispatch("core/preferences");

  const toggleFullscreen = () => {
    togglePreference(SIDEBAR_CONSTANTS.PREFERENCES.FULLSCREEN_MODE);
  };

  // ========== SIDEBAR STATE ==========

  /**
   * Use custom hook for sidebar state management.
   * This encapsulates all the complex state logic and provides clean APIs.
   */
  const { isPrimaryOpen, isSecondaryOpen, togglePrimary, toggleSecondary } =
    useSidebarState(SCOPE_PRIMARY, SCOPE_SECONDARY);

  // ========== KEYBOARD SHORTCUTS ==========

  // Register WordPress native keyboard shortcuts
  useKeyboardShortcuts({
    fullscreen: toggleFullscreen,
    primarySidebar: togglePrimary,
    secondarySidebar: toggleSecondary,
  });

  return (
    <div className={CLASSES.HEADER}>
      <div className={CLASSES.HEADER_LEFT}>
        <h1 className={CLASSES.HEADER_TITLE}>Template Editor</h1>
        <TemplateToolbar
          list={list}
          currentId={currentId}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
        />
      </div>

      <div className={CLASSES.HEADER_ACTIONS}>
        <ToggleButton
          className={CLASSES.TOGGLE_SECONDARY}
          isActive={isSecondaryOpen}
          onClick={toggleSecondary}
          shortcut={SHORTCUTS.SECONDARY_SIDEBAR}
          label={__("List View Shift+Alt+O", "campaignbridge")}
        >
          <Icon icon={listView} size={24} />
        </ToggleButton>

        <ToggleButton
          className={CLASSES.TOGGLE_PRIMARY}
          isActive={isPrimaryOpen}
          onClick={togglePrimary}
          shortcut={SHORTCUTS.PRIMARY_SIDEBAR}
          label={__("Sidebar Ctrl+Shift+,", "campaignbridge")}
        >
          <Icon icon={drawerRight} size={24} />
        </ToggleButton>

        <ToggleButton
          className={CLASSES.FULLSCREEN_TOGGLE}
          isActive={isFullscreen}
          onClick={toggleFullscreen}
          shortcut={SHORTCUTS.FULLSCREEN}
          label={
            isFullscreen
              ? __("Exit Fullscreen Ctrl+Shift+Alt+F", "campaignbridge")
              : __("Enter Fullscreen Ctrl+Shift+Alt+F", "campaignbridge")
          }
        >
          <Icon icon={fullscreenIcon} size={24} />
        </ToggleButton>
      </div>
    </div>
  );
}
