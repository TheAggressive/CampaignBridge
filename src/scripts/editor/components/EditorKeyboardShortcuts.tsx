import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isAppleOS } from '@wordpress/keycodes';
import {
  store as keyboardShortcutsStore,
  useShortcut,
} from '@wordpress/keyboard-shortcuts';

interface EditorKeyboardShortcutsProps {
  onSave: () => void | Promise<unknown>;
}

function usesNativeTextHistory(event: KeyboardEvent): boolean {
  const target = event.target;

  return (
    target instanceof HTMLInputElement ||
    target instanceof HTMLTextAreaElement ||
    (target instanceof HTMLElement && target.isContentEditable)
  );
}

/**
 * Register the small subset of global editor shortcuts this standalone editor
 * supports without initializing the full core/editor post editor store.
 */
export default function EditorKeyboardShortcuts({
  onSave,
}: EditorKeyboardShortcutsProps): null {
  const { registerShortcut, unregisterShortcut } = useDispatch(
    keyboardShortcutsStore
  );
  const { undo, redo } = useDispatch(coreStore);
  const { hasUndo, hasRedo } = useSelect(
    select => ({
      hasUndo: select(coreStore).hasUndo(),
      hasRedo: select(coreStore).hasRedo(),
    }),
    []
  );

  useEffect(() => {
    registerShortcut({
      name: 'campaignbridge/editor/undo',
      category: 'global',
      description: __('Undo your last changes.', 'campaignbridge'),
      keyCombination: { modifier: 'primary', character: 'z' },
    });
    registerShortcut({
      name: 'campaignbridge/editor/redo',
      category: 'global',
      description: __('Redo your last undo.', 'campaignbridge'),
      keyCombination: { modifier: 'primaryShift', character: 'z' },
      aliases: isAppleOS() ? [] : [{ modifier: 'primary', character: 'y' }],
    });
    registerShortcut({
      name: 'campaignbridge/editor/save',
      category: 'global',
      description: __('Save your changes.', 'campaignbridge'),
      keyCombination: { modifier: 'primary', character: 's' },
    });

    return () => {
      unregisterShortcut('campaignbridge/editor/undo');
      unregisterShortcut('campaignbridge/editor/redo');
      unregisterShortcut('campaignbridge/editor/save');
    };
  }, [registerShortcut, unregisterShortcut]);

  useShortcut(
    'campaignbridge/editor/undo',
    event => {
      if (!usesNativeTextHistory(event)) {
        event.preventDefault();
        undo();
      }
    },
    { isDisabled: !hasUndo }
  );
  useShortcut(
    'campaignbridge/editor/redo',
    event => {
      if (!usesNativeTextHistory(event)) {
        event.preventDefault();
        redo();
      }
    },
    { isDisabled: !hasRedo }
  );
  useShortcut('campaignbridge/editor/save', event => {
    event.preventDefault();
    void onSave();
  });

  return null;
}
