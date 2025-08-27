import { BlockEditorKeyboardShortcuts } from "@wordpress/block-editor";
import { Popover, SlotFillProvider } from "@wordpress/components";
import { ShortcutProvider } from "@wordpress/keyboard-shortcuts";

/**
 * Editor chrome component that provides necessary context providers for the block editor.
 *
 * Sets up the required providers for keyboard shortcuts, slot/fill system,
 * and block editor keyboard shortcuts to ensure the editor functions properly.
 *
 * @param {Object} props - Component props
 * @param {React.ReactNode} props.children - Child components to render within the providers
 * @returns {JSX.Element} The editor chrome with context providers
 */
export default function EditorChrome({ children }) {
  return (
    <ShortcutProvider>
      <SlotFillProvider>
        <BlockEditorKeyboardShortcuts />
        {children}
        <Popover.Slot />
      </SlotFillProvider>
    </ShortcutProvider>
  );
}
