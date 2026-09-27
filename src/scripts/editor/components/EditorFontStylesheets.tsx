import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import {
  requiredEditorFontUrls,
  syncEditorFontStylesheets,
  syncEditorTypeFontStyles,
  type EditorFontBlock,
} from '../editor-font-assets';
import { editorDesignConfig } from '../editor-design-config';
import { editorFontOptions, type DesignFontRegistry } from '../design-fonts';

interface BlockEditorSelectors {
  getBlocks: () => EditorFontBlock[];
}

/** Load only effective CampaignBridge web fonts into Core's editor iframe. */
export default function EditorFontStylesheets({
  registry,
}: {
  registry: DesignFontRegistry;
}): null {
  const blocks = useSelect(select => {
    const editor = select(blockEditorStore) as unknown as BlockEditorSelectors;
    return editor.getBlocks();
  }, []);
  const { families, slots } = useMemo(
    () => ({
      families: Object.fromEntries(
        editorFontOptions().map(font => [font.slug, font.fontFamily])
      ),
      slots: {
        ...editorDesignConfig.baseFontSlots,
        ...registry.slots,
      },
    }),
    [registry]
  );
  const urls = useMemo(
    () =>
      requiredEditorFontUrls(blocks, {
        campaignbridgeFontAssets: {
          ...editorDesignConfig.fontAssets,
          ...Object.fromEntries(
            registry.fonts.map(font => [font.slug, font.url])
          ),
        },
        campaignbridgeDefaultFonts: editorDesignConfig.defaultFonts,
      }),
    [blocks, registry]
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
      syncEditorTypeFontStyles(canvasDocument, families, slots);
    };
    const onLoad = () => apply();
    const observer = new MutationObserver(apply);

    apply();
    observer.observe(document.body, { childList: true, subtree: true });

    return () => {
      observer.disconnect();
      iframe?.removeEventListener('load', onLoad);
      if (canvasDocument) {
        syncEditorFontStylesheets(canvasDocument, []);
        syncEditorTypeFontStyles(canvasDocument, {}, {});
      }
    };
  }, [families, slots, urls]);

  return null;
}
