import { InnerBlocks } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';

const { name } = metadata;
export { metadata, name };

export const settings = {
	edit: Edit,
	save() {
		return <InnerBlocks.Content />;
	},
};

export const init = () => registerBlockType( { name, ...metadata }, settings );

init();
