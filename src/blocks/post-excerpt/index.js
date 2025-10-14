import { registerBlockType } from '@wordpress/blocks';

import './editor.css';
import './style.css';

import metadata from './block.json';
import Edit from './edit';
import Save from './save';

const { name } = metadata;
export { metadata, name };

export const settings = {
	edit: Edit,
	save: Save,
};

export const init = () => registerBlockType( { name, ...metadata }, settings );

init();
