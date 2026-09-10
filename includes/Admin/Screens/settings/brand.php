<?php
/**
 * Brand kit settings tab.
 *
 * @package CampaignBridge
 */

use CampaignBridge\Admin\Brand_Kit_Copy;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Services\Email\Google_Fonts;

$campaignbridge_brand_kit = ( new Brand_Kit_Repository() )->get();
$campaignbridge_brand     = Brand_Kit_Copy::payload( $campaignbridge_brand_kit );

global $screen;
if ( $screen ) {
	$screen->asset_enqueue_style(
		'campaignbridge-brand-kit',
		'dist/styles/admin/screens/brand-kit.asset.php',
		array( 'wp-components' )
	);
	$screen->asset_enqueue_script( 'campaignbridge-brand-kit', 'dist/scripts/admin/brand-kit/index.asset.php' );
	$screen->localize_script(
		'campaignbridge-brand-kit',
		'campaignbridgeBrandKit',
		array(
			'restUrl'              => rest_url( 'campaignbridge/v1/brand-kit' ),
			'nonce'                => wp_create_nonce( 'wp_rest' ),
			'kit'                  => $campaignbridge_brand,
			'externalFontsEnabled' => Google_Fonts::external_enabled(),
			'i18n'                 => array(
				'coloursTitle'     => __( 'Email colors', 'campaignbridge' ),
				'coloursHelp'      => __( 'These colors are used throughout your email templates. Choose colors with enough contrast for readable email content.', 'campaignbridge' ),
				'edit'             => __( 'Edit color', 'campaignbridge' ),
				'save'             => __( 'Save color', 'campaignbridge' ),
				'cancel'           => __( 'Cancel', 'campaignbridge' ),
				'saved'            => __( 'Brand color saved.', 'campaignbridge' ),
				'saveFailed'       => __( 'The brand color could not be saved.', 'campaignbridge' ),
				'colour'           => __( 'Color', 'campaignbridge' ),
				'slot'             => __( 'Slot', 'campaignbridge' ),
				'use'              => __( 'Use', 'campaignbridge' ),
				'empty'            => __( 'No brand colors are available.', 'campaignbridge' ),
				'sourceTheme'      => __( 'Current kit: imported from the active theme.', 'campaignbridge' ),
				'sourceCustom'     => __( 'Current kit: edited manually.', 'campaignbridge' ),
				'sourceDefaults'   => __( 'Current kit: CampaignBridge defaults.', 'campaignbridge' ),
				'typography'       => __( 'Typography', 'campaignbridge' ),
				'fontSave'         => __( 'Save font', 'campaignbridge' ),
				'fontSaveFailed'   => __( 'The font could not be saved.', 'campaignbridge' ),
				'fontFamily'       => __( 'Font', 'campaignbridge' ),
				'preview'          => __( 'Preview', 'campaignbridge' ),
				'primaryButton'    => __( 'Primary button', 'campaignbridge' ),
				'typographyHelp'   => __( 'Choose the fonts used in email templates. Web-safe fonts provide the most consistent email-client support.', 'campaignbridge' ),
				'headingUse'       => __( 'Default for heading and post-title blocks.', 'campaignbridge' ),
				'bodyUse'          => __( 'Default for body text and post excerpts.', 'campaignbridge' ),
				'buttonUse'        => __( 'Default for button and post-call-to-action blocks.', 'campaignbridge' ),
				'fontChange'       => __( 'Change font', 'campaignbridge' ),
				'fontLookup'       => __( 'Browse Google Fonts', 'campaignbridge' ),
				'fontLookupHelp'   => __( 'Search the Google Fonts catalog and add a font to your brand kit.', 'campaignbridge' ),
				'fontSearch'       => __( 'Font family', 'campaignbridge' ),
				'fontSearchButton' => __( 'Search fonts', 'campaignbridge' ),
				'fontSearchEmpty'  => __( 'No matching font families were found.', 'campaignbridge' ),
				'fontAdd'          => __( 'Add to brand kit', 'campaignbridge' ),
				'fontAdded'        => __( 'Google Font added. You can now assign it to a typography slot.', 'campaignbridge' ),
				'saving'           => __( 'Saving…', 'campaignbridge' ),
				'savedStatus'      => __( 'Saved', 'campaignbridge' ),
				'contrastPass'     => __( 'AA pass', 'campaignbridge' ),
				'contrastFail'     => __( 'Needs attention', 'campaignbridge' ),
			),
		)
	);
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from a redirect after a verified POST.
$campaignbridge_imported = isset( $_GET['imported'] ) ? sanitize_text_field( wp_unslash( $_GET['imported'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from a redirect after a verified POST.
$campaignbridge_restored = isset( $_GET['restored'] ) ? sanitize_text_field( wp_unslash( $_GET['restored'] ) ) : '';
?>
<div class="campaignbridge-brand-kit">

	<?php if ( 'theme' === $campaignbridge_imported ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Brand colors were imported from the active theme. Slots the theme could not fill kept their CampaignBridge defaults.', 'campaignbridge' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '1' === $campaignbridge_restored ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Brand colors were restored to the CampaignBridge defaults.', 'campaignbridge' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="campaignbridge-brand-kit__overview">
		<section class="cb-admin-card campaignbridge-brand-kit__hero">
			<div class="cb-admin-icon-disc campaignbridge-brand-kit__hero-icon" aria-hidden="true">
				<span class="dashicons dashicons-art"></span>
			</div>
			<div class="campaignbridge-brand-kit__hero-copy">
				<h2><?php esc_html_e( 'Your email brand, everywhere you send', 'campaignbridge' ); ?></h2>
				<p><?php esc_html_e( 'These settings control the colors and typography used in your email templates. Changes are available when creating new templates, and theme colors can be imported at any time.', 'campaignbridge' ); ?></p>
				<div class="campaignbridge-brand-kit__actions">
					<form method="post" class="campaignbridge-brand-kit__action">
						<?php wp_nonce_field( 'campaignbridge_import_brand' ); ?>
						<button type="submit" name="import_brand_kit" value="1" class="button button-primary">
							<span class="dashicons dashicons-upload" aria-hidden="true"></span>
							<?php esc_html_e( 'Import from theme', 'campaignbridge' ); ?>
						</button>
					</form>
					<form method="post" class="campaignbridge-brand-kit__action">
						<?php wp_nonce_field( 'campaignbridge_restore_brand' ); ?>
						<button type="submit" name="restore_brand_kit" value="1" class="button">
							<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
							<?php esc_html_e( 'Restore defaults', 'campaignbridge' ); ?>
						</button>
					</form>
				</div>
			</div>
			<div class="campaignbridge-brand-kit__email-sample" aria-hidden="true">
				<div class="campaignbridge-brand-kit__sample-bar"><i></i><i></i><i></i></div>
				<div class="campaignbridge-brand-kit__sample-body">
					<span class="dashicons dashicons-email-alt"></span>
					<div><small><?php esc_html_e( 'Your Brand', 'campaignbridge' ); ?></small><strong><?php esc_html_e( 'A bigger audience starts here', 'campaignbridge' ); ?></strong></div>
				</div>
				<span class="campaignbridge-brand-kit__sample-line"></span>
				<span class="campaignbridge-brand-kit__sample-line campaignbridge-brand-kit__sample-line--short"></span>
				<b><?php esc_html_e( 'Read our latest update', 'campaignbridge' ); ?></b>
			</div>
		</section>

		<aside class="cb-admin-card campaignbridge-brand-kit__tip">
			<div class="cb-admin-icon-disc" aria-hidden="true"><span class="dashicons dashicons-lightbulb"></span></div>
			<div>
				<h3><?php esc_html_e( 'Pro tip', 'campaignbridge' ); ?></h3>
				<p><?php esc_html_e( 'Start with colors from your WordPress theme, then fine-tune them here for a custom email look.', 'campaignbridge' ); ?></p>
				<a href="https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Learn about theme styles', 'campaignbridge' ); ?> <span aria-hidden="true">→</span>
				</a>
			</div>
		</aside>
	</div>

	<div
		id="campaignbridge-brand-kit-root"
		class="campaignbridge-brand-kit__dataviews"
		data-source="<?php echo esc_attr( $campaignbridge_brand_kit->source() ); ?>"
	></div>
</div>
