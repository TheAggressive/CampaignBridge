import { registerBlockType } from '@wordpress/blocks';

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
