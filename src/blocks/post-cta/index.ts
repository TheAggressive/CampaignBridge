import type { BlockConfiguration } from '@wordpress/blocks';
import { registerBlockType } from '@wordpress/blocks';
import React from 'react';

import metadata from './block.json';
import Edit from './edit';

const { name }: { name: string } = metadata;
export { metadata, name };

export interface PostCTABlockSettings {
  edit: React.ComponentType<any>;
}

export const settings: PostCTABlockSettings = {
  edit: Edit,
};

export const init = (): void => {
  registerBlockType(
    { name, ...metadata } as unknown as BlockConfiguration,
    settings
  );
};

init();
