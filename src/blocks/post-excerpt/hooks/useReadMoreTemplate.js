import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const esc = ( s = '' ) =>
	s.replace(
		/[&<>"']/g,
		( m ) =>
			( {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;',
			} )[ m ]
	);

/**
 * Builds the InnerBlocks template (seeded only when empty).
 * Uses '#' for URL; render.php replaces it with the real link.
 * @param root0
 * @param root0.showMore
 * @param root0.moreStyle
 * @param root0.morePlacement
 */
export function useReadMoreTemplate( { showMore, moreStyle, morePlacement } ) {
	return useMemo( () => {
		if ( ! showMore ) {
			return [];
		}
		if ( moreStyle === 'button' ) {
			return [
				[
					'core/buttons',
					{
						className:
							morePlacement === 'inline'
								? 'is-inline-readmore'
								: undefined,
					},
					[
						[
							'core/button',
							{
								text: __( 'Read more', 'campaignbridge' ),
								url: '#',
							},
						],
					],
				],
			];
		}
		return [
			[
				'core/paragraph',
				{
					className:
						morePlacement === 'inline'
							? 'is-inline-readmore'
							: undefined,
					content: `<a href="#">${ esc(
						__( 'Read more', 'campaignbridge' )
					) }</a>`,
				},
			],
		];
	}, [ showMore, moreStyle, morePlacement ] );
}
