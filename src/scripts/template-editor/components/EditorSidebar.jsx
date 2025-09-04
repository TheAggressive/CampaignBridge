import { BlockInspector } from "@wordpress/block-editor";
import { Panel, PanelBody } from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "@wordpress/element";
import { __ } from "@wordpress/i18n";

// Constants for better maintainability
const INTERACTION_TIMEOUT_MS = 100;

/**
 * Simple Tab Component - avoids WordPress TabPanel state issues
 */
function SimpleTabs({ tabs, activeTab, onTabChange, children }) {
  return (
    <div className="cb-editor-sidebar">
      {/* Tab Navigation */}
      <div className="cb-sidebar-tabs">
        <div className="cb-tab-buttons">
          {tabs.map((tab) => (
            <button
              key={tab.name}
              className={`cb-tab-button ${
                activeTab === tab.name ? "is-active" : ""
              }`}
              onClick={() => onTabChange(tab.name)}
            >
              {tab.title}
            </button>
          ))}
        </div>
      </div>

      {/* Tab Content */}
      <div className="cb-sidebar-content">{children}</div>
    </div>
  );
}

/**
 * Custom hook for managing sidebar tab switching logic
 */
function useSidebarTabSwitching() {
  const [activeTab, setActiveTab] = useState("document");
  const [localSelectedIds, setLocalSelectedIds] = useState([]);
  const isUserInteracting = useRef(false);
  const userInteractionTimeout = useRef(null);

  // Get WordPress dispatch functions
  const { clearSelectedBlock } = useDispatch("core/block-editor");

  // Track selected blocks from WordPress
  const wpSelectedBlockClientIds = useSelect((select) => {
    try {
      const blockEditor = select("core/block-editor");
      return blockEditor.getSelectedBlockClientIds();
    } catch (error) {
      console.warn("Error getting selected block IDs:", error);
      return [];
    }
  }, []);

  // Sync WordPress selection to our local state
  useEffect(() => {
    if (wpSelectedBlockClientIds && wpSelectedBlockClientIds.length > 0) {
      setLocalSelectedIds(wpSelectedBlockClientIds);
    }
  }, [wpSelectedBlockClientIds]);

  // Computed values
  const hasSelectedBlock = localSelectedIds.length > 0;

  // Auto-switch based on selection state
  useEffect(() => {
    // If we have selected blocks and we're not on block tab, switch to block tab
    if (
      hasSelectedBlock &&
      activeTab !== "block" &&
      !isUserInteracting.current
    ) {
      setActiveTab("block");
    }
    // If we have no selected blocks and we're not on document tab, switch to document tab
    else if (
      !hasSelectedBlock &&
      activeTab !== "document" &&
      !isUserInteracting.current
    ) {
      setActiveTab("document");
    }
  }, [hasSelectedBlock, activeTab]);

  // Handle manual tab switches
  const handleManualTabSwitch = useCallback(
    (tabName) => {
      // Set interaction flag
      isUserInteracting.current = true;

      // Handle selection clearing based on tab
      if (tabName === "document") {
        setLocalSelectedIds([]);
        try {
          clearSelectedBlock();
        } catch (error) {
          console.warn("Error clearing selected block:", error);
        }
      }

      // Clear any existing timeout
      if (userInteractionTimeout.current) {
        clearTimeout(userInteractionTimeout.current);
      }

      // Switch tab immediately
      setActiveTab(tabName);

      // Clear interaction flag after delay
      userInteractionTimeout.current = setTimeout(() => {
        isUserInteracting.current = false;
      }, INTERACTION_TIMEOUT_MS);
    },
    [clearSelectedBlock],
  );

  // Cleanup timeout on unmount
  useEffect(() => {
    return () => {
      if (userInteractionTimeout.current) {
        clearTimeout(userInteractionTimeout.current);
      }
    };
  }, []);

  return {
    activeTab,
    hasSelectedBlock,
    handleManualTabSwitch,
  };
}

/**
 * Sidebar component for the CampaignBridge template editor.
 *
 * Contains document settings and block inspection tools with tabbed interface.
 * This component is designed to work with WordPress InterfaceSkeleton.
 *
 * @returns {JSX.Element} The editor sidebar
 */
export default function EditorSidebar() {
  // Use custom hook for tab switching logic
  const { activeTab, hasSelectedBlock, handleManualTabSwitch } =
    useSidebarTabSwitching();

  // Memoize tabs configuration
  const tabs = useMemo(
    () => [
      {
        name: "document",
        title: __("Document", "campaignbridge"),
      },
      {
        name: "block",
        title: __("Block", "campaignbridge"),
      },
    ],
    [],
  );

  return (
    <SimpleTabs
      tabs={tabs}
      activeTab={activeTab}
      onTabChange={handleManualTabSwitch}
    >
      {activeTab === "document" && (
        <Panel>
          <PanelBody title={__("Status & Visibility", "campaignbridge")}>
            <div className="cb-document-settings">
              <p>
                {__(
                  "Document settings will be available here.",
                  "campaignbridge",
                )}
              </p>
            </div>
          </PanelBody>
          <PanelBody
            title={__("Template Settings", "campaignbridge")}
            initialOpen={false}
          >
            <div className="cb-template-settings">
              <p>
                {__(
                  "Template-specific settings will appear here.",
                  "campaignbridge",
                )}
              </p>
            </div>
          </PanelBody>
        </Panel>
      )}
      {activeTab === "block" && <BlockInspector />}
    </SimpleTabs>
  );
}
