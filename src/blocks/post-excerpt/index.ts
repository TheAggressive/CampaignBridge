import { registerBlockType } from '@wordpress/blocks';
import type { BlockConfiguration } from '@wordpress/blocks';

import './editor.css';
import './style.css';

import metadata from './block.json';
import Edit from './edit';
import Save from './save';

const { name }: { name: string } = metadata;
export { metadata, name };

export interface PostExcerptBlockSettings {
	edit: React.ComponentType<any>;
	save: React.ComponentType<any>;
}

export const settings: PostExcerptBlockSettings = {
	edit: Edit,
	save: Save,
};

export const init = (): void => {
	registerBlockType({ name, ...metadata } as BlockConfiguration, settings);
};

init();
