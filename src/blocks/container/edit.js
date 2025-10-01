/**
 * Editor UI for cb/container
 * - Uses core color panel (supports.color) for background/text colors.
 * - Keeps BoxControls for outerPadding (gutter) and inner padding.
 * - Locks the block (single, undeletable/unmovable).
 * - Mounts InnerBlocks via useInnerArea hook for a centered appender + minHeight.
 */

import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from "@wordpress/block-editor";
import {
  __experimentalBoxControl as BoxControl,
  PanelBody,
  RangeControl,
} from "@wordpress/components";
import { useDispatch } from "@wordpress/data";
import { useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { useBlockSelection } from "../../scripts/editor/hooks/useBlockSelection";

export default function Edit({ attributes, setAttributes, clientId }) {
  const { maxWidth, outerPadding, padding } = attributes;
  const { updateBlockAttributes } = useDispatch("core/block-editor");
  const { hasInnerBlocks } = useBlockSelection(clientId);

  const innerBlocksProps = useInnerBlocksProps(
    {
      className: "cb-email-container__inner",
      style: {
        maxWidth: maxWidth,
        margin: "0 auto",
      },
    },
    {
      allowedBlocks: [
        "campaignbridge/post-excerpt",
        "campaignbridge/post-image",
        "campaignbridge/post-cta",
        "campaignbridge/post-card",
      ],
      templateLock: false,
      renderAppender: hasInnerBlocks
        ? InnerBlocks.DefaultBlockAppender
        : InnerBlocks.ButtonBlockAppender,
    },
  );

  // Hard lock: cannot remove or move the root container
  useEffect(() => {
    updateBlockAttributes(clientId, { lock: { remove: true, move: false } });
  }, [clientId, updateBlockAttributes]);

  // Note: Background/Text colors come from core color support UI
  const blockProps = useBlockProps({
    style: {
      padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
    },
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

      <div {...blockProps}>
        <div {...innerBlocksProps} />
      </div>
    </div>
  );
}
