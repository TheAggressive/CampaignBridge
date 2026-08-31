import { BlockEditorKeyboardShortcuts } from '@wordpress/block-editor';
import BlockCanvas from './BlockCanvas';
import EditorKeyboardShortcuts from './EditorKeyboardShortcuts';

/**
 * Main content component for the CampaignBridge template editor.
 *
 * Provides the main content area containing the block canvas and keyboard shortcuts.
 * This component is designed to work with WordPress InterfaceSkeleton and serves
 * as the primary editing surface for template blocks.
 *
 * @returns {JSX.Element} The editor content area with block canvas and keyboard shortcuts
 *
 * @example
 * ```jsx
 * <Content />
 * ```
 */
export default function Content({ onSave, styles }) {
  return (
    <div className='cb-editor__content'>
      <BlockEditorKeyboardShortcuts />
      <EditorKeyboardShortcuts onSave={onSave} />
      <BlockCanvas styles={styles} />
    </div>
  );
}
