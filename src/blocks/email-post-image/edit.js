import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

export default function Edit( { context = {} } ) {
	const postId = Number( context[ 'campaignbridge:postId' ] ) || 0;
	const show = context[ 'campaignbridge:showImage' ] !== false;
	const postType = context[ 'campaignbridge:postType' ] || 'post';
	const post = useSelect(
		( select ) =>
			postId
				? select( 'core' ).getEntityRecord(
						'postType',
						postType,
						postId
				  )
				: null,
		[ postType, postId ]
	);
	const mediaId = post?.featured_media || 0;
	const media = useSelect(
		( select ) =>
			mediaId
				? select( 'core' ).getEntityRecord(
						'postType',
						'attachment',
						mediaId
				  )
				: null,
		[ mediaId ]
	);
	const url =
		media?.media_details?.sizes?.full?.source_url ||
		media?.source_url ||
		'';
	const props = useBlockProps();
	return (
		<div { ...props }>
			{ show && url ? (
				<img
					src={ url }
					alt=""
					style={ {
						display: 'block',
						width: '100%',
						height: 'auto',
						border: 0,
					} }
				/>
			) : null }
		</div>
	);
}
