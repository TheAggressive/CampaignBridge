// utils/registerCustomBlocks.js
import { registerBlockType } from "@wordpress/blocks";

/**
 * Auto-register custom blocks from the built dist/blocks folder.
 * Each block folder in dist/blocks must have:
 *   - block.json
 *   - index.js (built editor script)
 */
export function registerCustomBlocks() {
  // Look for all block.json files inside dist/blocks
  const jsonCtx = require.context(
    // relative to this file’s compiled location
    "../../../../dist/blocks",
    true,
    /block\.json$/,
  );

  jsonCtx.keys().forEach((jsonPath) => {
    const metadata = jsonCtx(jsonPath);
    const dir = jsonPath.replace(/^\.\/|\/block\.json$/g, "");

    let settings = {};
    try {
      // Each block’s built index.js is required here.
      // eslint-disable-next-line import/no-dynamic-require, global-require
      const mod = require(`../../../../dist/blocks/${dir}/index.js`);
      settings = mod.default ?? mod;
    } catch (e) {
      // ok if block is metadata-only
      console.error(e);
    }

    registerBlockType(metadata.name, { ...metadata, ...settings });
  });
}
