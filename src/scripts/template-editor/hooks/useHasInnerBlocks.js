import { useSelect } from "@wordpress/data";

/**
 * Check if a block has any inner blocks (child blocks)
 * @param {string} clientId - The block's client ID
 * @returns {boolean} - True if the block has inner blocks, false otherwise
 */
export default function useHasInnerBlocks(clientId) {
  return useSelect(
    (select) => {
      const block = select("core/block-editor").getBlock(clientId);
      return !!block?.innerBlocks?.length;
    },
    [clientId],
  );
}
