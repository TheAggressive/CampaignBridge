import { BlockEditorKeyboardShortcuts } from "@wordpress/block-editor";
import BlockCanvas from "./BlockCanvas";

/**
 * Main content component for the CampaignBridge template editor.
 *
 * Contains the block canvas and other content-level components.
 * This component is designed to work with WordPress InterfaceSkeleton.
 *
 * @param {Object} props - Component props
 * @param {Array} props.blocks - Current blocks array
 * @param {Function} props.setBlocks - Function to update blocks
 * @param {Function} props.save - Save function
 * @param {Object} props.editorSettings - Block editor settings
 * @returns {JSX.Element} The editor content area
 */
export default function Content() {
  return (
    <div className="cb-editor-content">
      <BlockEditorKeyboardShortcuts />
      <BlockCanvas />
    </div>
  );
}
