<?php
/**
 * Brand kit settings tab.
 *
 * @package CampaignBridge
 */

use CampaignBridge\Admin\Brand_Kit_Copy;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Repository\Brand_Kit_Repository;

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
			'restUrl' => rest_url( 'campaignbridge/v1/brand-kit' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'kit'     => $campaignbridge_brand,
			'i18n'    => array(
				'edit'           => __( 'Edit colour', 'campaignbridge' ),
				'save'           => __( 'Save colour', 'campaignbridge' ),
				'cancel'         => __( 'Cancel', 'campaignbridge' ),
				'saved'          => __( 'Brand colour saved.', 'campaignbridge' ),
				'saveFailed'     => __( 'The brand colour could not be saved.', 'campaignbridge' ),
				'colour'         => __( 'Colour', 'campaignbridge' ),
				'slot'           => __( 'Slot', 'campaignbridge' ),
				'use'            => __( 'Use', 'campaignbridge' ),
				'empty'          => __( 'No brand colours are available.', 'campaignbridge' ),
				'sourceTheme'    => __( 'Current kit: imported from the active theme.', 'campaignbridge' ),
				'sourceCustom'   => __( 'Current kit: edited manually.', 'campaignbridge' ),
				'sourceDefaults' => __( 'Current kit: CampaignBridge defaults.', 'campaignbridge' ),
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
	<h2><?php esc_html_e( 'Email brand', 'campaignbridge' ); ?></h2>
	<p class="campaignbridge-brand-kit__intro">
		<?php esc_html_e( 'These slots are what the email editor and compiler share. Import copies portable colours from the active theme; it does not keep the theme live. Custom hex is allowed when it can be inlined.', 'campaignbridge' ); ?>
	</p>

	<?php if ( 'theme' === $campaignbridge_imported ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Brand colours were imported from the active theme. Slots the theme could not fill kept their CampaignBridge defaults.', 'campaignbridge' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '1' === $campaignbridge_restored ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Brand colours were restored to the CampaignBridge defaults.', 'campaignbridge' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="campaignbridge-brand-kit__actions">
		<form method="post" class="campaignbridge-brand-kit__action">
			<?php wp_nonce_field( 'campaignbridge_import_brand' ); ?>
			<button type="submit" name="import_brand_kit" value="1" class="button">
				<?php esc_html_e( 'Import from theme', 'campaignbridge' ); ?>
			</button>
		</form>
		<form method="post" class="campaignbridge-brand-kit__action">
			<?php wp_nonce_field( 'campaignbridge_restore_brand' ); ?>
			<button type="submit" name="restore_brand_kit" value="1" class="button">
				<?php esc_html_e( 'Restore defaults', 'campaignbridge' ); ?>
			</button>
		</form>
	</div>

	<div
		id="campaignbridge-brand-kit-root"
		class="campaignbridge-brand-kit__dataviews"
		data-source="<?php echo esc_attr( $campaignbridge_brand_kit->source() ); ?>"
	></div>
</div>
