import { __experimentalListView as ListView } from "@wordpress/block-editor";
import { Panel } from "@wordpress/components";

export default function SecondarySidebar() {
  return (
    <Panel header="List View" className="cb-editor-sidebar">
      <ListView />
    </Panel>
  );
}
