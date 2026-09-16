<?php
/**
 * Email Template Editor Screen
 *
 * This is a simple screen with no tabs.
 * Controller auto-discovered: Editor_Controller (if exists)
 *
 * @package CampaignBridge
 */

?>

<div
	id="cb-block-editor-root"
	class="editor-screen"
	data-duplicable-meta-keys="<?php echo esc_attr( (string) wp_json_encode( \CampaignBridge\Post_Types\Post_Type_Email_Template::get_duplicable_meta_keys() ) ); ?>"
	data-revisioned-meta-keys="<?php echo esc_attr( (string) wp_json_encode( \CampaignBridge\Post_Types\Post_Type_Email_Template::get_revisioned_meta_keys() ) ); ?>"
></div>
