import { Button, SelectControl } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Component-specific constants
const TOOLBAR_LABELS = {
  TEMPLATES: __('Templates', 'campaignbridge'),
  SELECT_TEMPLATE: __('Please Select a Template', 'campaignbridge'),
  LOADING: __('Loading…', 'campaignbridge'),
  NEW_TEMPLATE: __('New Template', 'campaignbridge'),
};

const TOOLBAR_CLASSES = {
  CONTAINER: 'cb-editor__toolbar',
  SELECT: 'cb-editor__templates-select',
  // Export for potential reuse by other toolbar components
};

export { TOOLBAR_CLASSES };

/**
 * Template Toolbar Component
 *
 * Provides template selection and creation controls for the CampaignBridge editor.
 * Displays a dropdown for selecting existing templates and a button for creating
 * new templates. Handles loading states and provides contextual options based
 * on the current template selection state.
 *
 * Features:
 * - Template selection dropdown with proper labeling
 * - New template creation button
 * - Loading state handling
 * - Contextual placeholder text
 * - Keyboard navigation support
 *
 * @param {Object} props - Component props
 * @param {Array} props.list - Array of available templates with id and title properties
 * @param {number|null} props.currentId - ID of the currently selected template
 * @param {boolean} props.loading - Whether templates are currently loading
 * @param {function} props.onSelect - Callback fired when a template is selected (receives template ID or null)
 * @param {function} props.onNew - Callback fired when creating a new template
 * @returns {JSX.Element} The template toolbar with selection dropdown and creation button
 *
 * @example
 * ```jsx
 * <TemplateToolbar
 *   list={[
 *     { id: 1, title: { rendered: "Welcome Email" } },
 *     { id: 2, title: { rendered: "Newsletter" } }
 *   ]}
 *   currentId={1}
 *   loading={false}
 *   onSelect={(id) => console.log('Selected:', id)}
 *   onNew={() => console.log('Creating new template')}
 * />
 * ```
 */
export default function TemplateToolbar({
  list,
  currentId,
  loading,
  onSelect,
  onNew,
}) {
  // Transform the template list into select options (memoized)
  const options = useMemo(() => {
    return (list || []).map(p => ({
      label: p?.title?.rendered || `#${p?.id}`,
      value: String(p?.id),
    }));
  }, [list]);
  return (
    <div className={TOOLBAR_CLASSES.CONTAINER}>
      <SelectControl
        className={TOOLBAR_CLASSES.SELECT}
        label={TOOLBAR_LABELS.TEMPLATES}
        hideLabelFromVision={true}
        value={currentId ? String(currentId) : ''}
        onChange={val => onSelect(Number(val) || null)}
        disabled={loading}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
        options={[
          ...(currentId
            ? []
            : [
                {
                  label: TOOLBAR_LABELS.SELECT_TEMPLATE,
                  value: '',
                },
              ]),
          ...(loading
            ? [{ label: TOOLBAR_LABELS.LOADING, value: '' }]
            : options),
        ]}
      />
      <Button variant='primary' onClick={onNew}>
        {TOOLBAR_LABELS.NEW_TEMPLATE}
      </Button>
    </div>
  );
}
