import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from "@wordpress/block-editor";
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useMemo } from "@wordpress/element";
import { useExcerptPreview } from "./hooks/useExcerptPreview";
import { useReadMoreTemplate } from "./hooks/useReadMoreTemplate";
import { useSyncReadMoreStructure } from "./hooks/useSyncReadMoreStructure";
import { useInlinePlacementClass } from "./hooks/useInlinePlacementClass";

const D = {
  maxWords: 50,
  showMore: false,
  moreStyle: "link",
  morePlacement: "new-line",
  linkTo: "post",
  addSpaceBeforeLink: true,
  enableSeparator: false,
  separatorType: "custom",
  customSeparator: "",
  addSpaceBeforeSeparator: false,
};

const SEP_OPTIONS = [
  { label: __("Custom", "campaignbridge"), value: "custom" },
  { label: __("Em dash (—)", "campaignbridge"), value: "em-dash" },
  { label: __("En dash (–)", "campaignbridge"), value: "en-dash" },
  { label: __("Ellipsis (...)", "campaignbridge"), value: "ellipsis" },
  { label: __("Pipe (|)", "campaignbridge"), value: "pipe" },
  { label: __("Colon (:)", "campaignbridge"), value: "colon" },
  { label: __("Semicolon (;)", "campaignbridge"), value: "semicolon" },
  { label: __("Bullet (•)", "campaignbridge"), value: "bullet" },
];
const SEP_MAP = {
  "em-dash": "—",
  "en-dash": "–",
  ellipsis: "...",
  pipe: "|",
  colon: ":",
  semicolon: ";",
  bullet: "•",
};

const LINK_TO_OPTIONS = [
  { label: __("Post", "campaignbridge"), value: "post" },
  { label: __("Post Type", "campaignbridge"), value: "postType" },
];

const STYLE_OPTIONS = [
  { label: __("Link", "campaignbridge"), value: "link" },
  { label: __("Button", "campaignbridge"), value: "button" },
];

const PLACEMENT_OPTIONS = [
  { label: __("New line", "campaignbridge"), value: "new-line" },
  { label: __("Inline (after excerpt)", "campaignbridge"), value: "inline" },
];

const ALLOWED = ["core/paragraph", "core/buttons", "core/button"];

export default function Edit({
  attributes,
  setAttributes,
  context = {},
  clientId,
}) {
  const a = { ...D, ...attributes };
  const postId = Number(context["campaignbridge:postId"]) || 0;
  const postType = context["campaignbridge:postType"] || "post";

  const excerpt = useExcerptPreview({
    postId,
    postType,
    maxWords: a.maxWords,
    showMore: a.showMore,
  });
  const template = useReadMoreTemplate({
    showMore: a.showMore,
    moreStyle: a.moreStyle,
    morePlacement: a.morePlacement,
  });

  useSyncReadMoreStructure(
    clientId,
    { showMore: a.showMore, moreStyle: a.moreStyle },
    template,
  );
  useInlinePlacementClass(clientId, {
    showMore: a.showMore,
    moreStyle: a.moreStyle,
    morePlacement: a.morePlacement,
  });

  const blockProps = useBlockProps({
    className: a.morePlacement === "inline" ? "has-inline-readmore" : undefined,
    style: { fontSize: 14, lineHeight: 1.5 },
  });

  const sep = useMemo(
    () =>
      a.enableSeparator && a.customSeparator
        ? `${a.addSpaceBeforeSeparator ? " " : ""}${a.customSeparator} `
        : "",
    [a.enableSeparator, a.customSeparator, a.addSpaceBeforeSeparator],
  );

  return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody
          title={__("Excerpt & More Link", "campaignbridge")}
          initialOpen
        >
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__("Max words", "campaignbridge")}
            value={Number(a.maxWords) || 0}
            onChange={(v) => setAttributes({ maxWords: Number(v) || 0 })}
            min={10}
            max={150}
            step={1}
          />

          <ToggleControl
            __nextHasNoMarginBottom
            label={__("Show more link/button", "campaignbridge")}
            checked={!!a.showMore}
            onChange={(v) => setAttributes({ showMore: !!v })}
          />

          {a.showMore && (
            <>
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__("Style", "campaignbridge")}
                value={a.moreStyle}
                options={STYLE_OPTIONS}
                onChange={(v) => setAttributes({ moreStyle: v })}
              />

              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__("Placement", "campaignbridge")}
                value={a.morePlacement}
                options={PLACEMENT_OPTIONS}
                onChange={(v) => setAttributes({ morePlacement: v })}
              />

              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__("Link to", "campaignbridge")}
                value={a.linkTo}
                options={LINK_TO_OPTIONS}
                onChange={(v) => setAttributes({ linkTo: v })}
              />

              <ToggleControl
                __nextHasNoMarginBottom
                label={__("Add space before link", "campaignbridge")}
                checked={!!a.addSpaceBeforeLink}
                onChange={(v) => setAttributes({ addSpaceBeforeLink: !!v })}
                help={
                  a.enableSeparator
                    ? __("Not used when separator is enabled", "campaignbridge")
                    : __(
                        "Adds spacing between the excerpt text and the read more link",
                        "campaignbridge",
                      )
                }
              />
            </>
          )}
        </PanelBody>

        <PanelBody
          title={__("Separator", "campaignbridge")}
          initialOpen={false}
        >
          <ToggleControl
            __nextHasNoMarginBottom
            label={__("Enable separator", "campaignbridge")}
            checked={!!a.enableSeparator}
            onChange={(v) => setAttributes({ enableSeparator: !!v })}
            help={__(
              "Add a separator between the excerpt text and the read more link",
              "campaignbridge",
            )}
          />

          {a.enableSeparator && (
            <>
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__("Separator type", "campaignbridge")}
                value={a.separatorType}
                options={SEP_OPTIONS}
                onChange={(value) =>
                  setAttributes({
                    separatorType: value,
                    customSeparator: SEP_MAP[value] || "",
                  })
                }
              />

              {a.separatorType === "custom" && (
                <TextControl
                  __next40pxDefaultSize
                  __nextHasNoMarginBottom
                  label={__("Custom separator", "campaignbridge")}
                  value={a.customSeparator}
                  onChange={(v) => setAttributes({ customSeparator: v })}
                  placeholder={__("e.g., |, —, •, etc.", "campaignbridge")}
                />
              )}

              <ToggleControl
                __nextHasNoMarginBottom
                label={__("Add space before separator", "campaignbridge")}
                checked={!!a.addSpaceBeforeSeparator}
                onChange={(v) =>
                  setAttributes({ addSpaceBeforeSeparator: !!v })
                }
              />
            </>
          )}
        </PanelBody>
      </InspectorControls>

      {!!excerpt && (
        <>
          {excerpt}
          {a.enableSeparator && a.customSeparator ? sep : ""}
          {!a.enableSeparator && a.addSpaceBeforeLink && a.showMore ? " " : ""}

          {a.showMore && (
            <InnerBlocks
              template={template} // seed only when empty
              templateLock="all"
              allowedBlocks={ALLOWED}
              templateInsertUpdatesSelection={false}
            />
          )}
        </>
      )}
    </div>
  );
}
