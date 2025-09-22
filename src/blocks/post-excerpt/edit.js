import { InspectorControls, useBlockProps } from "@wordpress/block-editor";
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from "@wordpress/components";
import { useSelect } from "@wordpress/data";
import { decodeEntities } from "@wordpress/html-entities";

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
  linkColor: "#2271b1",
  linkUnderline: true,
  buttonBg: "#111111",
  buttonColor: "#ffffff",
  buttonRadius: 4,
  buttonPaddingX: 16,
  buttonPaddingY: 10,
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

// Components
import { default as ButtonColorPickers } from "./components/ButtonColorPickers";
import { default as LinkColorPicker } from "./components/LinkColorPicker";
// Constants are defined above, no need to import

export default function Edit({ attributes, setAttributes, context = {} }) {
  const postId = Number(context["campaignbridge:postId"]) || 0;
  const show = context["campaignbridge:showExcerpt"] !== false;
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
    linkColor,
    linkUnderline,
    buttonBg,
    buttonColor,
    buttonRadius,
    buttonPaddingX,
    buttonPaddingY,
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
  const props = useBlockProps({ style: { fontSize: 14, lineHeight: 1.5 } });
  return (
    <div {...props}>
      <InspectorControls>
        <PanelBody title="Excerpt Settings" initialOpen>
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
        </PanelBody>
        <PanelBody title="More Link" initialOpen={false}>
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
                help="Adds a space between the excerpt text and the read more link"
              />
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label="Link to"
                value={linkTo}
                options={LINK_TO_OPTIONS}
                onChange={(v) => setAttributes({ linkTo: v })}
              />
              {/* Color Pickers */}
              {moreStyle === "link" && (
                <LinkColorPicker
                  value={linkColor}
                  onChange={(color) => setAttributes({ linkColor: color })}
                />
              )}

              {moreStyle === "button" && (
                <ButtonColorPickers
                  buttonBg={buttonBg}
                  buttonColor={buttonColor}
                  onButtonBgChange={(color) =>
                    setAttributes({ buttonBg: color })
                  }
                  onButtonColorChange={(color) =>
                    setAttributes({ buttonColor: color })
                  }
                />
              )}
              {moreStyle === "link" && (
                <ToggleControl
                  __nextHasNoMarginBottom
                  label="Underline"
                  checked={!!linkUnderline}
                  onChange={(v) =>
                    setAttributes({
                      linkUnderline: !!v,
                    })
                  }
                />
              )}
              {moreStyle === "button" && (
                <>
                  <RangeControl
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                    label="Border radius"
                    value={Number(buttonRadius) || 0}
                    min={0}
                    max={24}
                    onChange={(v) =>
                      setAttributes({
                        buttonRadius: Number(v) || 0,
                      })
                    }
                  />
                  <RangeControl
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                    label="Padding X"
                    value={Number(buttonPaddingX) || 0}
                    min={4}
                    max={40}
                    step={2}
                    onChange={(v) =>
                      setAttributes({
                        buttonPaddingX: Number(v) || 0,
                      })
                    }
                  />
                  <RangeControl
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                    label="Padding Y"
                    value={Number(buttonPaddingY) || 0}
                    min={4}
                    max={32}
                    step={2}
                    onChange={(v) =>
                      setAttributes({
                        buttonPaddingY: Number(v) || 0,
                      })
                    }
                  />
                </>
              )}
            </>
          ) : null}
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
      {show && excerpt ? (
        <>
          {excerpt}
          {enableSeparator && customSeparator
            ? `${addSpaceBeforeSeparator ? " " : ""}${customSeparator} `
            : ""}
          {addSpaceBeforeLink && !enableSeparator ? " " : ""}
          {showMore && linkUrl ? (
            moreStyle === "button" ? (
              <p style={{ margin: "12px 0 0" }}>
                <a
                  href={linkUrl}
                  onClick={(e) => e.preventDefault()}
                  style={{
                    display: "inline-block",
                    background: buttonBg || "#111",
                    color: buttonColor || "#fff",
                    textDecoration: "none",
                    padding: `${buttonPaddingY || 10}px ${
                      buttonPaddingX || 16
                    }px`,
                    borderRadius: buttonRadius || 0,
                  }}
                  title="Link disabled in editor"
                >
                  {moreLabel || "Read more"}
                </a>
              </p>
            ) : (
              <>
                <a
                  href={linkUrl}
                  onClick={(e) => e.preventDefault()}
                  style={{
                    color: linkColor || "#2271b1",
                    textDecoration: linkUnderline ? "underline" : "none",
                  }}
                  title="Link disabled in editor"
                >
                  {moreLabel || "Read more"}
                </a>
              </>
            )
          ) : null}
        </>
      ) : null}
    </div>
  );
}
