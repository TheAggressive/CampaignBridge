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
$campaignbridge_post_types    = $screen->get( 'post_types', array() );
$campaignbridge_enabled_types = $screen->get( 'enabled_types', array() );
$screen->asset_enqueue_style( 'campaignbridge-post-types', 'dist/styles/admin/post-types.asset.php' );
$editor = $screen->asset_enqueue_script( 'campaignbridge-post-types', 'dist/scripts/editor/editor.js' );

// Create checkboxes for each post type.
$checkboxes = array();
foreach ( $campaignbridge_post_types as $post_type_key => $info ) {
	$checkboxes[ $post_type_key ] = $info['label'];
}

// Available post types and enabled types are loaded by the controller
// Create the form using the Form API.
$form = Form::make( 'post_types' )
	->description( 'Select which public post types can be used in CampaignBridge.' )
	->repeater( 'included_post_types', $checkboxes, $campaignbridge_enabled_types )->switch()

	// Save to options table - the Form API now handles multiple fields reliably.
	->save_to_options( 'campaignbridge_' )

	->success( 'Post types configuration saved successfully!' )
	->submit( 'Save Post Types' );
?>

<div class="campaignbridge-post-types">
	<div class="campaignbridge-post-types__header">
		<h1><?php esc_html_e( 'Post Types', 'campaignbridge' ); ?></h1>
		<?php settings_errors( 'campaignbridge_post_types' ); ?>
	</div>

	<div class="campaignbridge-post-types__content">
		<!-- Available Post Types Section -->
		<div class="campaignbridge-post-types__section">
			<div class="campaignbridge-post-types__section-header">
				<h2><?php esc_html_e( 'Included post types', 'campaignbridge' ); ?></h2>
				<p class="campaignbridge-post-types__description">
					<?php esc_html_e( 'Select which public post types can be used in CampaignBridge.', 'campaignbridge' ); ?>
				</p>
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

<style>
/* CampaignBridge Post Types Styles - Following BEM conventions */

.campaignbridge-post-types {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	margin-top: 20px;
	box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

.campaignbridge-post-types__header {
	padding: 24px 24px 0 24px;
	border-bottom: 1px solid #e5e5e5;
	margin-bottom: 0;
}

.campaignbridge-post-types__header h1 {
	margin: 0 0 12px 0;
	font-size: 23px;
	font-weight: 400;
	line-height: 1.3;
}

.campaignbridge-post-types__content {
	padding: 24px;
}

.campaignbridge-post-types__section {
	margin-bottom: 32px;
}

.campaignbridge-post-types__section-header h2 {
	margin: 0 0 8px 0;
	font-size: 16px;
	font-weight: 600;
	line-height: 1.4;
}

.campaignbridge-post-types__description {
	margin: 0 0 20px 0;
	color: #666;
	font-size: 14px;
	line-height: 1.5;
}

.campaignbridge-post-types__form {
	background: #f8f9fa;
	padding: 24px;
	border: 1px solid #e5e5e5;
	border-radius: 6px;
	margin-bottom: 16px;
}

.campaignbridge-post-types__switches-box {
	margin-bottom: 16px;
}

.campaignbridge-post-types__switches-group {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.campaignbridge-post-types__switches-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 16px;
}

.campaignbridge-post-types__switch {
	position: relative;
	display: flex;
	align-items: center;
	padding: 12px 16px;
	background: #fff;
	border: 1px solid #d0d0d0;
	border-radius: 6px;
	cursor: pointer;
	transition: all 0.2s ease;
	font-size: 14px;
	line-height: 1.4;
}

.campaignbridge-post-types__switch:hover {
	border-color: #007cba;
	box-shadow: 0 0 0 1px #007cba;
}

.campaignbridge-post-types__checkbox {
	position: absolute;
	opacity: 0;
	width: 0;
	height: 0;
	margin: 0;
}

.campaignbridge-post-types__slider {
	position: relative;
	display: inline-block;
	width: 44px;
	height: 24px;
	background-color: #d0d0d0;
	border-radius: 12px;
	margin-right: 12px;
	transition: background-color 0.3s ease;
	flex-shrink: 0;
}

.campaignbridge-post-types__slider::before {
	content: '';
	position: absolute;
	top: 2px;
	left: 2px;
	width: 20px;
	height: 20px;
	background-color: #fff;
	border-radius: 50%;
	transition: transform 0.3s ease;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.campaignbridge-post-types__checkbox:checked + .campaignbridge-post-types__slider {
	background-color: #007cba;
}

.campaignbridge-post-types__checkbox:checked + .campaignbridge-post-types__slider::before {
	transform: translateX(20px);
}

.campaignbridge-post-types__switch-label {
	flex: 1;
	font-weight: 500;
	color: #1d2327;
}

.campaignbridge-post-types__help-text {
	margin: 16px 0 20px 0;
	color: #666;
	font-size: 13px;
	font-style: italic;
	line-height: 1.4;
}

.campaignbridge-post-types__info-section {
	background: #f8f9fa;
	border: 1px solid #e5e5e5;
	border-radius: 6px;
	padding: 20px;
}

.campaignbridge-post-types__info-content h3 {
	margin: 0 0 12px 0;
	font-size: 14px;
	font-weight: 600;
	color: #1d2327;
}

.campaignbridge-post-types__info-content p {
	margin: 0;
	color: #50575e;
	font-size: 13px;
	line-height: 1.5;
}

/* WordPress admin integration */
.campaignbridge-post-types .submit {
	margin-top: 0;
	padding-top: 0;
	border-top: none;
}

/* Responsive adjustments */
@media screen and (max-width: 782px) {
	.campaignbridge-post-types__switches-grid {
		grid-template-columns: 1fr;
	}

	.campaignbridge-post-types__content {
		padding: 16px;
	}

	.campaignbridge-post-types__header {
		padding: 16px 16px 0 16px;
	}
}
</style>
