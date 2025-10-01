import { BlockBreadcrumb } from "@wordpress/block-editor";

/**
 * Footer Component
 *
 * Provides breadcrumb navigation for the block editor, showing the current
 * block hierarchy and allowing users to navigate between nested blocks.
 * This component is displayed in the footer area of the InterfaceSkeleton.
 *
 * @returns {JSX.Element} The block breadcrumb navigation component
 *
 * @example
 * ```jsx
 * <Footer />
 * ```
 */
export default function Footer() {
  return <BlockBreadcrumb />;
}
