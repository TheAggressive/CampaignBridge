/**
 * useBlockSelection
 * -----------------------------------------------------------------------------
 * Tiny helper around core/block-editor selectors to drive editor UI.
 *
 * PURPOSE
 * - Tell if a block is "selected" (either the block itself OR any of its
 *   descendants is selected). Useful for conditionally showing the InnerBlocks
 *   appender only when the block is focused, like core layout blocks do.
 * - Tell if a block currently has any inner blocks (children).
 *
 * USAGE
 *   import { useBlockSelection } from '../../hooks/useBlockSelection';
 *
 *   export default function Edit({ clientId }) {
 *     const { isSelected, hasInnerBlocks } = useBlockSelection(clientId);
 *
 *     const renderAppender = isSelected
 *       ? (hasInnerBlocks ? InnerBlocks.DefaultBlockAppender   // popup chooser
 *                         : InnerBlocks.ButtonBlockAppender)   // big "+"
 *       : () => null;                                          // hide when not selected
 *     // ...
 *   }
 *
 * OPTIONS
 *   deep (boolean, default: true)
 *     If true, treats selection of ANY descendant as "selected".
 *     If false, only the block itself being selected counts.
 *
 * RETURNS
 *   {
 *     isSelected: boolean,            // self or (optionally) any descendant
 *     hasInnerBlocks: boolean,        // at least one child exists
 *     isSelfSelected: boolean,        // only this block is selected
 *     hasSelectedDescendant: boolean, // a child/grandchild is selected (when deep)
 *     selectedClientId: string|null,  // currently selected block clientId (debug/useful)
 *   }
 */

import { useSelect } from '@wordpress/data';

type WordPressBlockEditor = {
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  getBlock?: (clientId: string) => { innerBlocks?: unknown[] } | undefined;
  getBlocks?: () => unknown[];
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  isBlockSelected?: (clientId: string) => boolean;
  // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
  hasSelectedInnerBlock?: (clientId: string, deep?: boolean) => boolean;
  getSelectedBlockClientId?: () => string | null;
};

export function useBlockSelection(clientId: string, { deep = true } = {}) {
  return useSelect(
    select => {
      const be = select('core/block-editor') as WordPressBlockEditor;

      const block = be.getBlock?.(clientId);
      const hasInnerBlocks = !!block?.innerBlocks?.length;

      const isSelfSelected = be.isBlockSelected?.(clientId) ?? false;
      const hasSelectedDescendant = deep
        ? (be.hasSelectedInnerBlock?.(clientId, true) ?? false)
        : false;

      const selectedClientId = be.getSelectedBlockClientId?.() ?? null;

      return {
        isSelected: !!(isSelfSelected || hasSelectedDescendant),
        hasInnerBlocks,
        isSelfSelected,
        hasSelectedDescendant,
        selectedClientId,
      };
    },
    [clientId, deep]
  );
}
