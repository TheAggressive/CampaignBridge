import { BlockEditorKeyboardShortcuts } from '@wordpress/block-editor';
import BlockCanvas from './BlockCanvas';
import EditorKeyboardShortcuts from './EditorKeyboardShortcuts';
import type { EditorStyle } from '../types';

interface ContentProps {
  onSave: () => void | Promise<unknown>;
  styles?: EditorStyle[];
}

/**
 * Main content component for the CampaignBridge template editor.
 *
 * Provides the main content area containing the block canvas and keyboard shortcuts.
 */
export default function Content({ onSave, styles }: ContentProps): JSX.Element {
  return (
    <div className='cb-editor__content'>
      <BlockEditorKeyboardShortcuts />
      <EditorKeyboardShortcuts onSave={onSave} />
      <BlockCanvas styles={styles} />
    </div>
  );
}
