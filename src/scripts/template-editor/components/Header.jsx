import { Button, Icon } from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import {
  drawerRight,
  fullscreen as fullscreenIcon,
  listView,
} from "@wordpress/icons";
import SaveIndicator from "./SaveIndicator";
import TemplateToolbar from "./TemplateToolbar";

/* NEW: use Preferences store for persistent UI state */
import { useEffect } from "@wordpress/element";
import { store as preferencesStore } from "@wordpress/preferences";

/* Shared namespace & keys for this screen */
const NS = "campaignbridge/template-editor";
const K_PRIMARY = "primaryOpen";
const K_SECONDARY = "secondaryOpen";

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
 * @param {string} props.saveStatus - Current save status ('saved', 'saving', 'autosaving', 'error')
 * @param {object} [props.primaryToggleRef] - Ref to primary sidebar toggle button
 * @param {object} [props.secondaryToggleRef] - Ref to secondary sidebar toggle button
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
export default function Header({
  list,
  currentId,
  loading,
  onSelect,
  onNew,
  saveStatus,
  /* NOTE: these three props are no longer required because we read/write from Preferences directly.
	   Keeping them in the signature comment to preserve your original docs. */
  // enableComplementaryArea,
  // disableComplementaryArea,
  // activeComplementaryArea,
  primaryToggleRef,
  secondaryToggleRef,
}) {
  const isFullscreen = useSelect(
    (select) =>
      select("core/preferences").get("core/edit-post", "fullscreenMode"),
    [],
  );

  const { toggle } = useDispatch("core/preferences");

  /* NEW: read current open state from Preferences (defaults: primary true, secondary false) */
  const primaryOpen = useSelect((select) => {
    const v = select(preferencesStore).get(NS, K_PRIMARY);
    return typeof v === "boolean" ? v : true;
  }, []);
  const secondaryOpen = useSelect((select) => {
    const v = select(preferencesStore).get(NS, K_SECONDARY);
    return typeof v === "boolean" ? v : false;
  }, []);

  /* NEW: setters */
  const { set } = useDispatch(preferencesStore);

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

  /* UPDATED: toggle via Preferences store */
  const toggleSidebar = () => set(NS, K_PRIMARY, !primaryOpen);
  const toggleSecondarySidebar = () => set(NS, K_SECONDARY, !secondaryOpen);

  return (
    <div className="cb-editor__header">
      <div className="cb-editor__header-left">
        <h1 className="cb-editor__title">Template Editor</h1>
        <span className="cb-editor__divider" aria-hidden="true" />
        <TemplateToolbar
          list={list}
          currentId={currentId}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
        />
      </div>

      <div className="cb-editor__header-actions">
        <SaveIndicator status={saveStatus} />

        <Button
          ref={secondaryToggleRef}
          className={`cb-editor__toggle cb-editor__toggle--secondary ${
            secondaryOpen ? "is-active" : ""
          }`}
          onClick={toggleSecondarySidebar}
          aria-label={
            secondaryOpen
              ? __("Close List View Shift+Alt+O", "campaignbridge")
              : __("Open List View Shift+Alt+O", "campaignbridge")
          }
          label={
            secondaryOpen
              ? __("Close List View Shift+Alt+O", "campaignbridge")
              : __("Open List View Shift+Alt+O", "campaignbridge")
          }
          aria-controls="cb-secondary-sidebar"
          aria-pressed={secondaryOpen}
          aria-keyshortcuts="Esc, Shift+Alt+O"
          showTooltip={true}
          variant="tertiary"
        >
          <Icon icon={listView} size={24} />
        </Button>

        <Button
          ref={primaryToggleRef}
          className={`cb-editor__toggle cb-editor__toggle--primary ${
            primaryOpen ? "is-active" : ""
          }`}
          onClick={toggleSidebar}
          aria-label={
            primaryOpen
              ? __("Close Sidebar Ctrl+Shift+,", "campaignbridge")
              : __("Open Sidebar Ctrl+Shift+,", "campaignbridge")
          }
          label={
            primaryOpen
              ? __("Close Sidebar Ctrl+Shift+,", "campaignbridge")
              : __("Open Sidebar Ctrl+Shift+,", "campaignbridge")
          }
          aria-controls="cb-primary-sidebar"
          aria-pressed={primaryOpen}
          aria-keyshortcuts="Esc, Ctrl+Shift+Comma, Meta+Shift+Comma"
          showTooltip={true}
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
