import { FullscreenToggle } from "./Button/FullscreenToggle";
import { PrimarySidebarToggle } from "./Button/PrimarySidebarToggle";
import { SecondarySidebarToggle } from "./Button/SecondarySidebarToggle";
import TemplateToolbar from "./TemplateToolbar";

/* CSS classes */
const CLASSES = {
  HEADER: "cb-editor__header",
  HEADER_LEFT: "cb-editor__header-left",
  HEADER_TITLE: "cb-editor__title",
  HEADER_ACTIONS: "cb-editor__header-actions",
};

/**
 * Header Component
 *
 * Main header component for the CampaignBridge template editor.
 * This component orchestrates the template toolbar and self-contained
 * toggle button components.
 *
 * Features:
 * - Template selection dropdown with search and creation
 * - Self-contained toggle buttons with internal state management
 * - Clean separation of concerns with modular architecture
 *
 * Architecture:
 * - Uses self-contained button components (each handles its own state)
 * - Minimal orchestration - just imports and renders components
 * - Each button component manages its own preferences and shortcuts
 * - Header focuses purely on layout and template functionality
 *
 * Keyboard Shortcuts (WordPress Native - handled by individual components):
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
        <SecondarySidebarToggle />
        <PrimarySidebarToggle />
        <FullscreenToggle />
      </div>
    </div>
  );
}
