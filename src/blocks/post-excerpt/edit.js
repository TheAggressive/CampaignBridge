import {
  store as blockEditorStore,
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from "@wordpress/block-editor";
import {
  Button,
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import { useMemo } from "@wordpress/element";
import { decodeEntities } from "@wordpress/html-entities";
import { useSyncInnerBlocks } from "./hooks/useSyncInnerBlocks";

// Constants
const DEFAULT_ATTRIBUTES = {
  maxWords: 50,
  showMore: false,
  moreStyle: "link",
  moreLabel: "Read more",
  linkTo: "post",
  morePrefix: "",
  addSpaceBeforeLink: true,
  enableSeparator: false,
  separatorType: "custom",
  customSeparator: "",
  addSpaceBeforeSeparator: false,
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

// Constants are defined above, no need to import

export default function Edit({
  attributes,
  setAttributes,
  context = {},
  clientId,
}) {
  const { replaceInnerBlocks } = useDispatch(blockEditorStore);
  const { selectBlock } = useDispatch("core/block-editor");
  const postId = Number(context["campaignbridge:postId"]) || 0;
  const postType = context["campaignbridge:postType"] || "post";
  const {
    maxWords,
    showMore,
    moreStyle,
    moreLabel,
    linkTo,
    morePrefix,
    addSpaceBeforeLink,
    enableSeparator,
    separatorType,
    customSeparator,
    addSpaceBeforeSeparator,
  } = { ...DEFAULT_ATTRIBUTES, ...attributes };

  const post = useSelect(
    (select) =>
      postId
        ? select("core").getEntityRecord("postType", postType, postId)
        : null,
    [postType, postId],
  );
  const raw = post?.excerpt?.rendered || post?.content?.rendered || "";
  const text = decodeEntities(raw)
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim();

  // Split text into words and limit by word count
  const words = text.split(/\s+/).filter((word) => word.length > 0);
  const limitedWords = words.slice(0, maxWords);
  let excerpt = limitedWords.join(" ");

  // Remove trailing period if show more link is enabled
  if (showMore && excerpt.endsWith(".")) {
    excerpt = excerpt.slice(0, -1).trim();
  }
  let linkUrl = "";
  if (post) {
    if (linkTo === "postType") {
      // Best effort: build from postType root (REST doesn’t expose archive easily)
      try {
        const base = new URL(post.link || "", window.location.origin);
        // Strip trailing slash and last path segment (post slug) to get /post-type/
        const parts = base.pathname.replace(/\/$/, "").split("/");
        if (parts.length > 1) {
          const parentPath = `/${parts[1]}/`;
          base.pathname = parentPath;
          base.search = "";
          base.hash = "";
          linkUrl = base.toString();
        }
      } catch (e) {
        linkUrl = post.link || "";
      }
    } else {
      linkUrl = post.link || "";
    }
  }
  // Set up the template based on moreStyle
  const getTemplate = () => {
    if (!showMore || !linkUrl) return [];

    if (moreStyle === "button") {
      return [
        [
          "core/buttons",
          {
            layout: {
              type: "flex",
              justifyContent: "left",
            },
          },
          [
            [
              "core/button",
              {
                text: moreLabel || "Read more",
                url: linkUrl,
              },
            ],
          ],
        ],
      ];
    } else {
      return [
        [
          "core/paragraph",
          {
            content: `<a href="${linkUrl}">${moreLabel || "Read more"}</a>`,
          },
        ],
      ];
    }
  };

  const props = useBlockProps();

  // Create structure key that only changes when structure actually changes
  const structureKey = `${showMore ? 1 : 0}|${moreStyle}`;

  // Memoize template - only include structural dependencies
  const template = useMemo(
    () => getTemplate(),
    [
      showMore,
      moreStyle,
      linkUrl, // URL is structural since it affects the template
    ],
  );

  // Use the new hook API
  const { resetToTemplate } = useSyncInnerBlocks(clientId, template, showMore, {
    structureKey,
    lockTemplate: true,
    clearOnDisable: true,
    keepParentSelected: true,
    debounceMs: 150, // Debounce to prevent rapid rebuilds
  });

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
            label="Show more link/button"
            checked={!!showMore}
            onChange={(v) => setAttributes({ showMore: !!v })}
          />

          {showMore ? (
            <>
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label="Style"
                value={moreStyle}
                options={MORE_STYLE_OPTIONS}
                onChange={(v) => setAttributes({ moreStyle: v })}
              />
              <TextControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label="Label"
                value={moreLabel}
                onChange={(v) => setAttributes({ moreLabel: v })}
              />
              <ToggleControl
                __nextHasNoMarginBottom
                label="Add space before link"
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
                label="Link to"
                value={linkTo}
                options={LINK_TO_OPTIONS}
                onChange={(v) => setAttributes({ linkTo: v })}
              />
            </>
          ) : null}

          <Button
            variant="secondary"
            onClick={() => resetToTemplate()}
            style={{ marginTop: "16px" }}
          >
            Reset to Default {moreStyle === "button" ? "Button" : "Link"}
          </Button>
        </PanelBody>

        {/* Separator Section */}
        <PanelBody title="Separator" initialOpen={false}>
          <ToggleControl
            __nextHasNoMarginBottom
            label="Enable separator"
            checked={!!enableSeparator}
            onChange={(v) => setAttributes({ enableSeparator: !!v })}
            help="Add a separator between the excerpt and the read more link"
          />
          {enableSeparator ? (
            <>
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label="Separator type"
                value={separatorType}
                options={SEPARATOR_OPTIONS}
                onChange={(value) => {
                  setAttributes({ separatorType: value });
                  // Set the corresponding custom separator value
                  setAttributes({
                    customSeparator: SEPARATOR_MAPPING[value] || "",
                  });
                }}
              />
              {separatorType === "custom" && (
                <TextControl
                  __next40pxDefaultSize
                  __nextHasNoMarginBottom
                  label="Custom separator"
                  value={customSeparator}
                  onChange={(v) => setAttributes({ customSeparator: v })}
                  help="Enter your own separator text"
                  placeholder="e.g., |, —, •, etc."
                />
              )}
              <ToggleControl
                __nextHasNoMarginBottom
                label="Add space before separator"
                checked={!!addSpaceBeforeSeparator}
                onChange={(v) =>
                  setAttributes({ addSpaceBeforeSeparator: !!v })
                }
                help="Adds a space between the excerpt text and the separator"
              />
            </>
          ) : null}
        </PanelBody>
      </InspectorControls>
      {excerpt ? (
        <>
          {excerpt}
          {enableSeparator && customSeparator
            ? `${addSpaceBeforeSeparator ? " " : ""}${customSeparator} `
            : ""}
          {addSpaceBeforeLink && !enableSeparator && showMore ? " " : ""}
          {showMore && (
            <InnerBlocks
              templateLock="all"
              allowedBlocks={["core/paragraph", "core/button", "core/buttons"]}
              templateInsertUpdatesSelection={false}
            />
          )}
        </>
      ) : null}
    </div>
  );
}
