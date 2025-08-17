/**
 * CampaignBridge Standalone Block Editor
 *
 * A native WordPress block editor instance for editing email templates.
 * Based on the official WordPress block editor documentation.
 */

import apiFetch from '@wordpress/api-fetch';
import {
  BlockEditorProvider,
  BlockList,
  BlockTools,
  ObserveTyping,
  WritingFlow,
} from '@wordpress/block-editor';
import { parse, serialize } from '@wordpress/blocks';
import {
  DropZoneProvider,
  Popover,
  SlotFillProvider,
} from '@wordpress/components';
import { createElement, render, useEffect, useState } from '@wordpress/element';
import { ShortcutProvider } from '@wordpress/keyboard-shortcuts';

/**
 * Main Editor Component
 */
function Editor() {
  const [blocks, setBlocks] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  // Get the current post data
  const postData = window.CB_POST || {};
  const editorSettings = window.CB_EDITOR_BOOT?.settings || {};

  // Initialize blocks from post content
  useEffect(() => {
    if (postData.content) {
      const parsedBlocks = parse(postData.content);
      setBlocks(parsedBlocks);
    }
    setIsLoading(false);
  }, [postData.content]);

  // Auto-save function
  const savePost = async (blocksToSave) => {
    if (!postData.id) return;

    try {
      const content = serialize(blocksToSave);
      await apiFetch({
        path: `/wp/v2/cb_template/${postData.id}`,
        method: 'POST',
        data: {
          content: content,
          status: 'draft',
        },
      });
    } catch (error) {
      console.error('Save failed:', error);
    }
  };

  // Handle block changes
  const onBlocksChange = (newBlocks) => {
    setBlocks(newBlocks);
    // Auto-save after a delay
    clearTimeout(window.cbSaveTimeout);
    window.cbSaveTimeout = setTimeout(() => {
      savePost(newBlocks);
    }, 1000);
  };

  if (isLoading) {
    return createElement(
      'div',
      { className: 'cb-editor-loading' },
      'Loading editor...'
    );
  }

  return createElement(
    SlotFillProvider,
    null,
    createElement(
      DropZoneProvider,
      null,
      createElement(
        ShortcutProvider,
        null,
        createElement(
          'div',
          { className: 'cb-block-editor' },
          createElement(
            BlockEditorProvider,
            {
              value: blocks,
              onInput: onBlocksChange,
              onChange: onBlocksChange,
              settings: editorSettings,
            },
            createElement(
              'div',
              { className: 'cb-editor-toolbar' },
              createElement('h2', null, postData.title || 'Email Template'),
              createElement(
                'button',
                {
                  className: 'button button-primary',
                  onClick: () => savePost(blocks),
                },
                'Save Template'
              )
            ),
            createElement(
              'div',
              { className: 'cb-editor-content' },
              createElement(
                BlockTools,
                null,
                createElement(
                  WritingFlow,
                  null,
                  createElement(
                    ObserveTyping,
                    null,
                    createElement(BlockList, { className: 'cb-block-list' })
                  )
                )
              )
            )
          ),
          createElement(Popover.Slot)
        )
      )
    )
  );
}

/**
 * Initialize the editor when DOM is ready
 */
function initializeEditor() {
  const container = document.getElementById('cb-standalone-editor');

  if (!container) {
    console.error('CampaignBridge: Editor container not found');
    return;
  }

  if (!window.CB_EDITOR_BOOT) {
    console.error('CampaignBridge: Editor boot data not found');
    return;
  }

  // Clear the loading message
  container.innerHTML = '';

  // Render the editor
  render(createElement(Editor), container);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeEditor);
} else {
  initializeEditor();
}
