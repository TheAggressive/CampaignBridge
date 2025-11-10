/**
 * Container Block Save Component
 *
 * Renders the saved content for the CampaignBridge container block. Since the container
 * block uses dynamic rendering (server-side rendering), this save component simply
 * outputs the InnerBlocks content placeholder.
 *
 * The actual rendering is handled by the PHP render callback in render.php,
 * which provides full server-side control over the email HTML structure and
 * ensures proper email client compatibility.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function Save(): JSX.Element {
  return <InnerBlocks.Content />;
}
