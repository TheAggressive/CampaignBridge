import {
  BlockList,
  BlockTools,
  Inserter,
  ObserveTyping,
  WritingFlow,
} from "@wordpress/block-editor";
import HistoryControls from "./HistoryControls";

/**
 * Block Canvas Component
 *
 * Provides the main editing canvas for the WordPress block editor interface.
 * Includes the block inserter, history controls, and the nested block list
 * with writing flow and typing observation capabilities.
 *
 * The BlockEditorProvider is managed at a higher level in EditorChrome to ensure
 * the BlockInspector in the sidebar can access the same block context.
 *
 * @returns {JSX.Element} The block editor canvas with tools and block list
 *
 * @example
 * ```jsx
 * <BlockCanvas />
 * ```
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
