import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from "@wordpress/block-editor";
import { PanelBody, SelectControl, ToggleControl } from "@wordpress/components";
import { useEffect, useState } from "@wordpress/element";

const ALLOWED_BLOCKS = [
  "campaignbridge/post-image",
  "campaignbridge/post-title",
  "campaignbridge/post-excerpt",
  "campaignbridge/post-cta",
  "core/paragraph",
  "core/heading",
  "core/group",
  "core/columns",
  "core/column",
  "core/spacer",
  "core/separator",
];

export default function Edit({ attributes, setAttributes, clientId }) {
  const {
    postType = "post",
    postId = 0,
    slotLinkEnabled = false,
    slotLinkTo = "post",
  } = attributes;
  const props = useBlockProps({ className: "cb-post-card" });
  // Ensure a stable slotId is generated and persisted
  useEffect(() => {
    if (!attributes.slotId) {
      const generated = `slot_${clientId.slice(0, 8)}`;
      setAttributes({ slotId: generated });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [attributes.slotId, clientId]);
  const [postItems, setPostItems] = useState([]);
  const [typeItems, setTypeItems] = useState([
    { label: "Posts", value: "post" },
    { label: "Pages", value: "page" },
  ]);

  useEffect(() => {
    // Fetch allowed post types from plugin settings
    (async () => {
      try {
        const root = window.wpApiSettings?.root || "/wp-json/";
        const url = `${root}campaignbridge/v1/post-types`;
        const res = await fetch(url, {
          headers: window.wpApiSettings?.nonce
            ? { "X-WP-Nonce": window.wpApiSettings.nonce }
            : {},
          credentials: "same-origin",
        });
        const json = await res.json();
        if (Array.isArray(json?.items) && json.items.length) {
          setTypeItems(
            json.items.map((it) => ({
              label: it.label,
              value: it.id,
            })),
          );
          // Normalize current postType if excluded
          const allowed = new Set(json.items.map((i) => i.id));
          if (!allowed.has(postType)) {
            setAttributes({
              postType: json.items[0].id,
              postId: 0,
            });
          }
        }
      } catch (e) {}
    })();

    const fetchPosts = async () => {
      try {
        const root = window.wpApiSettings?.root || "/wp-json/";
        const url = `${root}campaignbridge/v1/posts?post_type=${encodeURIComponent(
          postType || "post",
        )}`;
        const res = await fetch(url, {
          headers: window.wpApiSettings?.nonce
            ? { "X-WP-Nonce": window.wpApiSettings.nonce }
            : {},
          credentials: "same-origin",
        });
        const json = await res.json();
        setPostItems(Array.isArray(json?.items) ? json.items : []);
      } catch (e) {
        setPostItems([]);
      }
    };
    fetchPosts();
  }, [postType]);
  // Note: default layout insertion has been removed.

  return (
    <div {...props}>
      <InspectorControls>
        <PanelBody title="Post Card Settings" initialOpen>
          <SelectControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label="Post type"
            value={postType}
            options={typeItems}
            onChange={(v) => setAttributes({ postType: v, postId: 0 })}
          />
          <SelectControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label="Post"
            value={String(postId || "")}
            options={[
              { label: "— Select —", value: "" },
              ...postItems.map((it) => ({
                label: it.label,
                value: String(it.id),
              })),
            ]}
            onChange={(v) => setAttributes({ postId: v ? Number(v) : 0 })}
          />
          <ToggleControl
            __nextHasNoMarginBottom
            label="Make entire card clickable"
            checked={!!slotLinkEnabled}
            onChange={(v) => setAttributes({ slotLinkEnabled: !!v })}
          />
          {slotLinkEnabled ? (
            <SelectControl
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label="Link to"
              value={slotLinkTo}
              options={[
                { label: "Post", value: "post" },
                { label: "Post Type", value: "postType" },
              ]}
              onChange={(v) => setAttributes({ slotLinkTo: v })}
            />
          ) : null}
        </PanelBody>
      </InspectorControls>
      <InnerBlocks allowedBlocks={ALLOWED_BLOCKS} templateLock={false} />
    </div>
  );
}
