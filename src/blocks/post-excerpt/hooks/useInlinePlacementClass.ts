import { store as blockEditorStore } from '@wordpress/block-editor';
import { select, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * On placement change, toggle `is-inline-readmore` on the existing child
 * (paragraph or buttons wrapper). Never replaces children, so edits persist.
 * @param {string}  clientId             - The block client ID
 * @param {Object}  params               - Hook parameters
 * @param {boolean} params.showMore      - Whether to show the read more element
 * @param {string}  params.moreStyle     - Style of read more ('link' or 'button')
 * @param {string}  params.morePlacement - Placement of read more ('inline' or 'new-line')
 */
export function useInlinePlacementClass(
  clientId,
  { showMore, moreStyle, morePlacement }
) {
  const { updateBlockAttributes } = useDispatch(blockEditorStore);

  useEffect(() => {
    if (!clientId || !showMore) {
      return;
    }

    const kids = (select(blockEditorStore) as any).getBlocks(clientId);

    const toggle = block => {
      if (!block) {
        return;
      }
      const cur = block.attributes?.className || '';
      const parts = cur.split(/\s+/).filter(Boolean);
      const has = parts.includes('is-inline-readmore');
      const want = morePlacement === 'inline';
      if (want && !has) {
        updateBlockAttributes(block.clientId, {
          className: (cur + ' is-inline-readmore').trim(),
        });
      }
      if (!want && has) {
        updateBlockAttributes(block.clientId, {
          className:
            parts.filter(p => p !== 'is-inline-readmore').join(' ') ||
            undefined,
        });
      }
    };

    if (moreStyle === 'button') {
      toggle(kids.find(b => b.name === 'core/buttons'));
    } else {
      toggle(kids.find(b => b.name === 'core/paragraph'));
    }
  }, [clientId, showMore, moreStyle, morePlacement, updateBlockAttributes]);
}
