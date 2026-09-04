// WordPress has not yet exposed ListView under a stable public name. Keep the
// experimental dependency behind this adapter so the rest of the editor uses
// a stable CampaignBridge contract.
import * as blockEditor from '@wordpress/block-editor';
import type { ComponentType } from 'react';

interface ListViewProps {
  rootClientId: string;
  selectedBlockClientId: string | null;
  showNestedBlocks: boolean;
  showBlockMovers: boolean;
  showAppender: boolean;
}

const WordPressListView = (
  blockEditor as unknown as {
    __experimentalListView: ComponentType<ListViewProps>;
  }
).__experimentalListView;

export default WordPressListView;
