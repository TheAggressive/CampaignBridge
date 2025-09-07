import { createSlotFill, TabPanel } from "@wordpress/components";
import { useSelect } from "@wordpress/data";
import { useCallback, useEffect, useMemo, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import Inspector from "./Sidebar/Inspector";
import TemplateSettings from "./Sidebar/TemplateSettings";

// Slot/Fill system for plugin extensibility
const { Slot: InspectorSlot, Fill: InspectorFill } = createSlotFill(
  "CampaignBridgeBlockEditorSidebarInspector",
);

const { Slot: TemplateSlot, Fill: TemplateFill } = createSlotFill(
  "CampaignBridgeBlockEditorSidebarTemplateSlot",
);

// Tab configuration constants
const TABS = {
  TEMPLATE: "template-settings",
  INSPECTOR: "block-inspector",
};

export default function Sidebar() {
  const [activeTab, setActiveTab] = useState(TABS.TEMPLATE);

  // Detect when a block is selected in the editor
  const selectedBlock = useSelect((select) => {
    const { getSelectedBlock } = select("core/block-editor");
    return getSelectedBlock();
  }, []);

  // Auto-switch to Block Inspector when a block is selected
  useEffect(() => {
    if (selectedBlock) {
      setActiveTab(TABS.INSPECTOR);
    }
  }, [selectedBlock]);

  // Memoize tabs configuration to prevent unnecessary re-renders
  const tabs = useMemo(
    () => [
      {
        name: TABS.TEMPLATE,
        title: __("Template Settings", "campaignbridge"),
      },
      {
        name: TABS.INSPECTOR,
        title: __("Block Inspector", "campaignbridge"),
      },
    ],
    [],
  );

  // Memoize tab change handler
  const handleTabChange = useCallback((tabName) => {
    setActiveTab(tabName);
  }, []);

  return (
    <div
      className="cb-editor-sidebar"
      role="region"
      aria-label={__("Editor Sidebar", "campaignbridge")}
      tabIndex="-1"
    >
      <TabPanel
        key={activeTab} // Force re-mount when activeTab changes for programmatic control
        tabs={tabs}
        initialTabName={activeTab}
        onSelect={handleTabChange}
        
      >
        {(tab) =>
          tab.name === TABS.TEMPLATE ? (
            <>
              <TemplateSettings />
              <TemplateSlot bubblesVirtually />
            </>
          ) : (
            <>
              <Inspector />
              <InspectorSlot bubblesVirtually />
            </>
          )
        }
      </TabPanel>
    </div>
  );
}

// Export fills for plugin extensibility
Sidebar.InspectorFill = InspectorFill;
Sidebar.TemplateFill = TemplateFill;
