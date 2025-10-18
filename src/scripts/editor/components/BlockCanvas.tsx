import {
  BlockList,
  BlockSelectionClearer,
  BlockTools,
  ButtonBlockAppender,
  Inserter,
  ObserveTyping,
  WritingFlow,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import * as React from 'react';
import HistoryControls from './Toolbar/HistoryControls';

declare module '@wordpress/block-editor' {
  export const BlockTools: React.ComponentType<any>;
}

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
 * Note: CopyHandler was removed as it's deprecated since WordPress 6.4.
 * Copy/paste functionality is now handled by WritingFlow and the block editor core.
 *
 * @returns {JSX.Element} The block editor canvas with tools and block list
 *
 * @example
 * ```jsx
 * <BlockCanvas />
 * ```
 */
export default function BlockCanvas() {
  // Get the clientId of the last block for the appender
  const lastBlockClientId = useSelect((select: any) => {
    const blockEditorSelect = select('core/block-editor');
    const blocks = blockEditorSelect.getBlocks();
    return blocks.length > 0 ? blocks[blocks.length - 1].clientId : null;
  }, []);

  return (
    <div className='cb-editor__canvas'>
      <div className='cb-editor__canvas-tools'>
        <Inserter rootClientId={null} />
        <HistoryControls />
      </div>
      <BlockSelectionClearer>
        <BlockTools>
          <WritingFlow>
            <ObserveTyping>
              <BlockList />
              <ButtonBlockAppender rootClientId={null} />
              {/* <DefaultBlockAppender
                rootClientId={null}
                lastBlockClientId={lastBlockClientId}
              /> */}
            </ObserveTyping>
          </WritingFlow>
        </BlockTools>
      </BlockSelectionClearer>
    </div>
  );
}
