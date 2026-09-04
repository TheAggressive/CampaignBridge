import type { BlockConfiguration } from '@wordpress/blocks';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import React from 'react';

import metadata from './block.json';
import Edit from './edit';

const { name }: { name: string } = metadata;
export { metadata, name };

export interface PostCTABlockSettings {
  edit: React.ComponentType<any>;
  save: () => null;
}

export const settings: PostCTABlockSettings = {
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
