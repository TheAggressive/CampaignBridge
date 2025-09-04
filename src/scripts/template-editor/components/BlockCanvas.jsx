import {
  BlockSelectionClearer,
  BlockToolbar,
  BlockTools,
  Inserter,
  BlockCanvas as WordPressBlockCanvas,
} from "@wordpress/block-editor";
import HistoryControls from "./HistoryControls";

/**
 * Block canvas component that provides the WordPress block editor interface.
 *
 * Renders the block editor canvas and tools. The BlockEditorProvider is now
 * managed at a higher level in EditorChrome to ensure the BlockInspector
 * in the sidebar can access the same block context.
 *
 * @param {Object} props - Component props
 * @param {number} props.postId - The ID of the post/template to load and edit
 * @param {function} [props.onBlocksChange] - Callback fired when blocks change
 * @returns {JSX.Element} The block editor canvas
 */
export default function BlockCanvas({ postId, onBlocksChange }) {
  return (
    <div className="cb-block-editor">
      <BlockTools>
        <div className="cb-block-editor-tools">
          <Inserter rootClientId={null} />
          <HistoryControls />
        </div>
        <BlockToolbar />
        <BlockSelectionClearer>
          <WordPressBlockCanvas width="100%" height="100%" />
        </BlockSelectionClearer>
      </BlockTools>
    </div>
  );
}
