<?php
/**
 * Server render for post-excerpt with inline/new-line placement using InnerBlocks.
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return function ( $attributes, $content, $block ) {
	$ctx     = isset( $block->context ) && is_array( $block->context ) ? $block->context : array();
	$post_id = isset( $ctx['campaignbridge:postId'] ) ? absint( $ctx['campaignbridge:postId'] ) : 0;
	if ( $post_id <= 0 ) {
		return '';
	}

	// Attributes with defaults.
	$max_words             = isset( $attributes['maxWords'] ) ? absint( $attributes['maxWords'] ) : 50;
	$show_more             = ! empty( $attributes['showMore'] );
	$more_style            = isset( $attributes['moreStyle'] ) ? (string) $attributes['moreStyle'] : 'link'; // 'link' | 'button'
	$link_to               = isset( $attributes['linkTo'] ) ? (string) $attributes['linkTo'] : 'post';       // 'post' | 'postType'
	$add_space_before_link = isset( $attributes['addSpaceBeforeLink'] ) ? (bool) $attributes['addSpaceBeforeLink'] : true;

	$enable_separator           = isset( $attributes['enableSeparator'] ) ? (bool) $attributes['enableSeparator'] : false;
	$separator_type             = isset( $attributes['separatorType'] ) ? (string) $attributes['separatorType'] : 'custom';
	$custom_separator           = isset( $attributes['customSeparator'] ) ? (string) $attributes['customSeparator'] : '';
	$add_space_before_separator = isset( $attributes['addSpaceBeforeSeparator'] ) ? (bool) $attributes['addSpaceBeforeSeparator'] : false;

	$more_placement = isset( $attributes['morePlacement'] ) ? (string) $attributes['morePlacement'] : 'new-line'; // 'new-line' | 'inline'

	// Build plain excerpt.
	$raw_excerpt = (string) get_post_field( 'post_excerpt', $post_id );
	$raw_content = (string) get_post_field( 'post_content', $post_id );
	$raw         = '' !== $raw_excerpt ? $raw_excerpt : $raw_content;
	$decoded     = html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' );
	$plain       = trim( preg_replace( '/<[^>]*>/', ' ', $decoded ) );

	$words         = preg_split( '/\s+/', $plain, -1, PREG_SPLIT_NO_EMPTY );
	$limited_words = array_slice( $words, 0, max( 0, $max_words ) );
	$excerpt       = esc_html( implode( ' ', $limited_words ) );
	if ( $show_more && substr( $excerpt, -1 ) === '.' ) {
		$excerpt = rtrim( substr( $excerpt, 0, -1 ) );
	}

	// Compute target URL.
	$link = '';
	if ( $show_more ) {
		if ( 'postType' === $link_to ) {
			$post_type = get_post_type( $post_id );
			if ( $post_type ) {
				$archive = get_post_type_archive_link( $post_type );
				if ( $archive ) {
					$link = $archive;
				}
			}
		}
		if ( '' === $link ) {
			$link = get_permalink( $post_id );
		}
	}

	// Prefix (separator or spacing) that precedes the saved inner blocks.
	$prefix = '';
	if ( $show_more ) {
		if ( $enable_separator && '' !== $custom_separator ) {
			$prefix = esc_html( $custom_separator ) . ' ';
			if ( $add_space_before_separator ) {
				$prefix = ' ' . $prefix;
			}
		} elseif ( $add_space_before_link ) {
			$prefix = ' ';
		}
	}

	// Start with the saved inner blocks content.
	$more_html = '';
	if ( $show_more && $content ) {
		$more_html = $content;

		// 1) Ensure href "#" becomes the real link.
		if ( $link ) {
			if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
				$p = new WP_HTML_Tag_Processor( $more_html );
				while ( $p->next_tag( 'a' ) ) {
					$href = $p->get_attribute( 'href' );
					if ( $href === '#' || $href === '' ) {
						$p->set_attribute( 'href', esc_url( $link ) );
					}
				}
				$more_html = $p->get_updated_html();
			} else {
				$more_html = preg_replace(
					'#href=(["\'])\#\1#i',
					'href="' . esc_url( $link ) . '"',
					$more_html
				);
			}
		}

		// 2) If placement is inline, add inline class to child wrapper/tag (frontend parity).
		if ( 'inline' === $more_placement ) {
			if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
				$p     = new WP_HTML_Tag_Processor( $more_html );
				$added = false;

				// Try Buttons wrapper first.
				while ( $p->next_tag( 'div' ) ) {
					$class = $p->get_attribute( 'class' );
					if ( is_string( $class ) && false !== strpos( $class, 'wp-block-buttons' ) ) {
						$p->set_attribute( 'class', trim( $class . ' is-inline-readmore' ) );
						$added = true;
						break;
					}
				}

				// Otherwise try Paragraph (link style).
				if ( ! $added ) {
					$p->seek( 0 );
					while ( $p->next_tag( 'p' ) ) {
						$class = $p->get_attribute( 'class' );
						if ( is_string( $class ) && false !== strpos( $class, 'wp-block-paragraph' ) ) {
							$p->set_attribute( 'class', trim( $class . ' is-inline-readmore' ) );
							break;
						}
					}
				}

				$more_html = $p->get_updated_html();
			} else {
				// Fallback: inject class via regex if Tag Processor isn't available.
				$cnt       = 0;
				$more_html = preg_replace(
					'#<div([^>]*class="[^"]*\bwp-block-buttons\b[^"]*)"#i',
					'<div$1 is-inline-readmore',
					$more_html,
					1,
					$cnt
				);
				if ( empty( $cnt ) ) {
					$more_html = preg_replace(
						'#<p([^>]*class="[^"]*\bwp-block-paragraph\b[^"]*)"#i',
						'<p$1 is-inline-readmore',
						$more_html,
						1
					);
					$more_html = str_replace( ' is-inline-readmore', ' class="is-inline-readmore"', $more_html );
				} else {
					$more_html = str_replace( ' is-inline-readmore', ' class="is-inline-readmore"', $more_html );
				}
			}
		}

		// Prepend separator/space.
		if ( $prefix ) {
			$more_html = $prefix . $more_html;
		}
	}

	// Wrapper attributes (adds has-inline-readmore on the outer wrapper for CSS hooks).
	$wrapper_attrs = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes(
			array(
				'class' => ( 'inline' === $more_placement ? 'has-inline-readmore' : '' ),
				'style' => 'font-size:14px;line-height:1.5;color:#333;',
			)
		)
		: 'style="font-size:14px;line-height:1.5;color:#333;"';

	return sprintf(
		'<div %s>%s%s</div>',
		$wrapper_attrs,
		$excerpt,
		$more_html
	);
};
