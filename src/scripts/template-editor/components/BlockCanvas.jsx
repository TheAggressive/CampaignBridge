import {
  BlockList,
  BlockTools,
  Inserter,
  ObserveTyping,
  WritingFlow,
} from "@wordpress/block-editor";
import HistoryControls from "./HistoryControls";

/**
 * Block canvas component that provides the WordPress block editor interface.
 *
 * Renders the block editor canvas and tools. The BlockEditorProvider is now
 * managed at a higher level in EditorChrome to ensure the BlockInspector
 * in the sidebar can access the same block context.
 *
 * @returns {JSX.Element} The block editor canvas
 */
export default function BlockCanvas() {
  return (
    <div className="cb-block-editor">
      <div className="cb-block-editor-tools">
        <Inserter rootClientId={null} />
        <HistoryControls />
      </div>
      <BlockTools>
        <WritingFlow>
          <ObserveTyping>
            <BlockList />
          </ObserveTyping>
        </WritingFlow>
      </BlockTools>
    </div>
  );
}
