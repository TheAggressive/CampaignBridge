import { createSlotFill } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { memo, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Inspector from './Inspector';
import TemplateSettings from './TemplateSettings';

// Slot/Fill system for plugin extensibility
const { Slot: InspectorSlot, Fill: InspectorFill } = createSlotFill(
  'CampaignBridgeBlockEditorSidebarInspector'
);

const { Slot: TemplateSlot, Fill: TemplateFill } = createSlotFill(
  'CampaignBridgeBlockEditorSidebarTemplateSlot'
);

// Tab configuration constants
const TABS = {
  TEMPLATE: 'template-settings',
  INSPECTOR: 'block-inspector',
};

/**
 * Default props for sidebar components
 */
const DEFAULT_PROPS = {
  ACTIVE_TAB: TABS.TEMPLATE,
};

/**
 * CSS class names for consistent styling
 */
const CSS_CLASSES = {
  SIDEBAR_PANEL: 'cb-editor__sidebar-panel',
  TAB_PANEL: 'components-tab-panel__tabs',
  TAB_ITEM: 'components-tab-panel__tabs-item',
  TAB_ACTIVE: 'is-active',
  SIDEBAR_ERROR: 'cb-editor__sidebar-error',
};

/**
 * Sidebar Header Component
 *
 * Renders the tab navigation header for the sidebar using WordPress tab styling.
 * This component is used as the header for the ComplementaryArea and provides
 * accessible tab navigation between template settings and block inspector.
 *
 * @since 1.0.0
 * @param {Object} props - Component props
 * @param {string} props.activeTab - The currently active tab identifier
 * @param {Function} props.onTabChange - Callback function called when tab changes
 * @returns {JSX.Element} The rendered tab header component
 *
 * @example
 * ```jsx
 * <SidebarHeader
 *   activeTab={TABS.TEMPLATE}
 *   onTabChange={(tab) => setActiveTab(tab)}
 * />
 * ```
 */
export function SidebarHeader({ activeTab, onTabChange }) {
  // Validate required props
  if (typeof onTabChange !== 'function') {
    console.error('SidebarHeader: onTabChange prop must be a function');
    return null;
  }

  const handleTabClick = tab => {
    if (tab !== activeTab && typeof onTabChange === 'function') {
      onTabChange(tab);
    }
  };

  const handleKeyDown = (event, tab) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      handleTabClick(tab);
    }
  };

  return (
    <div
      className={CSS_CLASSES.TAB_PANEL}
      role='tablist'
      aria-label={__('Sidebar tabs', 'campaignbridge')}
    >
      <button
        type='button'
        role='tab'
        className={`${CSS_CLASSES.TAB_ITEM} ${
          activeTab === TABS.TEMPLATE ? CSS_CLASSES.TAB_ACTIVE : ''
        }`}
        onClick={() => handleTabClick(TABS.TEMPLATE)}
        onKeyDown={e => handleKeyDown(e, TABS.TEMPLATE)}
        aria-selected={activeTab === TABS.TEMPLATE}
        aria-controls='sidebar-content'
        id='tab-document'
        style={{ marginLeft: '-16px' }}
        tabIndex={activeTab === TABS.TEMPLATE ? 0 : -1}
      >
        {__('Document', 'campaignbridge')}
      </button>
      <button
        type='button'
        role='tab'
        className={`${CSS_CLASSES.TAB_ITEM} ${
          activeTab === TABS.INSPECTOR ? CSS_CLASSES.TAB_ACTIVE : ''
        }`}
        onClick={() => handleTabClick(TABS.INSPECTOR)}
        onKeyDown={e => handleKeyDown(e, TABS.INSPECTOR)}
        aria-selected={activeTab === TABS.INSPECTOR}
        aria-controls='sidebar-content'
        id='tab-block'
        tabIndex={activeTab === TABS.INSPECTOR ? 0 : -1}
      >
        {__('Block', 'campaignbridge')}
      </button>
    </div>
  );
}

/**
 * Sidebar Content Component
 *
 * Renders the content area of the sidebar based on the active tab.
 * Displays either template settings or block inspector content with
 * plugin extensibility slots for both tabs.
 *
 * @since 1.0.0
 * @param {Object} props - Component props
 * @param {string} props.activeTab - The currently active tab identifier
 * @returns {JSX.Element} The rendered sidebar content
 *
 * @example
 * ```jsx
 * <SidebarContent activeTab={TABS.TEMPLATE} />
 * ```
 */
export function SidebarContent({ activeTab, postType, postId }) {
  try {
    return (
      <>
        {activeTab === TABS.TEMPLATE ? (
          <>
            <TemplateSettings postType={postType} postId={postId} />
            <TemplateSlot bubblesVirtually />
          </>
        ) : activeTab === TABS.INSPECTOR ? (
          <>
            <Inspector />
            <InspectorSlot bubblesVirtually />
          </>
        ) : (
          <div className={CSS_CLASSES.SIDEBAR_ERROR}>
            <p>{__('Invalid tab selected', 'campaignbridge')}</p>
          </div>
        )}
      </>
    );
  } catch (error) {
    console.error('SidebarContent: Error rendering content:', error);
    return (
      <div className={CSS_CLASSES.SIDEBAR_ERROR}>
        <p>{__('Error loading sidebar content', 'campaignbridge')}</p>
      </div>
    );
  }
}

/**
 * Sidebar Component
 *
 * Main sidebar component for the CampaignBridge template editor with tabbed interface.
 * Provides access to template settings and block inspector functionality with automatic
 * tab switching when blocks are selected.
 *
 * Features:
 * - Tabbed interface with Document and Block tabs
 * - Auto-switching to Block Inspector when blocks are selected
 * - Plugin extensibility through Slot/Fill system
 * - Full keyboard navigation support
 * - Comprehensive accessibility features with proper ARIA labels
 * - Error handling and loading states
 * - Performance optimized with React.memo

 *
 * @example
 * ```jsx
 * // Basic usage
 * <Sidebar />
 *
 * // With custom initial tab
 * <Sidebar initialTab={TABS.INSPECTOR} />
 * ```
 */
function Sidebar({
  initialTab = DEFAULT_PROPS.ACTIVE_TAB,
  postType,
  postId,
}: {
  initialTab?: string;
  postType?: string;
  postId?: number;
}) {
  const [activeTab, setActiveTab] = useState(initialTab);

  // Detect when a block is selected in the editor
  const selectedBlock = useSelect(select => {
    try {
      const { getSelectedBlock } = select('core/block-editor') as {
        getSelectedBlock?: () => unknown;
      };
      return getSelectedBlock?.();
    } catch (error) {
      console.warn('Sidebar: Error accessing block editor state:', error);
      return null;
    }
  }, []);

  // Auto-switch to Block Inspector when a block is selected
  useEffect(() => {
    if (selectedBlock && activeTab !== TABS.INSPECTOR) {
      setActiveTab(TABS.INSPECTOR);
    }
  }, [selectedBlock, activeTab]);

  try {
    return (
      <SidebarContent
        activeTab={activeTab}
        postType={postType}
        postId={postId}
      />
    );
  } catch (error) {
    console.error('Sidebar: Error rendering component:', error);
    return (
      <div className={CSS_CLASSES.SIDEBAR_ERROR}>
        <p>{__('Error loading sidebar', 'campaignbridge')}</p>
      </div>
    );
  }
}

// Wrap with memo for performance optimization
export default memo(Sidebar);

/**
 * Inspector Fill Component
 *
 * Fill component that allows plugins to extend the Block Inspector tab content.
 * This component uses the Slot/Fill pattern to provide plugin extensibility.
 *
 * @since 1.0.0
 * @component
 * @example
 * ```jsx
 * import { Fill } from '@wordpress/components';
 * import { Sidebar } from './components/Sidebar';
 *
 * function MyPluginExtension() {
 *   return (
 *     <Fill name="CampaignBridgeBlockEditorSidebarInspector">
 *       <div>My custom inspector content</div>
 *     </Fill>
 *   );
 * }
 * ```
 */
Sidebar.InspectorFill = InspectorFill;

/**
 * Template Fill Component
 *
 * Fill component that allows plugins to extend the Template Settings tab content.
 * This component uses the Slot/Fill pattern to provide plugin extensibility.
 *
 * @since 1.0.0
 * @component
 * @example
 * ```jsx
 * import { Fill } from '@wordpress/components';
 * import { Sidebar } from './components/Sidebar';
 *
 * function MyTemplateExtension() {
 *   return (
 *     <Fill name="CampaignBridgeBlockEditorSidebarTemplateSlot">
 *       <div>My custom template settings</div>
 *     </Fill>
 *   );
 * }
 * ```
 */
Sidebar.TemplateFill = TemplateFill;

/**
 * Inspector Slot Component
 *
 * Slot component for the Block Inspector tab that renders plugin extensions.
 * This is the target where InspectorFill components are rendered.
 *
 * @since 1.0.0
 * @component
 * @private
 */

/**
 * Template Slot Component
 *
 * Slot component for the Template Settings tab that renders plugin extensions.
 * This is the target where TemplateFill components are rendered.
 *
 * @since 1.0.0
 * @component
 * @private
 */

// Export fills for plugin extensibility
Sidebar.TemplateFill = TemplateFill;

// Export slots for direct use
export { InspectorSlot, TemplateSlot };

// Export constants for external use
export { DEFAULT_PROPS, TABS };
