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

export default function Edit({ attributes, setAttributes, context = {} }) {
  const postId = Number(context["campaignbridge:postId"]) || 0;
  const show = context["campaignbridge:showExcerpt"] !== false;
  const postType = context["campaignbridge:postType"] || "post";
  const {
    maxWords = 50,
    showMore = false,
    moreStyle = "link",
    moreLabel = "Read more",
    linkTo = "post",
    morePrefix = "",
    linkColor = "#2271b1",
    linkUnderline = true,
    buttonBg = "#111111",
    buttonColor = "#ffffff",
    buttonRadius = 4,
    buttonPaddingX = 16,
    buttonPaddingY = 10,
  } = attributes || {};
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
  const excerpt = limitedWords.join(" ");
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
                options={[
                  { label: "Link", value: "link" },
                  { label: "Button", value: "button" },
                ]}
                onChange={(v) => setAttributes({ moreStyle: v })}
              />
              <TextControl
                label="Label"
                value={moreLabel}
                onChange={(v) => setAttributes({ moreLabel: v })}
              />
              <TextControl
                label="Separator (after excerpt)"
                value={morePrefix}
                onChange={(v) => setAttributes({ morePrefix: v })}
              />
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label="Link to"
                value={linkTo}
                options={[
                  { label: "Post", value: "post" },
                  { label: "Post Type", value: "postType" },
                ]}
                onChange={(v) => setAttributes({ linkTo: v })}
              />
              {moreStyle === "link" ? (
                <>
                  <TextControl
                    type="color"
                    label="Link color"
                    value={linkColor}
                    onChange={(v) => setAttributes({ linkColor: v })}
                  />
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
                </>
              ) : (
                <>
                  <TextControl
                    type="color"
                    label="Button background"
                    value={buttonBg}
                    onChange={(v) => setAttributes({ buttonBg: v })}
                  />
                  <TextControl
                    type="color"
                    label="Button text color"
                    value={buttonColor}
                    onChange={(v) => setAttributes({ buttonColor: v })}
                  />
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
      </InspectorControls>
      {show && excerpt ? (
        <>
          {excerpt}
          {morePrefix ? `${morePrefix} ` : ""}
          {showMore && linkUrl ? (
            moreStyle === "button" ? (
              <p style={{ margin: "12px 0 0" }}>
                <a
                  href={linkUrl}
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
                >
                  {moreLabel || "Read more"}
                </a>
              </p>
            ) : (
              <>
                <a
                  href={linkUrl}
                  style={{
                    color: linkColor || "#2271b1",
                    textDecoration: linkUnderline ? "underline" : "none",
                  }}
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
