<?php
/**
 * Simple Form Example - Working Form with Proper Submission Handling
 *
 * This shows how to create a working form with proper submission handling,
 * validation, and success messages. Copy-paste this code into any screen file!
 */

// File loaded successfully

// Step 1: Include the Form system
require_once __DIR__ . '/../Core/Form.php';

// Step 2: Create your form
$form = \CampaignBridge\Admin\Core\Form::make( 'simple_example' )
	->options() // Save to WordPress options
	->description( 'This is a simple form example.' )
	->text( 'your_name', 'Your Name' )->required()->end()
	->email( 'your_email', 'Your Email' )->required()->end()
	// ->success('Custom success message') // Optional - defaults provided
	// ->error('Custom error message')     // Optional - defaults provided
	->submit( 'Submit Form' );

// Step 4: Render the form
$form->render();

// 🎉 Success/error messages now automatically appear as WordPress admin notices!
// No manual use_screen_notices() calls needed - happens automatically during form processing.

echo '<hr><h3>How to customize this:</h3>';
echo '<ul>';
echo '<li>Add more fields: <code>->textarea(\'message\', \'Message\')->required()</code></li>';
echo '<li>Add validation: <code>->minLength(5)</code></li>';
echo '<li>Add placeholders: <code>->placeholder(\'Enter your name\')</code></li>';
echo '<li>Add descriptions: <code>->description(\'Help text here\')</code></li>';
echo '<li>Save to database: <code>->afterSave(function($data) { /* save logic */ })</code></li>';
echo '</ul>';
