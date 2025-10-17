// @ts-ignore - BlockBreadcrumb may not be available in current WP version
import { BlockBreadcrumb } from '@wordpress/block-editor';

/**
 * Footer Component
 *
 * Footer area for the editor interface. Currently empty but available for
 * future extensions like status indicators or additional controls.
 *
 * @returns {JSX.Element} The footer component
 */
export default function Footer(): JSX.Element {
  return <BlockBreadcrumb rootLabelText='Template' />;
}
