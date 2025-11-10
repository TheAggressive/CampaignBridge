<?php
/**
 * Post Types Configuration Screen.
 *
 * This is a simple screen with no tabs.
 * Controller auto-discovered: Post_Types_Controller (if exists).
 *
 * @package CampaignBridge\Admin\Screens
 */

// Include the Form API.
use CampaignBridge\Admin\Core\Form;

// Get data from controller.
global $screen;
if ( ! isset( $screen ) ) {
	$screen = null; // Fallback for PHPStan.
}
$campaignbridge_post_types    = $screen ? $screen->get( 'post_types', array() ) : array();
$campaignbridge_enabled_types = $screen ? $screen->get( 'enabled_types', array() ) : array();
if ( $screen ) {
	$screen->asset_enqueue_style( 'campaignbridge-post-types', 'dist/styles/admin/screens/post-types.asset.php' );
	$screen->asset_enqueue_script( 'campaignbridge-post-types', 'dist/scripts/editor/editor.js' );
}

// Create checkboxes for each post type.
$options = array();
foreach ( $campaignbridge_post_types as $post_type_key => $info ) {
	$options[ $post_type_key ] = $info['label'];
}

// Available post types and enabled types are loaded by the controller
// Create the form using the Form API.
$form = Form::make( 'post_types' )
	->description( 'Select which public post types can be used in CampaignBridge.' )
	->repeater( 'included_post_types', $options, $campaignbridge_enabled_types )->group()->switch()
	->save_to_options( 'campaignbridge_' )
	->success( 'Post types configuration saved successfully!' )
	->submit( 'Save Post Types' );
?>

<div class="campaignbridge-post-types">
	<div class="campaignbridge-post-types__content">
		<!-- Available Post Types Section -->
		<div class="campaignbridge-post-types__section">
			<div class="campaignbridge-post-types__section-header">
				<h2><?php esc_html_e( 'Included post types', 'campaignbridge' ); ?></h2>
			</div>

			<div class="campaignbridge-post-types__form">
				<?php
				// Render the form - handles all form logic, validation, and submission.
				$form->render();
				?>

				<p class="campaignbridge-post-types__help-text">
					<?php esc_html_e( 'Unchecked types will be unavailable when selecting posts.', 'campaignbridge' ); ?>
				</p>
			</div>
		</div>

		<!-- Usage Information Section -->
		<div class="campaignbridge-post-types__info-section">
			<div class="campaignbridge-post-types__info-content">
				<h3><?php esc_html_e( 'Usage Information', 'campaignbridge' ); ?></h3>
				<p>
					<?php esc_html_e( 'Enabled post types will be available when creating email campaigns. Only published posts from enabled post types can be included in campaigns.', 'campaignbridge' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
