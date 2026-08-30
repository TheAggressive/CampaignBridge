/**
 * Container Block Save Component
 *
 * Renders the saved content for the CampaignBridge container block. Since the container
 * block stores semantic InnerBlocks only. The canonical email compiler renders
 * the transport HTML used by preview, export, and delivery.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function Save(): JSX.Element {
  return <InnerBlocks.Content />;
}
