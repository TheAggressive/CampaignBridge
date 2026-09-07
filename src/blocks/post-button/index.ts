import type { BlockConfiguration } from '@wordpress/blocks';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import React from 'react';

import metadata from './block.json';
import Edit from './edit';
import { transforms } from './transforms';

const { name }: { name: string } = metadata;
export { metadata, name };

export const settings: {
  edit: React.ComponentType<any>;
  save: () => null;
  transforms: typeof transforms;
} = {
  edit: Edit,
  save: () => null,
  transforms,
};

export const init = (): void => {
  if (getBlockType(name)) {
    return;
  }

  registerBlockType(metadata as unknown as BlockConfiguration, settings);
};

init();
