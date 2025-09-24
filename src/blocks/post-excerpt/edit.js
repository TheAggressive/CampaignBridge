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
import { useEffect, useMemo, useRef } from "@wordpress/element";
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

  // Separator
  enableSeparator: false,
  separatorType: "custom",
  customSeparator: "",
  addSpaceBeforeSeparator: false,
};

const SEPARATOR_OPTIONS = [
  { label: __("Custom", "campaignbridge"), value: "custom" },
  { label: __("Em dash (—)", "campaignbridge"), value: "em-dash" },
  { label: __("En dash (–)", "campaignbridge"), value: "en-dash" },
  { label: __("Ellipsis (...)", "campaignbridge"), value: "ellipsis" },
  { label: __("Pipe (|)", "campaignbridge"), value: "pipe" },
  { label: __("Colon (:)", "campaignbridge"), value: "colon" },
  { label: __("Semicolon (;)", "campaignbridge"), value: "semicolon" },
  { label: __("Bullet (•)", "campaignbridge"), value: "bullet" },
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
  { label: __("Post", "campaignbridge"), value: "post" },
  { label: __("Post Type", "campaignbridge"), value: "postType" },
];

const MORE_STYLE_OPTIONS = [
  { label: __("Link", "campaignbridge"), value: "link" },
  { label: __("Button", "campaignbridge"), value: "button" },
];

/** ------------------ Helpers ------------------ */

// structure-only comparison (names + nesting)
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

// pull inner text from a paragraph’s <a>
function extractLabelFromParagraph(html = "") {
  const m = /<a[^>]*>([\s\S]*?)<\/a>/i.exec(html);
  const inner = m ? m[1] : html;
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
    morePrefix, // not used in editor preview
    addSpaceBeforeLink,

    // separator
    enableSeparator,
    separatorType,
    customSeparator,
    addSpaceBeforeSeparator,
  } = { ...DEFAULT_ATTRIBUTES, ...attributes };

  const postId = Number(context["campaignbridge:postId"]) || 0;
  const postType = context["campaignbridge:postType"] || "post";

  // Post -> excerpt preview only
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

  // Templates (STRUCTURE ONLY). Use '#' in editor; PHP replaces with real link.
  const getTemplate = () => {
    if (!showMore) return [];

    if (moreStyle === "button") {
      return [
        [
          "core/buttons",
          {},
          [
            [
              "core/button",
              { text: __("Read more", "campaignbridge"), url: "#" },
            ],
          ],
        ],
      ];
    }

    // Link variant: paragraph with anchor
    return [
      [
        "core/paragraph",
        {
          content: `<a href="#">${escapeHtml(__("Read more", "campaignbridge"))}</a>`,
        },
      ],
    ];
  };

  const props = useBlockProps({ style: { fontSize: 14, lineHeight: 1.5 } });

  // Only recalc when the shape can change
  const template = useMemo(getTemplate, [showMore, moreStyle]);

  const { replaceInnerBlocks, selectBlock } = useDispatch(blockEditorStore);

  // Track previous structure to ensure we only act on user-initiated structure changes
  const prevStructureKey = useRef(null);
  const structureKey = `${showMore ? 1 : 0}|${moreStyle}`;

  // Only touch InnerBlocks when structure changes (showMore / moreStyle)
  useEffect(() => {
    if (!clientId) return;

    // Skip the very first run after mount: we want to load whatever is saved
    if (prevStructureKey.current === null) {
      prevStructureKey.current = structureKey;
      return;
    }

    // If nothing changed (same structure), do nothing
    if (prevStructureKey.current === structureKey) return;

    const state = select(blockEditorStore);
    const current = state.getBlocks(clientId);

    // Handle disabling: clear children when turning showMore OFF
    const wasOn = prevStructureKey.current.startsWith("1|");
    const isOn = structureKey.startsWith("1|");
    if (!isOn && wasOn) {
      if (current.length > 0) {
        replaceInnerBlocks(clientId, [], { updateSelection: false });
        selectBlock(clientId);
      }
      prevStructureKey.current = structureKey;
      return;
    }

    // We’re enabled & a style switch may have happened: build desired and migrate label
    let userLabel = __("Read more", "campaignbridge");

    // Prefer button text if present
    const wrapper = current.find((b) => b.name === "core/buttons");
    const btn = wrapper?.innerBlocks?.find((b) => b.name === "core/button");
    if (btn?.attributes?.text) {
      userLabel = String(btn.attributes.text).trim() || userLabel;
    } else {
      // Else read paragraph <a> text
      const para = current.find((b) => b.name === "core/paragraph");
      if (para?.attributes?.content) {
        const extracted = extractLabelFromParagraph(para.attributes.content);
        if (extracted) userLabel = extracted;
      }
    }

    const desired = createBlocksFromInnerBlocksTemplate(template || []);
    if (moreStyle === "button") {
      const buttons = desired[0];
      const button = buttons?.innerBlocks?.[0];
      if (button) {
        button.attributes = {
          ...button.attributes,
          text: userLabel || __("Read more", "campaignbridge"),
        };
      }
    } else {
      const para = desired[0];
      if (para) {
        const label = escapeHtml(
          userLabel || __("Read more", "campaignbridge"),
        );
        para.attributes = {
          ...para.attributes,
          content: `<a href="#">${label}</a>`,
        };
      }
    }

    // If structures match already, do nothing (preserve user styles/text)
    if (sameStructure(shape(current), shape(desired))) {
      prevStructureKey.current = structureKey;
      return;
    }

    // First-time insert or style switch: replace
    replaceInnerBlocks(clientId, desired, { updateSelection: false });
    const inside = state.hasSelectedInnerBlock(clientId, true);
    if (!inside) selectBlock(clientId);

    prevStructureKey.current = structureKey;
  }, [clientId, structureKey, template, replaceInnerBlocks, selectBlock]);

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

        <PanelBody
          title={__("Separator", "campaignbridge")}
          initialOpen={false}
        >
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
                  placeholder={__("e.g., |, —, •, etc.", "campaignbridge")}
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
          {enableSeparator && customSeparator
            ? `${addSpaceBeforeSeparator ? " " : ""}${customSeparator} `
            : ""}
          {addSpaceBeforeLink && !enableSeparator && showMore ? " " : ""}
          {showMore && (
            <InnerBlocks
              templateLock="all" // users can edit text but cannot add/move/remove blocks
              allowedBlocks={["core/paragraph", "core/buttons", "core/button"]}
              templateInsertUpdatesSelection={false}
            />
          )}
        </>
      )}
    </div>
  );
}
