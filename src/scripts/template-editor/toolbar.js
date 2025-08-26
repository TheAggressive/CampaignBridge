import { Button, Inserter } from "@wordpress/components";
import { dispatch, select } from "@wordpress/data";

const Toolbar = () => {
  // Safely access block editor store with error handling
  let canUndo = false;
  let canRedo = false;

  try {
    const blockEditorStore = select("core/block-editor");
    if (blockEditorStore && typeof blockEditorStore.hasUndo === "function") {
      canUndo = blockEditorStore.hasUndo();
    }
    if (blockEditorStore && typeof blockEditorStore.hasRedo === "function") {
      canRedo = blockEditorStore.hasRedo();
    }
  } catch (error) {
    console.warn("Block editor store not available in toolbar:", error);
  }

  const handleUndo = () => {
    try {
      dispatch("core/block-editor").undo();
    } catch (error) {
      console.warn("Failed to undo:", error);
    }
  };

  const handleRedo = () => {
    try {
      dispatch("core/block-editor").redo();
    } catch (error) {
      console.warn("Failed to redo:", error);
    }
  };

  return (
    <div className="cb-tm-toolbar">
      <Button variant="secondary" onClick={handleUndo} disabled={!canUndo}>
        Undo
      </Button>
      <Button variant="secondary" onClick={handleRedo} disabled={!canRedo}>
        Redo
      </Button>

      <div style={{ marginLeft: "auto" }}>
        <Inserter rootClientId={null} />
      </div>
    </div>
  );
};

export default Toolbar;
