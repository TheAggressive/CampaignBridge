/**
 * Custom hook for synchronizing InnerBlocks with dynamic templates.
 *
 * @param {string} clientId - The block's client ID
 * @param {Array} template - The template array to sync with
 * @param {boolean} enabled - Whether the feature is enabled
 * @param {Object} options - Additional options
 * @param {boolean} options.lockTemplate - Whether to lock the template structure (default: false)
 * @param {boolean} options.clearOnDisable - Whether to clear blocks when disabled (default: true)
 * @returns {Object} Hook utilities
 */
import { store as blockEditorStore } from "@wordpress/block-editor";
import { createBlocksFromInnerBlocksTemplate } from "@wordpress/blocks";
import { useDispatch, useSelect } from "@wordpress/data";
import { useCallback, useEffect, useRef } from "@wordpress/element";

// Simple focus restoration for the parent block
const restoreBlockSelection = (selectBlock, clientId, callback) => {
  // Execute the callback first
  const result = callback();

  // Immediately try to select the parent block
  try {
    selectBlock(clientId);
  } catch (e) {
    // If selection fails, that's okay
  }

  return result;
};

export function useSyncInnerBlocks(clientId, template, enabled, options = {}) {
  const { lockTemplate = false, clearOnDisable = true } = options;

  const { replaceInnerBlocks, updateBlockAttributes } =
    useDispatch(blockEditorStore);
  const { selectBlock } = useDispatch("core/block-editor");

  const lastTemplateRef = useRef();

  // Simple template comparison
  const templateChanged = () => {
    if (!enabled || !template) return false;

    const templateString = JSON.stringify(template);
    const lastTemplateString = JSON.stringify(lastTemplateRef.current);

    return templateString !== lastTemplateString;
  };

  // Get current inner blocks to compare with template
  const currentBlocks = useSelect(
    (select) => {
      if (!clientId || !enabled) return null;
      return select(blockEditorStore).getBlocks(clientId);
    },
    [clientId, enabled],
  );

  // Debounced sync function
  const syncInnerBlocks = useCallback(() => {
    if (!clientId || !enabled || !currentBlocks) return;

    // Store the new template
    lastTemplateRef.current = template;

    // Convert template to real block objects
    const templateBlocks = createBlocksFromInnerBlocksTemplate(template);

    // Check if structure changed (need to replace blocks)
    const structureChanged =
      currentBlocks.length !== templateBlocks.length ||
      !currentBlocks.every((block, index) => {
        const templateBlock = templateBlocks[index];
        return templateBlock && block.name === templateBlock[0];
      });

    if (structureChanged) {
      // Replace inner blocks if structure changed
      restoreBlockSelection(selectBlock, clientId, () => {
        replaceInnerBlocks(clientId, templateBlocks, {
          updateSelection: false,
        });
      });
    } else {
      // Update existing blocks' attributes instead of replacing them
      currentBlocks.forEach((block, index) => {
        const templateBlock = templateBlocks[index];
        if (templateBlock && block.name === templateBlock[0]) {
          // Update the block's attributes with the template values
          updateBlockAttributes(block.clientId, templateBlock[1]);
        }
      });
    }
  }, [
    clientId,
    enabled,
    template,
    currentBlocks,
    replaceInnerBlocks,
    selectBlock,
    updateBlockAttributes,
  ]);

  useEffect(() => {
    // Only run if template has changed
    if (!enabled || !templateChanged()) return;

    // Call sync function immediately (no debouncing)
    syncInnerBlocks();
  }, [enabled, template, syncInnerBlocks]);

  return {
    replaceInnerBlocks,
  };
}
