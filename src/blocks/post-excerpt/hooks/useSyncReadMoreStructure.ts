import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';
import { select, useDispatch } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const esc = (s = '') =>
  s.replace(
    /[&<>"']/g,
    m =>
      ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
      })[m]
  );

/**
 * Extract anchor text from a paragraph's content HTML
 * @param {string} html - HTML content to extract anchor text from
 * @return {string} The extracted anchor text
 */
const anchorText = (html = '') => {
  const m = /<a[^>]*>([\s\S]*?)<\/a>/i.exec(html);
  const inner = m ? m[1] : html;
  return inner.replace(/<[^>]*>/g, '').trim();
};

/**
 * Rebuild children ONLY when structure changes:
 *  - ShowMore ON/OFF
 *  - Link ↔ Button swap
 * Preserves the user's label when swapping.
 * @param {string}  clientId         - The block client ID
 * @param {Object}  params           - Hook parameters
 * @param {boolean} params.showMore  - Whether to show the read more element
 * @param {string}  params.moreStyle - Style of read more ('link' or 'button')
 * @param {Array}   template         - InnerBlocks template array
 */
export function useSyncReadMoreStructure(
  clientId,
  { showMore, moreStyle },
  template
) {
  const { replaceInnerBlocks, selectBlock } = useDispatch(blockEditorStore);
  const prevKeyRef = useRef(null);
  const structureKey = `${showMore ? 1 : 0}|${moreStyle}`;

  useEffect(() => {
    if (!clientId) {
      return;
    }

    // First render: honor saved content
    if (prevKeyRef.current === null) {
      prevKeyRef.current = structureKey;
      return;
    }
    if (prevKeyRef.current === structureKey) {
      return;
    }

    const state = select(blockEditorStore);
    const current = (state as any).getBlocks(clientId);

    const wasOn = prevKeyRef.current.startsWith('1|');
    const isOn = structureKey.startsWith('1|');

    // Turning OFF showMore -> clear children
    if (!isOn && wasOn) {
      if (current.length) {
        replaceInnerBlocks(clientId, [], { updateSelection: false });
        selectBlock(clientId);
      }
      prevKeyRef.current = structureKey;
      return;
    }

    // Enabled + maybe style changed: rebuild desired and migrate label text
    let userLabel = __('Read more', 'campaignbridge');
    const buttonsWrap = current.find(b => b.name === 'core/buttons');
    const btn = buttonsWrap?.innerBlocks?.find(b => b.name === 'core/button');
    if (btn?.attributes?.text) {
      userLabel = (String(btn.attributes.text).trim() || userLabel) as any;
    } else {
      const para = current.find(b => b.name === 'core/paragraph');
      if (para?.attributes?.content) {
        const t = anchorText(para.attributes.content);
        if (t) {
          userLabel = t as any;
        }
      }
    }

    const desired = createBlocksFromInnerBlocksTemplate(template || []);
    if (moreStyle === 'button') {
      const buttons = desired[0];
      const button = buttons?.innerBlocks?.[0];
      if (button) {
        (button.attributes as any) = { ...button.attributes, text: userLabel };
      }
    } else {
      const para = desired[0];
      if (para) {
        (para.attributes as any) = {
          ...para.attributes,
          content: `<a href="#">${esc(userLabel)}</a>`,
        };
      }
    }

    replaceInnerBlocks(clientId, desired, { updateSelection: false });
    prevKeyRef.current = structureKey;
  }, [
    clientId,
    structureKey,
    template,
    replaceInnerBlocks,
    selectBlock,
    moreStyle,
  ]);
}
