// HistoryControls.jsx
import { Button, Icon, Tooltip } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { redo as redoIcon, undo as undoIcon } from '@wordpress/icons';

/**
 * History Controls Component
 *
 * Provides custom undo/redo functionality for the block editor with full history management.
 * Tracks block state changes, manages history stack (limited to 50 entries), and provides
 * keyboard shortcuts for undo (Ctrl+Z/Cmd+Z) and redo (Ctrl+Shift+Z/Cmd+Shift+Z or Ctrl+Y/Cmd+Y).
 *
 * Features:
 * - Real-time block state tracking and history management
 * - Keyboard shortcuts for undo/redo operations
 * - Visual buttons with tooltips and accessibility labels
 * - Automatic history pruning to prevent memory issues
 * - Smart history branching (removes future history when new changes are made)
 *
 * @returns {JSX.Element} The history controls toolbar with undo/redo buttons
 *
 * @example
 * ```jsx
 * <HistoryControls />
 * ```
 */
export default function HistoryControls() {
  // Custom history management for reliable undo/redo in custom block editors
  const [history, setHistory] = useState([]);
  const [historyIndex, setHistoryIndex] = useState(-1);

  // Get current blocks for history tracking
  const { currentBlocks } = useSelect(
    (select: any) => {
      const blockEditor = select('core/block-editor');
      const blocks = blockEditor?.getBlocks ? blockEditor.getBlocks() : [];
      return { currentBlocks: blocks };
    },
    [history, historyIndex]
  );

  // Determine if undo/redo actions are available
  const hasUndo = historyIndex > 0;
  const hasRedo = historyIndex < history.length - 1;

  // Track all block changes for full history
  useEffect(() => {
    if (currentBlocks && currentBlocks.length >= 0) {
      const currentState = JSON.stringify(currentBlocks);
      const lastState = history[historyIndex];

      // Only add to history if this is a different state
      if (!lastState || lastState !== currentState) {
        // Remove any history after current index (for when user makes new changes after undo)
        const newHistory = history.slice(0, historyIndex + 1);
        newHistory.push(currentState);

        // Limit history to 50 entries to prevent memory issues
        if (newHistory.length > 50) {
          newHistory.shift(); // Remove oldest entry
        }

        setHistory(newHistory);
        setHistoryIndex(newHistory.length - 1);
      }
    }
  }, [currentBlocks, history, historyIndex]);

  const blockEditorDispatch = useDispatch('core/block-editor');

  // Define undo/redo logic with useCallback for proper memoization
  const performUndo = useCallback(() => {
    // Navigate backward in history
    if (historyIndex > 0) {
      const previousIndex = historyIndex - 1;
      const previousState = history[previousIndex];

      try {
        const previousBlocks = JSON.parse(previousState);

        // Use WordPress block editor API to restore previous state
        if (blockEditorDispatch?.replaceBlocks) {
          blockEditorDispatch.replaceBlocks([], previousBlocks);
          setHistoryIndex(previousIndex);
        } else if (blockEditorDispatch?.resetBlocks) {
          blockEditorDispatch.resetBlocks(previousBlocks);
          setHistoryIndex(previousIndex);
        }
      } catch (error) {
        console.error('Error during undo:', error);
      }
    }
  }, [historyIndex, history, blockEditorDispatch]);

  const performRedo = useCallback(() => {
    // Navigate forward in history
    if (historyIndex < history.length - 1) {
      const nextIndex = historyIndex + 1;
      const nextState = history[nextIndex];

      try {
        const nextBlocks = JSON.parse(nextState);

        // Use WordPress block editor API to restore next state
        if (blockEditorDispatch?.replaceBlocks) {
          blockEditorDispatch.replaceBlocks([], nextBlocks);
          setHistoryIndex(nextIndex);
        } else if (blockEditorDispatch?.resetBlocks) {
          blockEditorDispatch.resetBlocks(nextBlocks);
          setHistoryIndex(nextIndex);
        }
      } catch (error) {
        console.error('Error during redo:', error);
      }
    }
  }, [historyIndex, history, blockEditorDispatch]);

  // Keyboard shortcuts for undo/redo
  useEffect(() => {
    const handleKeyDown = event => {
      // Ctrl+Z or Cmd+Z (Undo)
      if (
        (event.ctrlKey || event.metaKey) &&
        !event.shiftKey &&
        event.key === 'z'
      ) {
        event.preventDefault();
        if (hasUndo) performUndo();
      }
      // Ctrl+Shift+Z or Cmd+Shift+Z (Redo)
      else if (
        (event.ctrlKey || event.metaKey) &&
        event.shiftKey &&
        event.key === 'z'
      ) {
        event.preventDefault();
        if (hasRedo) performRedo();
      }
      // Ctrl+Y or Cmd+Y (Alternative Redo)
      else if (
        (event.ctrlKey || event.metaKey) &&
        !event.shiftKey &&
        event.key === 'y'
      ) {
        event.preventDefault();
        if (hasRedo) performRedo();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [hasUndo, hasRedo, performUndo, performRedo]);

  return (
    <div
      role='toolbar'
      aria-label='Editor history controls'
      style={{ display: 'flex', gap: 8 }}
    >
      <Tooltip text='Undo last action (Ctrl+Z / Cmd+Z)'>
        <Button
          onClick={performUndo}
          disabled={!hasUndo}
          aria-label={
            hasUndo
              ? 'Undo last action (Ctrl+Z or Cmd+Z)'
              : 'No actions available to undo'
          }
        >
          <Icon icon={undoIcon} size={20} />
        </Button>
      </Tooltip>

      <Tooltip text='Redo last undone action (Ctrl+Shift+Z / Cmd+Shift+Z)'>
        <Button
          onClick={performRedo}
          disabled={!hasRedo}
          aria-label={
            hasRedo
              ? 'Redo last undone action (Ctrl+Shift+Z or Cmd+Shift+Z)'
              : 'No actions available to redo'
          }
        >
          <Icon icon={redoIcon} size={20} />
        </Button>
      </Tooltip>
    </div>
  );
}
