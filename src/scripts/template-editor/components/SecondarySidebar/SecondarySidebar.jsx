import { __experimentalListView as ListView } from "@wordpress/block-editor";

export default function SecondarySidebar() {
  return (
    <div className="cb-editor__sidebar-content-inner">
      <ListView />
    </div>
  );
}
