import { BlockInspector } from "@wordpress/block-editor";
import { Panel, PanelBody } from "@wordpress/components";

export default function Inspector() {
  return (
    <Panel>
      <PanelBody>
        <BlockInspector />
      </PanelBody>
    </Panel>
  );
}
