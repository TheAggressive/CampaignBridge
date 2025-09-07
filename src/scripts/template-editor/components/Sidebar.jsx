import {
  createSlotFill,
  Panel,
  PanelBody,
  TabPanel,
  TextControl,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";

const { Slot: InspectorSlot, Fill: InspectorFill } = createSlotFill(
  "CampaignBridgeBlockEditorSidebarInspector",
);

const { Slot: TemplateSlot, Fill: TemplateFill } = createSlotFill(
  "CampaignBridgeBlockEditorSidebarTemplateSlot",
);

export default function Sidebar() {
  const tabs = [
    {
      name: "template-settings",
      title: __("Template Settings", "campaignbridge"),
    },
    {
      name: "block-inspector",
      title: __("Block Inspector", "campaignbridge"),
    },
  ];

  return (
    <div
      className="cb-editor-sidebar"
      role="region"
      aria-label={__("Editor Sidebar", "campaignbridge")}
      tabIndex="-1"
    >
      <TabPanel tabs={tabs} initialTabName="template-settings">
        {(tab) =>
          tab.name === "template-settings" ? (
            <>
              <Panel>
                <PanelBody
                  title={__("Template Configuration", "campaignbridge")}
                  initialOpen={true}
                >
                  <div
                    style={{
                      display: "flex",
                      flexDirection: "column",
                      gap: "16px",
                    }}
                  >
                    <TextControl
                      label={__("Default Subject Line", "campaignbridge")}
                      placeholder={__(
                        "Enter default subject line...",
                        "campaignbridge",
                      )}
                      help={__(
                        "This will be used as the default subject for new emails",
                        "campaignbridge",
                      )}
                    />

                    <TextControl
                      label={__("From Name", "campaignbridge")}
                      placeholder={__("Your Name", "campaignbridge")}
                      help={__(
                        "The name that will appear as the sender",
                        "campaignbridge",
                      )}
                    />

                    <TextControl
                      label={__("Reply-To Email", "campaignbridge")}
                      type="email"
                      placeholder={__("reply@yourdomain.com", "campaignbridge")}
                      help={__("Email address for replies", "campaignbridge")}
                    />
                  </div>
                </PanelBody>
              </Panel>
              <TemplateSlot bubblesVirtually />
            </>
          ) : (
            <Panel>
              <PanelBody>
                <InspectorSlot bubblesVirtually />
              </PanelBody>
            </Panel>
          )
        }
      </TabPanel>
    </div>
  );
}

Sidebar.InspectorFill = InspectorFill;
Sidebar.TemplateFill = TemplateFill;
