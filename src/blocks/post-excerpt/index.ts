import type { BlockConfiguration } from '@wordpress/blocks';
import { registerBlockType } from '@wordpress/blocks';
import type { ComponentType } from 'react';

import './editor.css';
import metadata from './block.json';
import Edit from './edit';

const { name }: { name: string } = metadata;
export { metadata, name };

export interface PostExcerptBlockSettings {
  edit: ComponentType<any>;
  save: () => null;
}

export const settings: PostExcerptBlockSettings = {
  edit: Edit,
  save: () => null,
};

export const init = (): void => {
  registerBlockType(
    { name, ...metadata } as unknown as BlockConfiguration,
    settings
  );
};

init();
