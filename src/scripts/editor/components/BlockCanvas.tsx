import {
  BlockCanvas as CoreBlockCanvas,
  Inserter,
} from '@wordpress/block-editor';
import type { ComponentType, ReactNode } from 'react';
import type { EditorStyle } from '../types';
import HistoryControls from './Toolbar/HistoryControls';

declare module '@wordpress/block-editor' {
  export const BlockCanvas: ComponentType<{
    children?: ReactNode;
    height?: string;
    styles?: EditorStyle[];
  }>;
}

/**
 * Add CampaignBridge's compact toolbar around WordPress's complete canvas.
 * Core owns writing flow, selection clearing, commands, styles, and iframe
 * isolation; this component only owns product-specific chrome.
 */
export default function BlockCanvas({
  styles,
}: {
  styles?: EditorStyle[];
}): JSX.Element {
  return (
    <div className='cb-editor__canvas'>
      <div className='cb-editor__canvas-tools'>
        <Inserter />
        <HistoryControls />
      </div>
      <CoreBlockCanvas height='100%' styles={styles} />
    </div>
  );
}
