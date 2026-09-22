<?php
/**
 * Representative email-client compatibility scenarios.
 *
 * Each scenario is one parsed block document plus the render context it
 * compiles under. Together they exercise the supported authoring grammar the
 * client expectation fixtures make claims about: document shell, preheader,
 * sections, columns and mobile stacking, brand logo, navigation, social links,
 * linked video posters, typography fallbacks, buttons, images, lists, spacing,
 * links, resolved design styles, and the compliance footer.
 *
 * These scenarios are inputs only. The deterministic golden artifacts in
 * `tests/Fixtures/Email/golden/` stay separate: they pin exact bytes, while
 * these prove structural properties that named email clients depend on.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

/**
 * Build one `core/heading` node.
 *
 * @param string               $content Heading text.
 * @param int                  $level   Heading level.
 * @param array<string, mixed> $style   Native style tree.
 * @return array<string, mixed>
 */
$heading = static function ( string $content, int $level, array $style = array() ): array {
	return array(
		'blockName'   => 'core/heading',
		'attrs'       => array(
			'content' => $content,
			'level'   => $level,
			'style'   => $style,
		),
		'innerBlocks' => array(),
	);
};

/**
 * Build one `core/paragraph` node.
 *
 * @param string               $content Rich text.
 * @param array<string, mixed> $style   Native style tree.
 * @return array<string, mixed>
 */
$paragraph = static function ( string $content, array $style = array() ): array {
	return array(
		'blockName'   => 'core/paragraph',
		'attrs'       => array(
			'content' => $content,
			'style'   => $style,
		),
		'innerBlocks' => array(),
	);
};

/**
 * Build one `core/buttons` group holding a single button.
 *
 * @param string      $label     Button label.
 * @param string      $url       Button destination.
 * @param string      $variant   Block style slug, or an empty string for the default.
 * @param string|null $justify   Layout justification.
 * @return array<string, mixed>
 */
$button = static function ( string $label, string $url, string $variant = '', ?string $justify = 'center' ): array {
	$attrs = array(
		'text'  => $label,
		'url'   => $url,
		'style' => array(
			'color' => array(
				'background' => '#0057b8',
				'text'       => '#ffffff',
			),
		),
	);
	if ( '' !== $variant ) {
		$attrs['className'] = 'is-style-' . $variant;
	}

	return array(
		'blockName'   => 'core/buttons',
		'attrs'       => null === $justify ? array() : array( 'layout' => array( 'justifyContent' => $justify ) ),
		'innerBlocks' => array(
			array(
				'blockName'   => 'core/button',
				'attrs'       => $attrs,
				'innerBlocks' => array(),
			),
		),
	);
};

/**
 * Build one `core/image` node with the Core wrapper markup it saves.
 *
 * @param string      $url  Image source.
 * @param string      $alt  Alternative text.
 * @param int         $width  Intrinsic width.
 * @param int         $height Intrinsic height.
 * @param string|null $link   Optional link destination.
 * @return array<string, mixed>
 */
$image = static function ( string $url, string $alt, int $width, int $height, ?string $link = null ): array {
	$img   = '<img src="' . $url . '" alt="' . $alt . '" style="width:' . $width . 'px;height:' . $height . 'px"/>';
	$inner = null === $link ? $img : '<a href="' . $link . '">' . $img . '</a>';
	$attrs = array(
		'url'          => $url,
		'alt'          => $alt,
		'isDecorative' => false,
		'width'        => $width . 'px',
		'height'       => $height . 'px',
	);
	if ( null !== $link ) {
		$attrs['linkDestination'] = 'custom';
	}

	return array(
		'blockName'   => 'core/image',
		'attrs'       => $attrs,
		'innerBlocks' => array(),
		'innerHTML'   => '<figure class="wp-block-image is-resized">' . $inner . '</figure>',
	);
};

/**
 * Build one `campaignbridge/column` node.
 *
 * @param array<int, array<string, mixed>> $children Column children.
 * @param array<string, mixed>             $attrs    Column attributes.
 * @return array<string, mixed>
 */
$column = static function ( array $children, array $attrs = array() ): array {
	return array(
		'blockName'   => 'campaignbridge/column',
		'attrs'       => $attrs,
		'innerBlocks' => $children,
	);
};

return array(
	'universal-newsletter' => array(
		'label'     => 'Full supported grammar: preheader, sections, brand logo, navigation, social links, image, linked video poster, rich text, list, button, divider, spacer, compliance footer.',
		'covers'    => array( 'document-shell', 'preheader', 'section', 'brand-logo', 'navigation', 'social-links', 'image', 'video', 'rich-text', 'links', 'list', 'button', 'divider', 'spacer', 'compliance-footer' ),
		'brand_kit' => array(
			'logo' => array(
				'url'      => 'https://example.com/brand-logo.png',
				'alt'      => 'Example Co',
				'width'    => 800,
				'height'   => 240,
				'link_url' => 'https://example.com/',
			),
		),
		'metadata' => array(
			'title'            => 'Universal newsletter compatibility fixture',
			'language'         => 'en',
			'background_color' => '#f4f4f4',
			'unsubscribe_url'  => 'https://example.com/unsubscribe',
		),
		'blocks'   => array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array( 'maxWidth' => 600 ),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/preheader',
						'attrs'       => array( 'content' => 'Your November digest is here' ),
						'innerBlocks' => array(),
					),
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(
							'padding'         => array(
								'top'    => 24,
								'right'  => 24,
								'bottom' => 24,
								'left'   => 24,
							),
							'backgroundColor' => '#ffffff',
						),
						'innerBlocks' => array(
							array(
								'blockName'   => 'core/image',
								'attrs'       => array(
									'width'           => 240,
									'align'           => 'center',
									'linkDestination' => 'custom',
									'metadata'        => array(
										'bindings' => array(
											'url'  => array( 'source' => 'campaignbridge/brand-data', 'args' => array( 'field' => 'logoUrl' ) ),
											'alt'  => array( 'source' => 'campaignbridge/brand-data', 'args' => array( 'field' => 'logoAlt' ) ),
											'href' => array( 'source' => 'campaignbridge/brand-data', 'args' => array( 'field' => 'logoLink' ) ),
										),
									),
								),
								'innerBlocks' => array(),
								'innerHTML'   => '',
							),
							array(
								'blockName'   => 'campaignbridge/navigation',
								'attrs'       => array(
									'items' => array(
										array(
											'label' => 'Home',
											'url'   => 'https://example.com/home',
										),
										array(
											'label' => 'Shop',
											'url'   => 'https://example.com/shop',
										),
										array(
											'label' => 'Support',
											'url'   => 'https://example.com/support',
										),
									),
								),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'core/social-links',
								'attrs'       => array(
									'align'      => 'center',
									'showLabels' => false,
									'size'       => 'has-normal-icon-size',
								),
								'innerBlocks' => array(
									array(
										'blockName'   => 'core/social-link',
										'attrs'       => array(
											'service' => 'instagram',
											'url'     => 'https://example.com/instagram',
											'label'   => 'Follow Example Co on Instagram',
										),
										'innerBlocks' => array(),
									),
									array(
										'blockName'   => 'core/social-link',
										'attrs'       => array(
											'service' => 'youtube',
											'url'     => 'https://example.com/youtube',
										),
										'innerBlocks' => array(),
									),
								),
								'innerHTML'   => '<ul class="wp-block-social-links"></ul>',
							),
							$image( 'https://example.com/hero.jpg', 'Campaign hero', 600, 320, 'https://example.com/story' ),
							array(
								'blockName'   => 'campaignbridge/video',
								'attrs'       => array(
									'posterUrl' => 'https://example.com/video-poster.jpg',
									'posterAlt' => 'A preview of the product demonstration',
									'videoUrl'  => 'https://example.com/videos/demo',
									'label'     => 'Watch the product demo',
									'width'     => 600,
									'height'    => 338,
								),
								'innerBlocks' => array(),
							),
							$heading( 'Build &amp; send confidently', 1, array( 'color' => array( 'text' => '#111111' ) ) ),
							$paragraph(
								'A <strong>deterministic</strong> message with an <a href="https://example.com/docs" target="_blank" rel="noreferrer noopener">auditable link</a>.',
								array( 'color' => array( 'text' => '#333333' ) )
							),
							array(
								'blockName'   => 'core/list',
								'attrs'       => array(),
								'innerBlocks' => array(
									array(
										'blockName'   => 'core/list-item',
										'attrs'       => array( 'content' => 'Deterministic compilation' ),
										'innerBlocks' => array(),
									),
									array(
										'blockName'   => 'core/list-item',
										'attrs'       => array( 'content' => 'Immutable content snapshots' ),
										'innerBlocks' => array(),
									),
								),
							),
							$button( 'Read the story', 'https://example.com/story' ),
							array(
								'blockName'   => 'core/spacer',
								'attrs'       => array( 'height' => '24px' ),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'core/separator',
								'attrs'       => array(
									'style'     => array( 'color' => array( 'background' => '#dddddd' ) ),
									'className' => 'is-style-wide',
								),
								'innerBlocks' => array(),
							),
						),
					),
					array(
						'blockName'   => 'campaignbridge/compliance-footer',
						'attrs'       => array(
							'businessName' => 'Example Co',
							'address'      => '123 Example St, Portland, OR 97201',
						),
						'innerBlocks' => array(),
					),
				),
			),
		),
	),

	'stacked-columns'      => array(
		'label'    => 'Three columns that stack on mobile, each holding an image, heading, text, and button.',
		'covers'   => array( 'columns', 'mobile-stacking', 'column-gap', 'image', 'button' ),
		'metadata' => array(
			'title'            => 'Stacked columns compatibility fixture',
			'language'         => 'en',
			'background_color' => '#f4f4f4',
			'unsubscribe_url'  => 'https://example.com/unsubscribe',
		),
		'blocks'   => array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array( 'maxWidth' => 600 ),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array(
							array(
								'blockName'   => 'campaignbridge/columns',
								'attrs'       => array(
									'gap'               => 24,
									'verticalAlign'     => 'top',
									'isStackedOnMobile' => true,
								),
								'innerBlocks' => array(
									$column(
										array(
											$image( 'https://example.com/one.jpg', 'First story', 160, 120 ),
											$heading( 'First', 3 ),
											$paragraph( 'Column one copy.' ),
										)
									),
									$column(
										array(
											$image( 'https://example.com/two.jpg', 'Second story', 160, 120 ),
											$heading( 'Second', 3 ),
											$paragraph( 'Column two copy.' ),
										)
									),
									$column(
										array(
											$image( 'https://example.com/three.jpg', 'Third story', 160, 120 ),
											$heading( 'Third', 3 ),
											$button( 'Open', 'https://example.com/three', 'outline', null ),
										)
									),
								),
							),
						),
					),
					array(
						'blockName'   => 'campaignbridge/compliance-footer',
						'attrs'       => array(
							'businessName' => 'Example Co',
							'address'      => '123 Example St, Portland, OR 97201',
						),
						'innerBlocks' => array(),
					),
				),
			),
		),
	),

	'branded-typography'   => array(
		'label'     => 'Brand kit web font and resolved design colours across headings, text, and button variants.',
		'covers'    => array( 'web-font-fallback', 'resolved-design', 'heading-levels', 'button-variants' ),
		'brand_kit' => array(
			'colors' => array(
				'brand'    => '#7a1fa2',
				'on-brand' => '#ffffff',
				'text'     => '#1a1a1a',
			),
			'fonts'  => array(
				'heading' => 'inter',
				'body'    => 'inter',
				'button'  => 'inter',
			),
		),
		'metadata'  => array(
			'title'            => 'Branded typography compatibility fixture',
			'language'         => 'en',
			'background_color' => '#f4f4f4',
			'unsubscribe_url'  => 'https://example.com/unsubscribe',
		),
		'blocks'    => array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array( 'maxWidth' => 600 ),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array( 'backgroundColor' => '#ffffff' ),
						'innerBlocks' => array(
							$heading( 'Level one', 1, array( 'typography' => array( 'fontFamily' => 'inter' ) ) ),
							$heading( 'Level two', 2, array( 'typography' => array( 'fontFamily' => 'inter' ) ) ),
							$heading( 'Level three', 3 ),
							$heading( 'Level four', 4 ),
							$paragraph(
								'Body copy in the brand face with a <a href="https://example.com/brand">brand link</a>.',
								array( 'typography' => array( 'fontFamily' => 'inter' ) )
							),
							$button( 'Primary', 'https://example.com/primary', '', 'left' ),
							$button( 'Outline', 'https://example.com/outline', 'outline', 'left' ),
							$button( 'Text link', 'https://example.com/ghost', 'ghost', 'left' ),
						),
					),
					array(
						'blockName'   => 'campaignbridge/compliance-footer',
						'attrs'       => array(
							'businessName' => 'Example Co',
							'address'      => '123 Example St, Portland, OR 97201',
						),
						'innerBlocks' => array(),
					),
				),
			),
		),
	),
);
