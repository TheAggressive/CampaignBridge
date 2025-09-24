import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
  store as blockEditorStore,
} from "@wordpress/block-editor";
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from "@wordpress/components";
import { useDispatch, useSelect, select } from "@wordpress/data";
import { useEffect, useMemo } from "@wordpress/element";
import { decodeEntities } from "@wordpress/html-entities";
import { createBlocksFromInnerBlocksTemplate } from "@wordpress/blocks";
import { __ } from "@wordpress/i18n";

/** ------------------ Constants ------------------ */

const DEFAULT_ATTRIBUTES = {
  maxWords: 50,
  showMore: false,
  moreStyle: "link", // 'link' | 'button'
  moreLabel: "Read more",
  linkTo: "post", // 'post' | 'postType' (PHP decides final URL)
  morePrefix: "",
  addSpaceBeforeLink: true,
  enableSeparator: false,
  separatorType: "custom",
  customSeparator: "",
  addSpaceBeforeSeparator: false,
  // Button cosmetics
  buttonRadius: 4,
  buttonPaddingX: 16,
  buttonPaddingY: 10,
  buttonLayout: "new-line", // 'new-line' | 'full-width' | 'inline'
  buttonAlignment: "left", // 'left' | 'center' | 'right'
};

const SEPARATOR_OPTIONS = [
  { label: "Custom", value: "custom" },
  { label: "Em dash (—)", value: "em-dash" },
  { label: "En dash (–)", value: "en-dash" },
  { label: "Ellipsis (...)", value: "ellipsis" },
  { label: "Pipe (|)", value: "pipe" },
  { label: "Colon (:)", value: "colon" },
  { label: "Semicolon (;)", value: "semicolon" },
  { label: "Bullet (•)", value: "bullet" },
];

const SEPARATOR_MAPPING = {
  "em-dash": "—",
  "en-dash": "–",
  ellipsis: "...",
  pipe: "|",
  colon: ":",
  semicolon: ";",
  bullet: "•",
};

const LINK_TO_OPTIONS = [
  { label: "Post", value: "post" },
  { label: "Post Type", value: "postType" },
];

const MORE_STYLE_OPTIONS = [
  { label: "Link", value: "link" },
  { label: "Button", value: "button" },
];

/** ------------------ Helpers ------------------ */

// Compare block *structure* only (names + nesting), not attributes
const shape = (blocks = []) =>
  blocks.map((b) => ({ n: b.name, c: shape(b.innerBlocks || []) }));

const sameStructure = (a = [], b = []) => {
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; i++) {
    if (a[i].n !== b[i].n) return false;
    if (!sameStructure(a[i].c, b[i].c)) return false;
  }
  return true;
};

/** ------------------ Component ------------------ */

export default function Edit({
  attributes,
  setAttributes,
  context = {},
  clientId,
}) {
  const {
    maxWords,
    showMore,
    moreStyle,
    moreLabel,
    linkTo,
    morePrefix, // currently not used in this edit preview, preserved for attributes
    addSpaceBeforeLink,
    enableSeparator,
    separatorType,
    customSeparator,
    addSpaceBeforeSeparator,
    buttonRadius,
    buttonPaddingX,
    buttonPaddingY,
    buttonLayout,
    buttonAlignment,
  } = { ...DEFAULT_ATTRIBUTES, ...attributes };

  const postId = Number(context["campaignbridge:postId"]) || 0;
  const postType = context["campaignbridge:postType"] || "post";

  // Get post data just to render an excerpt preview in the editor
  const post = useSelect(
    (s) =>
      postId ? s("core").getEntityRecord("postType", postType, postId) : null,
    [postType, postId],
  );

  const raw = post?.excerpt?.rendered || post?.content?.rendered || "";
  const text = decodeEntities(raw)
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim();
  const words = text.split(/\s+/).filter(Boolean);
  let excerpt = words.slice(0, maxWords).join(" ");
  if (showMore && excerpt.endsWith(".")) excerpt = excerpt.slice(0, -1).trim();

  // Template builder (STRUCTURE ONLY). Use '#' in editor; PHP replaces with real link.
  const getTemplate = () => {
    if (!showMore) return [];

    if (moreStyle === "button") {
      const justify =
        buttonAlignment === "center"
          ? "center"
          : buttonAlignment === "right"
            ? "flex-end"
            : "flex-start";

      const buttonAttrs = {
        text: moreLabel || "Read more",
        url: "#", // placeholder; PHP swaps to permalink/archive
        style: {
          border: { radius: buttonRadius || 0 },
          spacing: {
            padding: {
              top: buttonPaddingY || 10,
              bottom: buttonPaddingY || 10,
              left: buttonPaddingX || 16,
              right: buttonPaddingX || 16,
            },
          },
        },
      };
      if (buttonLayout === "full-width") {
        buttonAttrs.width = 100; // core/button width %
      }

      return [
        [
          "core/buttons",
          { layout: { type: "flex", justifyContent: justify } },
          [["core/button", buttonAttrs]],
        ],
      ];
    }

    // Link variant: paragraph with align + anchor
    const align =
      buttonAlignment === "center"
        ? "center"
        : buttonAlignment === "right"
          ? "right"
          : "left";

    return [
      [
        "core/paragraph",
        { align, content: `<a href="#">${moreLabel || "Read more"}</a>` },
      ],
    ];
  };

  const props = useBlockProps({ style: { fontSize: 14, lineHeight: 1.5 } });

  // Memoize *only* on structure-affecting inputs
  const template = useMemo(getTemplate, [
    showMore,
    moreStyle,
    moreLabel,
    buttonRadius,
    buttonPaddingX,
    buttonPaddingY,
    buttonLayout,
    buttonAlignment,
  ]);

  const { replaceInnerBlocks, selectBlock, updateBlockAttributes } =
    useDispatch(blockEditorStore);

  // Key that flips only on structure changes
  const structureKey = `${showMore ? 1 : 0}|${moreStyle}`;

  /** Replace children ONLY when the structure changes (and avoid no-op replaces). */
  useEffect(() => {
    if (!clientId) return;

    const desired = createBlocksFromInnerBlocksTemplate(template || []);
    const current = select(blockEditorStore).getBlocks(clientId);

    if (sameStructure(shape(current), shape(desired))) return;

    replaceInnerBlocks(clientId, desired, { updateSelection: false });
    const inside = select(blockEditorStore).hasSelectedInnerBlock(
      clientId,
      true,
    );
    if (!inside) selectBlock(clientId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clientId, structureKey]);

  /** Cosmetic updates (no replace): alignments, label, padding, radius, width */
  useEffect(() => {
    if (!showMore) return;

    const state = select(blockEditorStore);
    const kids = state.getBlocks(clientId);

    if (moreStyle === "button") {
      const wrapper = kids.find((b) => b.name === "core/buttons");
      const button = wrapper?.innerBlocks?.find(
        (b) => b.name === "core/button",
      );

      if (wrapper) {
        const wantJustify =
          buttonAlignment === "center"
            ? "center"
            : buttonAlignment === "right"
              ? "flex-end"
              : "flex-start";
        const haveJustify = wrapper.attributes?.layout?.justifyContent;
        if (haveJustify !== wantJustify) {
          updateBlockAttributes(wrapper.clientId, {
            layout: { type: "flex", justifyContent: wantJustify },
          });
        }
      }

      if (button) {
        const next = {
          text: moreLabel || "Read more",
          url: "#",
          style: {
            border: { radius: buttonRadius || 0 },
            spacing: {
              padding: {
                top: buttonPaddingY || 10,
                bottom: buttonPaddingY || 10,
                left: buttonPaddingX || 16,
                right: buttonPaddingX || 16,
              },
            },
          },
          ...(buttonLayout === "full-width" ? { width: 100 } : {}),
        };

        const cur = {
          text: button.attributes?.text,
          url: button.attributes?.url,
          style: button.attributes?.style,
          width: button.attributes?.width,
        };

        if (JSON.stringify(cur) !== JSON.stringify(next)) {
          updateBlockAttributes(button.clientId, next);
        }
      }
    } else {
      const para = kids.find((b) => b.name === "core/paragraph");
      if (para) {
        const wantAlign =
          buttonAlignment === "center"
            ? "center"
            : buttonAlignment === "right"
              ? "right"
              : "left";
        const wantContent = `<a href="#">${moreLabel || "Read more"}</a>`;
        if (
          para.attributes?.align !== wantAlign ||
          para.attributes?.content !== wantContent
        ) {
          updateBlockAttributes(para.clientId, {
            align: wantAlign,
            content: wantContent,
          });
        }
      }
    }
  }, [
    clientId,
    showMore,
    moreStyle,
    moreLabel,
    buttonAlignment,
    buttonLayout,
    buttonRadius,
    buttonPaddingX,
    buttonPaddingY,
    updateBlockAttributes,
  ]);

  // Separator (editor preview string)
  const sep =
    enableSeparator && customSeparator
      ? `${addSpaceBeforeSeparator ? " " : ""}${customSeparator} `
      : "";

  return (
    <div {...props}>
      <InspectorControls>
        <PanelBody title="Excerpt & More Link" initialOpen>
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label="Max words"
            value={Number(maxWords) || 0}
            onChange={(v) => setAttributes({ maxWords: Number(v) || 0 })}
            min={10}
            max={150}
            step={1}
          />

          <ToggleControl
            __nextHasNoMarginBottom
            label={__("Show Link/Button", "campaignbridge")}
            checked={!!showMore}
            onChange={(v) => setAttributes({ showMore: !!v })}
          />

          {showMore && (
            <>
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__("Style", "campaignbridge")}
                value={moreStyle}
                options={MORE_STYLE_OPTIONS}
                onChange={(v) => setAttributes({ moreStyle: v })}
              />
              <TextControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__("Label", "campaignbridge")}
                value={moreLabel}
                onChange={(v) => setAttributes({ moreLabel: v })}
              />
              <ToggleControl
                __nextHasNoMarginBottom
                label={__("Add space before link", "campaignbridge")}
                checked={!!addSpaceBeforeLink}
                onChange={(v) => setAttributes({ addSpaceBeforeLink: !!v })}
                help={
                  enableSeparator
                    ? "Not used when separator is enabled"
                    : "Adds spacing between the excerpt text and the read more link"
                }
              />
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__("Link to", "campaignbridge")}
                value={linkTo}
                options={LINK_TO_OPTIONS}
                onChange={(v) => setAttributes({ linkTo: v })}
              />
            </>
          )}
        </PanelBody>

        <PanelBody title="Separator" initialOpen={false}>
          <ToggleControl
            __nextHasNoMarginBottom
            label={__("Enable separator", "campaignbridge")}
            checked={!!enableSeparator}
            onChange={(v) => setAttributes({ enableSeparator: !!v })}
            help="Add a separator between the excerpt text and the read more link"
          />
          {enableSeparator && (
            <>
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__("Separator type", "campaignbridge")}
                value={separatorType}
                options={SEPARATOR_OPTIONS}
                onChange={(value) => {
                  setAttributes({ separatorType: value });
                  setAttributes({
                    customSeparator: SEPARATOR_MAPPING[value] || "",
                  });
                }}
              />
              {separatorType === "custom" && (
                <TextControl
                  __next40pxDefaultSize
                  __nextHasNoMarginBottom
                  label={__("Custom separator", "campaignbridge")}
                  value={customSeparator}
                  onChange={(v) => setAttributes({ customSeparator: v })}
                  help="Enter your own separator text"
                  placeholder="e.g., |, —, •, etc."
                />
              )}
              <ToggleControl
                __nextHasNoMarginBottom
                label={__("Add space before separator", "campaignbridge")}
                checked={!!addSpaceBeforeSeparator}
                onChange={(v) =>
                  setAttributes({ addSpaceBeforeSeparator: !!v })
                }
                help="Adds a space between the excerpt text and the separator"
              />
            </>
          )}
        </PanelBody>
      </InspectorControls>

      {!!excerpt && (
        <>
          {excerpt}
          {enableSeparator && customSeparator ? sep : ""}
          {addSpaceBeforeLink && !enableSeparator && showMore ? " " : ""}
          {showMore && (
            <InnerBlocks
              templateLock="all"
              allowedBlocks={["core/paragraph", "core/buttons", "core/button"]}
              templateInsertUpdatesSelection={false}
            />
          )}
        </>
      )}
    </div>
  );
}
