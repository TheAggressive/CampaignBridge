<?php
/**
 * Server-side render for the cb/container block.
 * - Uses core color support for background/text colors.
 * - Uses attributes for maxWidth, outerPadding, and inner padding.
 * - Outputs email-safe table markup with inline styles.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Rendered InnerBlocks HTML.
 * @return string
 * @package CampaignBridge
 */

if ( ! function_exists( 'campaignbridge_email_attr' ) ) {
	/**
	 * Compile inline style attribute from key=>value pairs.
	 *
	 * @param array $styles Key-value pairs of styles.
	 * @return string Inline style attribute.
	 */
	function campaignbridge_email_attr( $styles ) {
		$out = array();
		foreach ( $styles as $k => $v ) {
			if ( '' === $v || null === $v ) {
				continue;
			}
			$out[] = $k . ':' . $v;
		}
		return $out ? ' style="' . esc_attr( implode( ';', $out ) ) . ';"' : '';
	}
}

$attrs = wp_parse_args(
	(array) $attributes,
	array(
		'maxWidth'     => 600,
		'outerPadding' => array(
			'top'    => 0,
			'right'  => 0,
			'bottom' => 0,
			'left'   => 0,
		),
		'padding'      => array(
			'top'    => 0,
			'right'  => 24,
			'bottom' => 0,
			'left'   => 24,
		),
	)
);

// Colors provided by core color support.
$style          = isset( $attributes['style'] ) ? (array) $attributes['style'] : array();
$color_style    = isset( $style['color'] ) ? (array) $style['color'] : array();
$background_hex = isset( $color_style['background'] ) ? (string) $color_style['background'] : '#ffffff';
$text_hex       = isset( $color_style['text'] ) ? (string) $color_style['text'] : '#000000';

$max_width = (int) $attrs['maxWidth'];

// Regular container rendering (when not global).
$op             = array_merge(
	array(
		'top'    => 0,
		'right'  => 0,
		'bottom' => 0,
		'left'   => 0,
	),
	(array) $attrs['outerPadding']
);
$outer_td_style = campaignbridge_email_attr(
	array(
		'background' => $background_hex,
		'color'      => $text_hex,
		'margin'     => '0',
		'padding'    => "{$op['top']}px {$op['right']}px {$op['bottom']}px {$op['left']}px",
	)
);

// Inner table (fixed width, centered).
$inner_table_style = campaignbridge_email_attr(
	array(
		'width'            => $max_width . 'px',
		'max-width'        => '100%',
		'background'       => $background_hex, // single background source (core color).
		'color'            => $text_hex,
		'mso-table-lspace' => '0pt',
		'mso-table-rspace' => '0pt',
		'border-collapse'  => 'collapse',
	)
);

// Inner content cell padding.
$ip             = array_merge(
	array(
		'top'    => 0,
		'right'  => 0,
		'bottom' => 0,
		'left'   => 0,
	),
	(array) $attrs['padding']
);
$inner_td_style = campaignbridge_email_attr(
	array(
		'padding'              => "{$ip['top']}px {$ip['right']}px {$ip['bottom']}px {$ip['left']}px",
		'mso-line-height-rule' => 'exactly',
		'color'                => $text_hex,
	)
);

ob_start();
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
	<tbody>
	<tr>
		<td align="center"<?php echo $outer_td_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped by campaignbridge_email_attr(). ?>
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="<?php echo (int) $max_width; ?>"
																									<?php
																									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped by campaignbridge_email_attr().
																									echo $inner_table_style;
																									?>
																									>
				<tbody>
				<tr>
					<td align="left"<?php echo $inner_td_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped by campaignbridge_email_attr(). ?>
						<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is already escaped by WordPress block rendering. ?>
					</td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
	</tbody>
</table>
<?php
return ob_get_clean();
