import { Button, SelectControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

/**
 * Template toolbar component that provides template selection and creation controls.
 *
 * Displays a dropdown for selecting existing templates and a button for creating
 * new templates. Handles loading states and provides appropriate options based
 * on the current state.
 *
 * @param {Object} props - Component props
 * @param {Array} props.list - Array of available templates
 * @param {number|null} props.currentId - ID of the currently selected template
 * @param {boolean} props.loading - Whether templates are currently loading
 * @param {function} props.onSelect - Callback fired when a template is selected
 * @param {function} props.onNew - Callback fired when creating a new template
 * @returns {JSX.Element} The template toolbar UI
 */
export default function TemplateToolbar({
  list,
  currentId,
  loading,
  onSelect,
  onNew,
}) {
  /**
   * Transforms the template list into select control options.
   * Uses the rendered title if available, otherwise falls back to the template ID.
   */
  const options = (list || []).map((p) => ({
    label: p?.title?.rendered || `#${p.id}`,
    value: String(p.id),
  }));
  return (
    <div className="cb-tm-toolbar">
      <SelectControl
        label={__("Templates", "campaignbridge")}
        value={currentId ? String(currentId) : ""}
        onChange={
          /**
           * Handles template selection from the dropdown.
           * Converts the string value back to a number and passes it to onSelect.
           * @param {string} val - The selected template ID as a string
           */
          (val) => onSelect(Number(val) || null)
        }
        disabled={loading}
        options={[
          ...(currentId
            ? []
            : [
                {
                  label: __("Please select a template", "campaignbridge"),
                  value: "",
                },
              ]),
          ...(loading
            ? [{ label: __("Loading…", "campaignbridge"), value: "" }]
            : options),
        ]}
      />
      <Button variant="primary" onClick={onNew}>
        {__("New Template", "campaignbridge")}
      </Button>
    </div>
  );
}
