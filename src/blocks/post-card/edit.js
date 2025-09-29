import apiFetch from "@wordpress/api-fetch";
import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from "@wordpress/block-editor";
import { PanelBody, SelectControl, ToggleControl } from "@wordpress/components";
import { useEffect, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

// Constants
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

const LINK_TO_OPTIONS = [
  { label: __("Post", "campaignbridge"), value: "post" },
  { label: __("Post Type", "campaignbridge"), value: "postType" },
];

const API_ENDPOINTS = {
  POST_TYPES: "campaignbridge/v1/post-types",
  POSTS: "campaignbridge/v1/posts",
};

const DEFAULT_POST_SELECT_LABEL = __(
  "— Please Select Post —",
  "campaignbridge",
);
const DEFAULT_POST_TYPE_SELECT_LABEL = __(
  "— Please Select Post Type —",
  "campaignbridge",
);

export default function Edit({ attributes, setAttributes, clientId }) {
  const {
    postType,
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
  const [typeItems, setTypeItems] = useState([]);
  const [isLoadingTypes, setIsLoadingTypes] = useState(true);
  const [isLoadingPosts, setIsLoadingPosts] = useState(false);

  // Fetch post types on mount - WordPress best practices
  useEffect(() => {
    const fetchPostTypes = async () => {
      try {
        setIsLoadingTypes(true);
        const response = await apiFetch({
          path: API_ENDPOINTS.POST_TYPES,
        });

        if (Array.isArray(response?.items) && response.items.length) {
          setTypeItems(
            response.items.map((item) => ({
              label: item.label,
              value: item.id,
            })),
          );
        }
      } catch (error) {
        console.warn(
          __("Failed to fetch post types:", "campaignbridge"),
          error,
        );
        // Set empty array on error to prevent UI issues
        setTypeItems([]);
      } finally {
        setIsLoadingTypes(false);
      }
    };

    fetchPostTypes();
  }, []);

  // Fetch posts only when user explicitly selects a post type
  const fetchPosts = async (selectedPostType) => {
    if (!selectedPostType) return;

    try {
      setIsLoadingPosts(true);
      const response = await apiFetch({
        path: `${API_ENDPOINTS.POSTS}?post_type=${encodeURIComponent(selectedPostType)}`,
      });

      setPostItems(Array.isArray(response?.items) ? response.items : []);
    } catch (error) {
      console.warn(__("Failed to fetch posts:", "campaignbridge"), error);
      setPostItems([]);
    } finally {
      setIsLoadingPosts(false);
    }
  };
  // Note: default layout insertion has been removed.

  const PlaceholderContent = () => {
    return (
      <div className="campaignbridge-placeholder-container">
        <h3 className="campaignbridge-placeholder-title">
          {__("Select a Post", "campaignbridge")}
        </h3>

        <div className="campaignbridge-placeholder-form">
          <SelectControl
            label={__("Post Type", "campaignbridge")}
            value={postType || ""}
            options={[
              ...(isLoadingTypes
                ? [{ label: "Loading Post Types...", value: "" }]
                : [{ label: DEFAULT_POST_TYPE_SELECT_LABEL, value: "" }]),
              ...typeItems,
            ]}
            onChange={(v) => {
              setAttributes({ postType: v, postId: 0 });
              if (v && v !== "") {
                fetchPosts(v);
              } else {
                setPostItems([]);
              }
            }}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <SelectControl
            label={__("Post", "campaignbridge")}
            value={String(postId || "")}
            options={[
              { label: DEFAULT_POST_SELECT_LABEL, value: "" },
              ...(isLoadingPosts
                ? [
                    {
                      label: __("Loading posts...", "campaignbridge"),
                      value: "",
                    },
                  ]
                : postItems.map((it) => ({
                    label: it.label,
                    value: String(it.id),
                  }))),
            ]}
            onChange={(v) => setAttributes({ postId: v ? Number(v) : 0 })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            disabled={!postType || isLoadingPosts}
          />
        </div>

        <p className="campaignbridge-placeholder-description">
          {__(
            "You can also change these selections later in the sidebar settings.",
            "campaignbridge",
          )}
        </p>
      </div>
    );
  };

  return (
    <div {...props}>
      <InspectorControls>
        <PanelBody title="Post Card Settings" initialOpen>
          <SelectControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__("Post type", "campaignbridge")}
            value={postType || ""}
            options={[
              ...(isLoadingTypes
                ? [
                    {
                      label: __("Loading Post Types...", "campaignbridge"),
                      value: "",
                    },
                  ]
                : [{ label: DEFAULT_POST_TYPE_SELECT_LABEL, value: "" }]),
              ...typeItems,
            ]}
            onChange={(v) => {
              setAttributes({ postType: v, postId: 0 });
              if (v && v !== "") {
                fetchPosts(v);
              } else {
                setPostItems([]);
              }
            }}
          />
          <SelectControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label="Post"
            value={String(postId || "")}
            options={[
              { label: DEFAULT_POST_SELECT_LABEL, value: "" },
              ...postItems.map((it) => ({
                label: it.label,
                value: String(it.id),
              })),
            ]}
            onChange={(v) => setAttributes({ postId: v ? Number(v) : 0 })}
          />
          <ToggleControl
            __nextHasNoMarginBottom
            label={__("Make entire card clickable", "campaignbridge")}
            checked={!!slotLinkEnabled}
            onChange={(v) => setAttributes({ slotLinkEnabled: !!v })}
          />
          {slotLinkEnabled ? (
            <SelectControl
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label={__("Link to", "campaignbridge")}
              value={slotLinkTo}
              options={LINK_TO_OPTIONS}
              onChange={(v) => setAttributes({ slotLinkTo: v })}
            />
          ) : null}
        </PanelBody>
      </InspectorControls>

      {/* Show placeholder when no post selected, otherwise show content */}
      {postId && postType ? (
        <InnerBlocks allowedBlocks={ALLOWED_BLOCKS} templateLock={false} />
      ) : (
        <PlaceholderContent />
      )}
    </div>
  );
}
