import { Panel, PanelBody, TextControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export default function TemplateSettings() {
  return (
    <Panel>
      <PanelBody
        title={__("Template Configuration", "campaignbridge")}
        initialOpen={true}
      >
        <TextControl
          label={__("Default Subject Line", "campaignbridge")}
          placeholder={__("Enter default subject line...", "campaignbridge")}
          help={__(
            "This will be used as the default subject for new emails",
            "campaignbridge",
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        <TextControl
          label={__("From Name", "campaignbridge")}
          placeholder={__("Your Name", "campaignbridge")}
          help={__("The name that will appear as the sender", "campaignbridge")}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        <TextControl
          label={__("Reply-To Email", "campaignbridge")}
          type="email"
          placeholder={__("reply@yourdomain.com", "campaignbridge")}
          help={__("Email address for replies", "campaignbridge")}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />
      </PanelBody>
    </Panel>
  );
}
