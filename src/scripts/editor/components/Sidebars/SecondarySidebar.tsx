import { __experimentalListView as ListView } from "@wordpress/block-editor";
import { useSelect } from "@wordpress/data";

export default function SecondarySidebar() {
  // Get the selected block and root blocks count for display
  const { selectedBlockClientId, blockCount } = useSelect((select: any) => {
    const blockEditorSelect = select("core/block-editor");
    return {
      selectedBlockClientId: blockEditorSelect.getSelectedBlockClientId(),
      blockCount: blockEditorSelect.getBlocks().length,
    };
  }, []);

  return (
    <div
      className="cb-editor__sidebar-content-inner"
      style={{ height: "100%", overflow: "auto" }}
    >
      {blockCount > 0 ? (
        <ListView
          rootClientId=""
          selectedBlockClientId={selectedBlockClientId}
          showNestedBlocks
          showBlockMovers={false}
          showAppender={false}
        />
      ) : (
        <div style={{ padding: "16px", color: "#666" }}>
          No blocks found. Add some content to see the block structure.
        </div>
      )}
    </div>
  );
}
