import type { BlockConfiguration } from '@wordpress/blocks';
import { registerBlockType } from '@wordpress/blocks';
import type { ComponentType } from 'react';

import './editor.css';
import './style.css';

import metadata from './block.json';
import Edit from './edit';
import Save from './save';

const { name }: { name: string } = metadata;
export { metadata, name };

export interface PostExcerptBlockSettings {
  edit: ComponentType<any>;
  save: ComponentType<any>;
}

export const settings: PostExcerptBlockSettings = {
  edit: Edit,
  save: Save,
};

export const init = (): void => {
  registerBlockType(
    { name, ...metadata } as unknown as BlockConfiguration,
    settings
  );
};

init();
