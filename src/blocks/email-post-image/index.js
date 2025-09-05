import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';

const { name } = metadata;
export { metadata, name };

export const settings = {
	edit: Edit,
};

export const init = () => registerBlockType( { name, ...metadata }, settings );

init();
