<?php
/**
 * Script to fix WordPress function calls by adding global namespace prefix
 */

echo "=== Fixing WordPress Function Namespace Issues ===\n";

// List of WordPress functions that need global namespace prefix
$wp_functions = array(
	'add_action',
	'add_filter',
	'add_menu_page',
	'add_submenu_page',
	'add_meta_box',
	'add_settings_error',
	'wp_register_script',
	'wp_register_style',
	'wp_enqueue_script',
	'wp_enqueue_style',
	'wp_enqueue_media',
	'wp_add_inline_script',
	'wp_json_encode',
	'wp_create_nonce',
	'wp_kses_post',
	'wp_trim_words',
	'wp_insert_post',
	'wp_verify_nonce',
	'wp_unslash',
	'wp_post_revision_title',
	'wp_count_posts',
	'wp_script_is',
	'wp_style_is',
	'wp_die',
	'get_option',
	'get_post',
	'get_posts',
	'get_post_types',
	'get_post_type',
	'get_post_type_object',
	'get_post_field',
	'get_post_meta',
	'get_post_thumbnail_id',
	'get_post_thumbnail_url',
	'get_permalink',
	'get_current_screen',
	'get_bloginfo',
	'get_user_locale',
	'get_locale',
	'get_attachment_image_url',
	'is_wp_error',
	'rest_ensure_response',
	'sanitize_key',
	'sanitize_text_field',
	'absint',
	'esc_html',
	'esc_attr',
	'esc_url',
	'esc_url_raw',
	'checked',
	'selected',
	'function_exists',
	'version_compare',
	'strpos',
	'file_exists',
	'require_once',
	'spl_autoload_register',
	'class_exists',
	'method_exists',
	'is_subclass_of',
	'is_array',
	'is_bool',
	'is_string',
	'is_int',
	'is_object',
	'count',
	'basename',
	'dirname',
	'glob',
	'str_replace',
	'strrpos',
	'substr',
	'strtolower',
	'preg_replace',
	'file_put_contents',
	'file_get_contents',
	'error_log',
	'defined',
	'constant',
	'plugin_dir_path',
	'plugin_dir_url',
	'plugin_basename',
	'load_plugin_textdomain',
	'add_action',
	'add_filter',
	'add_menu_page',
	'add_submenu_page',
	'add_meta_box',
	'add_settings_error',
	'wp_register_script',
	'wp_register_style',
	'wp_enqueue_script',
	'wp_enqueue_style',
	'wp_enqueue_media',
	'wp_add_inline_script',
	'wp_json_encode',
	'wp_create_nonce',
	'wp_kses_post',
	'wp_trim_words',
	'wp_insert_post',
	'wp_verify_nonce',
	'wp_unslash',
	'wp_post_revision_title',
	'wp_count_posts',
	'wp_script_is',
	'wp_style_is',
	'wp_die',
	'get_option',
	'get_post',
	'get_posts',
	'get_post_types',
	'get_post_type',
	'get_post_type_object',
	'get_post_field',
	'get_post_meta',
	'get_post_thumbnail_id',
	'get_post_thumbnail_url',
	'get_permalink',
	'get_current_screen',
	'get_bloginfo',
	'get_user_locale',
	'get_locale',
	'get_attachment_image_url',
	'is_wp_error',
	'rest_ensure_response',
	'sanitize_key',
	'sanitize_text_field',
	'absint',
	'esc_html',
	'esc_attr',
	'esc_url',
	'esc_url_raw',
	'checked',
	'selected',
	'function_exists',
	'version_compare',
	'strpos',
	'file_exists',
	'require_once',
	'spl_autoload_register',
	'class_exists',
	'method_exists',
	'is_subclass_of',
	'is_array',
	'is_bool',
	'is_string',
	'is_int',
	'is_object',
	'count',
	'basename',
	'dirname',
	'glob',
	'str_replace',
	'strrpos',
	'substr',
	'strtolower',
	'preg_replace',
	'file_put_contents',
	'file_get_contents',
	'error_log',
	'defined',
	'constant',
	'plugin_dir_path',
	'plugin_dir_url',
	'plugin_basename',
	'load_plugin_textdomain',
);

// Remove duplicates
$wp_functions = array_unique( $wp_functions );

$total_files    = 0;
$modified_files = 0;

// Process all PHP files in the includes directory
$files = glob( 'includes/**/*.php' );
foreach ( $files as $file ) {
	++$total_files;
	echo "Processing: $file\n";

	$content          = file_get_contents( $file );
	$original_content = $content;
	$modified         = false;

	foreach ( $wp_functions as $function ) {
		// Look for function calls without global namespace prefix
		// Pattern: function_name( - but not \function_name(
		$pattern = '/(?<!\\\\)\b' . preg_quote( $function, '/' ) . '\s*\(/';
		if ( preg_match( $pattern, $content ) ) {
			$content  = preg_replace( $pattern, '\\' . $function . '(', $content );
			$modified = true;
		}
	}

	if ( $modified ) {
		if ( file_put_contents( $file, $content ) ) {
			echo "  ✓ Modified: $file\n";
			++$modified_files;
		} else {
			echo "  ✗ Failed to modify: $file\n";
		}
	} else {
		echo "  - No changes needed\n";
	}
}

echo "\n=== Summary ===\n";
echo "Total files processed: $total_files\n";
echo "Files modified: $modified_files\n";
echo "Done!\n";
