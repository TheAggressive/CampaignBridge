import {
  store as blockEditorStore,
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from "@wordpress/block-editor";
import { createBlocksFromInnerBlocksTemplate } from "@wordpress/blocks";
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from "@wordpress/components";
import { select, useDispatch, useSelect } from "@wordpress/data";
import { useEffect, useMemo } from "@wordpress/element";
import { decodeEntities } from "@wordpress/html-entities";
import { __ } from "@wordpress/i18n";

/** ------------------ Constants ------------------ */

const DEFAULT_ATTRIBUTES = {
  maxWords: 50,
  showMore: false,
  moreStyle: "link", // 'link' | 'button'
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

const escapeHtml = (s = "") =>
  s.replace(
    /[&<>"']/g,
    (m) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        m
      ],
  );

/** Extract the visible text from a paragraph’s anchor content. */
function extractLabelFromParagraph(html = "") {
  const m = /<a[^>]*>([\s\S]*?)<\/a>/i.exec(html);
  const inner = m ? m[1] : html;
  // decode entities already handled by Gutenberg; still strip tags/trim
  return inner.replace(/<[^>]*>/g, "").trim();
}

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
    linkTo,
    morePrefix, // not used in editor preview, preserved on attributes
    addSpaceBeforeLink,
    enableSeparator,
    separatorType,
    customSeparator,
    addSpaceBeforeSeparator,

    // button cosmetics
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
        text: "Read more", // will be replaced with user's current label on structure change
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
        { align, content: `<a href="#">Read more</a>` }, // will be replaced with user's current label on structure change
      ],
    ];
  };

  const props = useBlockProps({ style: { fontSize: 14, lineHeight: 1.5 } });

  // Memoize *only* on structure-affecting inputs
  const template = useMemo(getTemplate, [
    showMore,
    moreStyle,
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

  /** Replace children ONLY when the structure changes. Also migrate the user's label. */
  useEffect(() => {
    if (!clientId) return;

    const state = select(blockEditorStore);
    const current = state.getBlocks(clientId);

    // Pull the user's current label (from button.text or paragraph content)
    let userLabel = "Read more";
    const wrapper = current.find((b) => b.name === "core/buttons");
    const btn = wrapper?.innerBlocks?.find((b) => b.name === "core/button");
    if (btn?.attributes?.text) {
      userLabel = String(btn.attributes.text).trim() || userLabel;
    } else {
      const para = current.find((b) => b.name === "core/paragraph");
      if (para?.attributes?.content) {
        const extracted = extractLabelFromParagraph(para.attributes.content);
        if (extracted) userLabel = extracted;
      }
    }

    // Build desired template blocks and inject the user's label
    const desired = createBlocksFromInnerBlocksTemplate(template || []);
    if (moreStyle === "button") {
      const buttons = desired[0];
      const button = buttons?.innerBlocks?.[0];
      if (button) {
        button.attributes = {
          ...button.attributes,
          text: userLabel || "Read more",
        };
      }
    } else {
      const para = desired[0];
      if (para) {
        const label = escapeHtml(userLabel || "Read more");
        para.attributes = {
          ...para.attributes,
          content: `<a href="#">${label}</a>`,
        };
      }
    }

    // No-op replace if structure already matches
    if (sameStructure(shape(current), shape(desired))) return;

    replaceInnerBlocks(clientId, desired, { updateSelection: false });
    const inside = state.hasSelectedInnerBlock(clientId, true);
    if (!inside) selectBlock(clientId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clientId, structureKey]);

  /** Cosmetic updates (no replace): alignments, padding, radius, width — NOT the label. */
  useEffect(() => {
    if (!showMore) return;

    const state = select(blockEditorStore);
    const kids = state.getBlocks(clientId);

    if (moreStyle === "button") {
      const wrapper = kids.find((b) => b.name === "core/buttons");
      const button = wrapper?.innerBlocks?.find(
        (b) => b.name === "core/button",
      );

      // wrapper alignment
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

      // button visuals (do NOT touch button.text; users edit that inline)
      if (button) {
        const next = {
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
        if (para.attributes?.align !== wantAlign) {
          updateBlockAttributes(para.clientId, { align: wantAlign });
        }
        // Do NOT overwrite para.content here; user edits it inline.
      }
    }
  }, [
    clientId,
    showMore,
    moreStyle,
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
        <PanelBody
          title={__("Excerpt & More Link", "campaignbridge")}
          initialOpen
        >
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__("Max words", "campaignbridge")}
            value={Number(maxWords) || 0}
            onChange={(v) => setAttributes({ maxWords: Number(v) || 0 })}
            min={10}
            max={150}
            step={1}
          />

          <ToggleControl
            __nextHasNoMarginBottom
            label={__("Show more link/button", "campaignbridge")}
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
              <ToggleControl
                __nextHasNoMarginBottom
                label={__("Add space before link", "campaignbridge")}
                checked={!!addSpaceBeforeLink}
                onChange={(v) => setAttributes({ addSpaceBeforeLink: !!v })}
                help={
                  enableSeparator
                    ? __("Not used when separator is enabled", "campaignbridge")
                    : __(
                        "Adds spacing between the excerpt text and the read more link",
                        "campaignbridge",
                      )
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
            help={__(
              "Add a separator between the excerpt text and the read more link",
              "campaignbridge",
            )}
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
                  help={__("Enter your own separator text", "campaignbridge")}
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
                help={__(
                  "Adds a space between the excerpt text and the separator",
                  "campaignbridge",
                )}
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
