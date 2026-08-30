import { InnerBlocks } from '@wordpress/block-editor';
import type { BlockConfiguration } from '@wordpress/blocks';
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';

const { name }: { name: string } = metadata;
export { metadata, name };

export const settings = {
  edit: Edit,
  save: () => <InnerBlocks.Content />,
};

export const init = (): void => {
  registerBlockType(
    { name, ...metadata } as unknown as BlockConfiguration,
    settings
  );
};

init();
