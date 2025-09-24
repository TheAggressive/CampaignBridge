/**
 * useSyncInnerBlocks
 * ------------------
 * Sync a block's InnerBlocks to a template without losing selection or user styles,
 * and without causing render loops. Replaces children only when the "structure"
 * actually changes; cosmetic updates should be handled with updateBlockAttributes
 * elsewhere in your component.
 *
 * Usage:
 *   const structureKey = `${showMore ? 1 : 0}|${moreStyle}`; // only flips on real structure changes
 *   const { resetToTemplate } = useSyncInnerBlocks(clientId, template, showMore, {
 *     structureKey,
 *     lockTemplate: true,          // call synchronizeTemplate after replace
 *     clearOnDisable: true,        // remove children when enabled=false
 *     keepParentSelected: true,    // reselect parent if no child is active
 *     debounceMs: 0,               // 0 = no debounce (usually fine)
 *     preserveAttrs: defaultPreserveAttrs, // merge strategy for user styles
 *   });
 *
 * Notes:
 * - `template` must be an InnerBlocks template array (see @wordpress/blocks docs).
 * - Do NOT include `template` or `children` in the effect deps; that causes loops.
 */

import { store as blockEditorStore } from "@wordpress/block-editor";
import {
  cloneBlock,
  createBlocksFromInnerBlocksTemplate,
} from "@wordpress/blocks";
import { useDebounce } from "@wordpress/compose";
import { useDispatch } from "@wordpress/data";
import { useEffect } from "@wordpress/element";

/** ----- Utilities -------------------------------------------------------- */

/**
 * Create a lightweight "shape" signature from a blocks array:
 * only names and nested structure, not attributes or clientIds.
 */
function shapeOfBlocks(blocks = []) {
  return blocks.map((b) => ({
    n: b.name,
    c: shapeOfBlocks(b.innerBlocks || []),
  }));
}

/** Deep compare two shapes built by shapeOfBlocks(). */
function shapesEqual(a, b) {
  if (a === b) return true;
  if (!Array.isArray(a) || !Array.isArray(b) || a.length !== b.length)
    return false;
  for (let i = 0; i < a.length; i++) {
    if (a[i].n !== b[i].n) return false;
    if (!shapesEqual(a[i].c, b[i].c)) return false;
  }
  return true;
}

/**
 * Default attribute-preserve strategy:
 * when replacing children, keep common style-ish attrs from previous blocks.
 * You can pass your own `preserveAttrs(nextAttrs, prevAttrs)` in options.
 */
export function defaultPreserveAttrs(next = {}, prev = {}) {
  if (!prev) return next;
  const keep = [
    "style", // includes border, spacing, color, etc.
    "textColor",
    "backgroundColor",
    "gradient",
    "className",
    "fontSize",
    "width", // core/button width %
    // add more keys you want to preserve
  ];
  const merged = { ...next };
  for (const k of keep) {
    if (prev[k] !== undefined) merged[k] = prev[k];
  }
  return merged;
}

/**
 * Merge attributes from previous (editor) blocks into a set of template blocks.
 * Matches blocks by name and position first; if mismatch, falls back to first
 * unused block with the same name. Recurses through innerBlocks.
 */
function mergeAttrsFromPrev(templateBlocks, prevBlocks, preserveAttrs) {
  const used = new Set();

  function findMatchFor(tBlock, indexHint) {
    // optimistic: same index & same name
    const hinted = prevBlocks[indexHint];
    if (hinted && hinted.name === tBlock.name && !used.has(indexHint)) {
      used.add(indexHint);
      return hinted;
    }
    // fallback: first unused with same name
    for (let i = 0; i < prevBlocks.length; i++) {
      if (!used.has(i) && prevBlocks[i].name === tBlock.name) {
        used.add(i);
        return prevBlocks[i];
      }
    }
    return null;
  }

  return templateBlocks.map((tb, i) => {
    const match = findMatchFor(tb, i);

    // Recurse for children first so we can pass merged innerBlocks to cloneBlock.
    const mergedChildren = mergeAttrsFromPrev(
      tb.innerBlocks || [],
      match?.innerBlocks || [],
      preserveAttrs,
    );

    const nextAttrs = preserveAttrs
      ? preserveAttrs(tb.attributes, match?.attributes)
      : tb.attributes;

    // cloneBlock(block, attributes?, innerBlocks?)
    return cloneBlock(tb, nextAttrs, mergedChildren);
  });
}

/** Build blocks from template safely (always returns an array). */
function blocksFromTemplate(template) {
  try {
    return createBlocksFromInnerBlocksTemplate(template || []);
  } catch {
    return [];
  }
}

/** ----- Hook ------------------------------------------------------------- */

/**
 * @param {string}  clientId                     Parent block clientId.
 * @param {Array}   template                     InnerBlocks template array (structure only).
 * @param {boolean} enabled                      Whether the template area is enabled (e.g., showMore && link).
 * @param {Object}  options
 * @param {string}  [options.structureKey]       A coarse key that changes only when structure changes (RECOMMENDED).
 * @param {boolean} [options.lockTemplate=false] Call synchronizeTemplate(clientId, template).
 * @param {boolean} [options.clearOnDisable=true]Replace children with [] when enabled === false.
 * @param {boolean} [options.keepParentSelected=true] Reselect parent if no child is selected after replace.
 * @param {number}  [options.debounceMs=0]       Debounce replaces; 0 = no debounce.
 * @param {(nextAttrs:any, prevAttrs:any)=>any} [options.preserveAttrs=defaultPreserveAttrs]
 * @returns {{ resetToTemplate: (force?:boolean)=>void }}
 */
export function useSyncInnerBlocks(
  clientId,
  template,
  enabled,
  {
    structureKey, // e.g., `${enabled?1:0}|${variant}`
    lockTemplate = false,
    clearOnDisable = true,
    keepParentSelected = true,
    debounceMs = 0,
    preserveAttrs = defaultPreserveAttrs,
  } = {},
) {
  const { replaceInnerBlocks, selectBlock, synchronizeTemplate } =
    useDispatch(blockEditorStore);

  // Debounced executor (optional). We keep it very small to avoid dependency churn.
  const schedule = useDebounce((fn) => fn(), Math.max(0, debounceMs));

  /**
   * Replace children with blocks derived from `template`, preserving user styles, and
   * keeping selection on the parent unless a child is already focused.
   */
  function doReplace(prevChildrenBlocks, tmpl) {
    const desired = blocksFromTemplate(tmpl);
    const merged = preserveAttrs
      ? mergeAttrsFromPrev(desired, prevChildrenBlocks || [], preserveAttrs)
      : desired;

    replaceInnerBlocks(clientId, merged, { updateSelection: false });

    if (lockTemplate) {
      try {
        synchronizeTemplate(clientId, tmpl || []);
      } catch {
        // synchronizeTemplate not available in some environments; ignore.
      }
    }

    if (keepParentSelected) {
      try {
        const state = wp.data.select(blockEditorStore); // eslint-disable-line no-undef
        const childSelected = state.hasSelectedInnerBlock(clientId, true);
        if (!childSelected) selectBlock(clientId);
      } catch {
        // wp.data might not be global in some test envs; best-effort only.
        selectBlock(clientId);
      }
    }
  }

  // 1) CLEAR on disable (once when enabled flips false)
  useEffect(() => {
    if (!clientId) return;
    if (enabled) return;
    if (!clearOnDisable) return;

    // Grab a one-time snapshot and clear children if any exist.
    try {
      const state = wp.data.select(blockEditorStore); // eslint-disable-line no-undef
      const current = state.getBlocks(clientId);
      if (current?.length) {
        replaceInnerBlocks(clientId, [], { updateSelection: false });
        if (lockTemplate) {
          try {
            synchronizeTemplate(clientId, []);
          } catch {}
        }
        if (
          keepParentSelected &&
          !state.hasSelectedInnerBlock(clientId, true)
        ) {
          selectBlock(clientId);
        }
      }
    } catch {
      // If wp.data not present, still attempt a clear.
      replaceInnerBlocks(clientId, [], { updateSelection: false });
      if (lockTemplate) {
        try {
          synchronizeTemplate(clientId, []);
        } catch {}
      }
      selectBlock(clientId);
    }
  }, [
    clientId,
    enabled,
    clearOnDisable,
    lockTemplate,
    keepParentSelected,
    replaceInnerBlocks,
    selectBlock,
    synchronizeTemplate,
  ]);

  // 2) REPLACE when structure changes (drive by structureKey)
  useEffect(() => {
    if (!clientId) return;
    if (!enabled) return;

    // Take a snapshot of current children and compute desired shape from the template.
    let state;
    try {
      state = wp.data.select(blockEditorStore); // eslint-disable-line no-undef
    } catch {
      state = null;
    }

    const currentBlocks = state ? state.getBlocks(clientId) : [];
    const currentShape = shapeOfBlocks(currentBlocks);
    const desiredBlocks = blocksFromTemplate(template);
    const desiredShape = shapeOfBlocks(desiredBlocks);

    // No-op: structure already matches -> do nothing (prevents render churn).
    if (shapesEqual(currentShape, desiredShape)) return;

    const run = () => doReplace(currentBlocks, template);
    if (debounceMs > 0) {
      schedule(run);
    } else {
      run();
    }
    // IMPORTANT: deps are intentionally minimal to avoid loops.
    // - structureKey should only change on real shape differences
    // - do NOT include `template` or `currentBlocks` in deps
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clientId, enabled, structureKey, debounceMs]);

  /**
   * Manual reset helper (e.g., for a "Reset" button).
   * If force=true, runs even if shapes already match.
   */
  function resetToTemplate(force = false) {
    try {
      const state = wp.data.select(blockEditorStore); // eslint-disable-line no-undef
      const currentBlocks = state.getBlocks(clientId);
      if (!force) {
        const currentShape = shapeOfBlocks(currentBlocks);
        const desiredShape = shapeOfBlocks(blocksFromTemplate(template));
        if (shapesEqual(currentShape, desiredShape)) return;
      }
      doReplace(currentBlocks, template);
    } catch {
      // fallback if wp.data not accessible
      doReplace([], template);
    }
  }

  return { resetToTemplate };
}
