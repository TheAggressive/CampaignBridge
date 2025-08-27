/**
 * Waits for WordPress block types to be registered before proceeding.
 *
 * Polls the wp.blocks.getBlockTypes() function until a minimum number
 * of block types are available or a timeout is reached. This ensures
 * that all necessary blocks are loaded before initializing the editor.
 *
 * @param {Object} [options={}] - Configuration options
 * @param {number} [options.min=40] - Minimum number of block types required
 * @param {number} [options.timeoutMs=5000] - Maximum time to wait in milliseconds
 * @returns {Promise<void>} Resolves when minimum block types are available
 */
export async function waitForBlocksRegistered({
  min = 40,
  timeoutMs = 5000,
} = {}) {
  const start = Date.now();
  // wp.blocks is available when you enqueue core/editor scripts
  while (Date.now() - start < timeoutMs) {
    const count = (window.wp?.blocks?.getBlockTypes?.() || []).length;
    if (count >= min) return;
    await new Promise((r) => setTimeout(r, 50));
  }
}
