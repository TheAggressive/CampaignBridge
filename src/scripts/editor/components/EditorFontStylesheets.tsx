import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import {
  requiredEditorFontUrls,
  syncEditorFontStylesheets,
  type EditorFontBlock,
} from '../editor-font-assets';
import { editorDesignConfig } from '../editor-design-config';

interface BlockEditorSelectors {
  getBlocks: () => EditorFontBlock[];
}

/** Load only effective CampaignBridge web fonts into Core's editor iframe. */
export default function EditorFontStylesheets(): null {
  const blocks = useSelect(select => {
    const editor = select(blockEditorStore) as unknown as BlockEditorSelectors;
    return editor.getBlocks();
  }, []);
  const urls = useMemo(
    () =>
      requiredEditorFontUrls(blocks, {
        campaignbridgeFontAssets: editorDesignConfig.fontAssets,
        campaignbridgeDefaultFonts: editorDesignConfig.defaultFonts,
      }),
    [blocks]
  );

  useEffect(() => {
    let canvasDocument: Document | null = null;
    let iframe: HTMLIFrameElement | null = null;

    const apply = () => {
      const next = document.querySelector<HTMLIFrameElement>(
        'iframe[name="editor-canvas"]'
      );
      if (!next?.contentDocument) return;

      if (next !== iframe) {
        iframe?.removeEventListener('load', onLoad);
        iframe = next;
        iframe.addEventListener('load', onLoad);
      }
      canvasDocument = next.contentDocument;
      syncEditorFontStylesheets(canvasDocument, urls);
    };
    const onLoad = () => apply();
    const observer = new MutationObserver(apply);

    apply();
    observer.observe(document.body, { childList: true, subtree: true });

    return () => {
      observer.disconnect();
      iframe?.removeEventListener('load', onLoad);
      if (canvasDocument) syncEditorFontStylesheets(canvasDocument, []);
    };
  }, [urls]);

  return null;
}
