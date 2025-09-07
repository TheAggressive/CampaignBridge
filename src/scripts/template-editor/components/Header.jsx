import { Button, Icon } from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import { fullscreen as fullscreenIcon } from "@wordpress/icons";
import SaveIndicator from "./SaveIndicator";
import TemplateToolbar from "./TemplateToolbar";

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
}) {
  const isFullscreen = useSelect(
    (select) =>
      select("core/preferences").get("core/edit-post", "fullscreenMode"),
    [],
  );

  const { toggle } = useDispatch("core/preferences");

  const toggleFullscreen = () => {
    toggle("core/edit-post", "fullscreenMode");
  };

  return (
    <div className="cb-editor-header">
      <h1>Template Editor</h1>
      <div className="cb-header-actions">
        <SaveIndicator status={saveStatus} />
        <TemplateToolbar
          list={list}
          currentId={currentId}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
        />
        <Button
          className="cb-fullscreen-toggle"
          onClick={toggleFullscreen}
          label={
            isFullscreen
              ? __("Exit Fullscreen", "campaignbridge")
              : __("Enter Fullscreen", "campaignbridge")
          }
          showTooltip={true}
        >
          <Icon icon={fullscreenIcon} size={20} />
        </Button>
      </div>
    </div>
  );
}
