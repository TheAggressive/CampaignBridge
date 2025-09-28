/**
 * Editor UI for cb/container
 * - Uses core color panel (supports.color) for background/text colors.
 * - Keeps BoxControls for outerPadding (gutter) and inner padding.
 * - Locks the block (single, undeletable/unmovable).
 */

import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from "@wordpress/block-editor";
import {
  __experimentalBoxControl as BoxControl,
  PanelBody,
  RangeControl,
} from "@wordpress/components";
import { useDispatch } from "@wordpress/data";
import { useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

export default function Edit({ attributes, setAttributes, clientId }) {
  const { maxWidth, outerPadding, padding } = attributes;
  const { updateBlockAttributes } = useDispatch("core/block-editor");

  // Hard lock: cannot remove or move the root container
  useEffect(() => {
    updateBlockAttributes(clientId, { lock: { remove: true, move: false } });
  }, [clientId, updateBlockAttributes]);

  // Note: Background/Text colors come from core color support UI
  const blockProps = useBlockProps({
    className: "cb-email-container",
    // Editor-only hint (kept minimal; actual email styles come from render.php)
    style: { outline: "1px dashed #e5e7eb" },
  });

  return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody title={__("Container", "campaignbridge")} initialOpen>
          <RangeControl
            label={__("Max width (px)", "campaignbridge")}
            min={320}
            max={900}
            value={maxWidth}
            onChange={(v) => setAttributes({ maxWidth: v })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <BoxControl
            label={__("Outer padding (around inner table)", "campaignbridge")}
            values={outerPadding}
            onChange={(vals) => setAttributes({ outerPadding: vals })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <BoxControl
            label={__("Inner padding (content area)", "campaignbridge")}
            values={padding}
            onChange={(vals) => setAttributes({ padding: vals })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>

      {/* Editor scaffold (for authoring). Final email HTML is rendered in PHP. */}
      <div
        style={{
          margin: "0 auto",
          maxWidth,
          padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
        }}
      >
        <InnerBlocks
          allowedBlocks={[
            "campaignbridge/section",
            "campaignbridge/columns",
            "campaignbridge/heading",
            "campaignbridge/paragraph",
            "campaignbridge/image",
            "campaignbridge/button",
            "campaignbridge/post-card",
          ]}
          templateLock={false}
        />
      </div>
    </div>
  );
}
