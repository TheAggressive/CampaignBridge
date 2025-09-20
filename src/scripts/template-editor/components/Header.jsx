import { Button, Icon } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import {
  drawerRight,
  fullscreen as fullscreenIcon,
  listView,
} from "@wordpress/icons";
// Use core/interface store by key to avoid relying on direct store import
import TemplateToolbar from "./TemplateToolbar";

/* Keyboard shortcut for fullscreen */
import { useEffect } from "@wordpress/element";
import { useSidebarState } from "../hooks/useSidebarState";

/* Shared scopes for this screen - independent sidebars */
const SCOPE_PRIMARY = "campaignbridge/template-editor/primary";
const SCOPE_SECONDARY = "campaignbridge/template-editor/secondary";

/**
 * Header Component
 *
 * Main header component for the CampaignBridge template editor providing
 * template selection, save status indication, and fullscreen toggle functionality.
 * Contains the template selection toolbar, save indicator, and fullscreen button.
 * This component is designed to work with WordPress InterfaceSkeleton.
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
  const isFullscreen = useSelect(
    (select) =>
      select("core/preferences").get("core/edit-post", "fullscreenMode"),
    [],
  );

  const toggleFullscreen = () => {
    toggle("core/edit-post", "fullscreenMode");
  };

  // Keyboard shortcut: Ctrl+Shift+Alt+F toggles fullscreen (match WP)
  useEffect(() => {
    const onKeyDown = (event) => {
      const key = (event.key || "").toLowerCase();
      if (event.ctrlKey && event.shiftKey && event.altKey && key === "f") {
        event.preventDefault();
        toggleFullscreen();
      }
    };
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  /**
   * Use custom hook for sidebar state management.
   * This encapsulates all the complex state logic and provides clean APIs.
   */
  const { isPrimaryOpen, isSecondaryOpen, togglePrimary, toggleSecondary } =
    useSidebarState(SCOPE_PRIMARY, SCOPE_SECONDARY);

  return (
    <div className="cb-editor__header">
      <div className="cb-editor__header-left">
        <h1 className="cb-editor__title">Template Editor</h1>
        <TemplateToolbar
          list={list}
          currentId={currentId}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
        />
      </div>

      <div className="cb-editor__header-actions">
        <Button
          className={`cb-editor__toggle cb-editor__toggle--secondary ${
            isSecondaryOpen ? "is-active" : ""
          }`}
          onClick={toggleSecondary}
          aria-pressed={isSecondaryOpen}
          label={__("List View Shift+Alt+O", "campaignbridge")}
          showTooltip
          variant="tertiary"
        >
          <Icon icon={listView} size={24} />
        </Button>

        <Button
          className={`cb-editor__toggle cb-editor__toggle--primary ${
            isPrimaryOpen ? "is-active" : ""
          }`}
          onClick={togglePrimary}
          aria-pressed={isPrimaryOpen}
          label={__("Sidebar Ctrl+Shift+,", "campaignbridge")}
          showTooltip
          variant="tertiary"
        >
          <Icon icon={drawerRight} size={24} />
        </Button>

        <Button
          className="cb-fullscreen-toggle"
          onClick={toggleFullscreen}
          aria-keyshortcuts="Control+Shift+Alt+F"
          label={
            isFullscreen
              ? __("Exit Fullscreen Ctrl+Shift+Alt+F", "campaignbridge")
              : __("Enter Fullscreen Ctrl+Shift+Alt+F", "campaignbridge")
          }
          showTooltip={true}
        >
          <Icon icon={fullscreenIcon} size={24} />
        </Button>
      </div>
    </div>
  );
}
