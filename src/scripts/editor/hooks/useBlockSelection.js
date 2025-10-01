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

import { useSelect } from "@wordpress/data";

export function useBlockSelection(clientId, { deep = true } = {}) {
  return useSelect(
    (select) => {
      const be = select("core/block-editor");

      const block = be.getBlock(clientId);
      const hasInnerBlocks = !!block?.innerBlocks?.length;

      const isSelfSelected = be.isBlockSelected(clientId);
      const hasSelectedDescendant = deep
        ? be.hasSelectedInnerBlock(clientId, true)
        : false;

      const selectedClientId = be.getSelectedBlockClientId() || null;

      return {
        isSelected: !!(isSelfSelected || hasSelectedDescendant),
        hasInnerBlocks,
        isSelfSelected,
        hasSelectedDescendant,
        selectedClientId,
      };
    },
    [clientId, deep],
  );
}
