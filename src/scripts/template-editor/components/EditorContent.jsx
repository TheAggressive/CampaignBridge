import BlockCanvas from "./BlockCanvas";

/**
 * Main content component for the CampaignBridge template editor.
 *
 * Contains the block canvas and other content-level components.
 * This component is designed to work with WordPress InterfaceSkeleton.
 *
 * @returns {JSX.Element} The editor content area
 */
export default function EditorContent() {
  return (
    <div className="cb-editor-content">
      <BlockCanvas />
    </div>
  );
}
