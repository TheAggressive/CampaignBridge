import { useMemo } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

const escapeHtml = (s = "") =>
  s.replace(
    /[&<>"']/g,
    (m) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        m
      ],
  );

/**
 * Builds the InnerBlocks template (seeded only when empty by InnerBlocks itself).
 * Uses '#' for URL; render.php replaces it with the real link.
 */
export function useReadMoreTemplate({ showMore, moreStyle, morePlacement }) {
  return useMemo(() => {
    if (!showMore) return [];
    if (moreStyle === "button") {
      return [
        [
          "core/buttons",
          {
            className:
              morePlacement === "inline" ? "is-inline-readmore" : undefined,
          },
          [
            [
              "core/button",
              { text: __("Read more", "campaignbridge"), url: "#" },
            ],
          ],
        ],
      ];
    }
    return [
      [
        "core/paragraph",
        {
          className:
            morePlacement === "inline" ? "is-inline-readmore" : undefined,
          content: `<a href="#">${escapeHtml(__("Read more", "campaignbridge"))}</a>`,
        },
      ],
    ];
  }, [showMore, moreStyle, morePlacement]);
}
