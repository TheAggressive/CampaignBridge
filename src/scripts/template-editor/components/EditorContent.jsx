import BlockCanvas from "./BlockCanvas";

/**
 * Main content component for the CampaignBridge template editor.
 *
 * Contains the block canvas and other content-level components.
 * This component is designed to work with WordPress InterfaceSkeleton.
 *
 * @param {Object} props - Component props
 * @param {number} props.postId - The ID of the post/template to load and edit
 * @param {function} [props.onBlocksChange] - Callback fired when blocks change
 * @returns {JSX.Element} The editor content area
 */
export default function EditorContent({ postId, onBlocksChange }) {
  return (
    <div className="cb-editor-content">
      <BlockCanvas postId={postId} onBlocksChange={onBlocksChange} />
    </div>
  );
}
