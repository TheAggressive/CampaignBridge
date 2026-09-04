import type { BlockConfiguration } from '@wordpress/blocks';
import { getBlockType, registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';

const { name }: { name: string } = metadata;
export { metadata, name };

export const settings = {
  edit: Edit,
  save: () => null,
};

export const init = (): void => {
  if (getBlockType(name)) {
    return;
  }

  registerBlockType(metadata as unknown as BlockConfiguration, settings);
};

init();
