import { useSelect } from '@wordpress/data';

type WordPressBlockEditor = {
  getBlock?: (clientId: string) => { innerBlocks?: unknown[] } | undefined;
  isBlockSelected?: (clientId: string) => boolean;
  hasSelectedInnerBlock?: (clientId: string, deep?: boolean) => boolean;
  getSelectedBlockClientId?: () => string | null;
};

/**
 * Report whether a block or one of its descendants is selected and whether it
 * currently has children. Email container blocks use this to expose Core's
 * native InnerBlocks appender only while the container is active.
 */
export function useBlockSelection(clientId: string, { deep = true } = {}) {
  return useSelect(
    select => {
      const blockEditor = select('core/block-editor') as WordPressBlockEditor;
      const block = blockEditor.getBlock?.(clientId);
      const hasInnerBlocks = Boolean(block?.innerBlocks?.length);
      const isSelfSelected = blockEditor.isBlockSelected?.(clientId) ?? false;
      const hasSelectedDescendant = deep
        ? (blockEditor.hasSelectedInnerBlock?.(clientId, true) ?? false)
        : false;

      return {
        isSelected: isSelfSelected || hasSelectedDescendant,
        hasInnerBlocks,
        isSelfSelected,
        hasSelectedDescendant,
        selectedClientId: blockEditor.getSelectedBlockClientId?.() ?? null,
      };
    },
    [clientId, deep]
  );
}
