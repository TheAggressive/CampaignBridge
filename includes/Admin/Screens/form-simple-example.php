<?php
/**
 * SIMPLEST Form Example - Copy-Paste Ready
 *
 * This shows the absolute simplest way to use the Form system.
 * Just copy-paste this code into any screen file and it works!
 */

// Step 1: Include the Form system
require_once __DIR__ . '/../Core/Form.php';

// Step 2: Create your form (just 4 lines!)
$form = \CampaignBridge\Admin\Core\Form::make( 'simple_example' )
	->text( 'your_name', 'Your Name' )->required()->end()
	->email( 'your_email', 'Your Email' )->required()->end()
	->submit( 'Submit Form' );

// Step 3: Handle success (optional)
if ( $form->submitted() && $form->valid() ) {
	echo '<div class="notice notice-success"><p>Thank you! Form submitted successfully.</p></div>';
}

// Step 4: Render the form (that's it!)
$form->render();

echo '<hr><h3>How to customize this:</h3>';
echo '<ul>';
echo '<li>Add more fields: <code>->textarea(\'message\', \'Message\')->required()</code></li>';
echo '<li>Add validation: <code>->minLength(5)</code></li>';
echo '<li>Add placeholders: <code>->placeholder(\'Enter your name\')</code></li>';
echo '<li>Add descriptions: <code>->description(\'Help text here\')</code></li>';
echo '<li>Save to database: <code>->afterSave(function($data) { /* save logic */ })</code></li>';
echo '</ul>';
