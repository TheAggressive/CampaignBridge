# CampaignBridge Forms System Documentation

A modern, trait-based forms system for WordPress admin interfaces with automatic security validation, fluent API chaining, and comprehensive field support. This documentation covers the current trait-based architecture with automatic field management and enhanced security.

## Table of Contents

### Getting Started
1. [Quick Start](#quick-start)
2. [Basic Usage](#basic-usage)
   - Method Naming Convention
   - Fluent API Chaining

### Core Concepts
3. [Field Types](#field-types)
   - Standard HTML5 Fields
   - Choice Fields
   - Advanced Fields
   - Encrypted Fields
4. [Form Configuration](#form-configuration)
   - Layout Options
   - Form Methods and Actions

### Data Management
5. [Data Sources & Storage](#data-sources--storage)
6. [Data Encryption & Security](#data-encryption--security)
   - Encryption Classes
   - Security Contexts
   - Encrypted Field Types

### Security & Validation
7. [Validation](#validation)
   - Built-in Validation Rules
   - Custom Validation
8. [Security](#security)
   - Automatic Security Features
   - File Upload Security

### Advanced Features
9. [Conditional Fields](#conditional-fields)
10. [Dynamic Field Options](#dynamic-field-options)
11. [Multi-Step Forms](#multi-step-forms)
12. [Custom Fields](#custom-fields)
13. [Hooks & Lifecycle](#hooks--lifecycle)

### Presentation & UX
14. [Universal Notice System](#universal-notice-system)
15. [Styling & Theming](#styling--theming)

### Developer Experience
16. [Troubleshooting](#troubleshooting)
17. [Best Practices](#best-practices)
18. [Architecture Overview](#architecture-overview)
19. [Recent Changes](#recent-changes)

## Quick Start

### Creating Your First Form

```php
<?php
use CampaignBridge\Admin\Core\Form;

// Create a simple settings form with seamless chaining
$form = Form::settings_api('my_settings')
    ->text('site_name', 'Site Name')
        ->required()
        ->placeholder('My Awesome Site')

    ->email('admin_email', 'Admin Email')
        ->required()
        ->placeholder('admin@example.com')

    ->textarea('description', 'Site Description')
        ->rows(3)
        ->placeholder('Describe your site...')

    ->select('theme', 'Theme')
        ->fieldOptions([
            'light' => 'Light Theme',
            'dark' => 'Dark Theme',
            'auto' => 'Auto Theme'
        ])
        ->default('light')

    ->end() // Return to Form_Builder for configuration methods

    ->success('Settings saved successfully!')
    ->error('Please correct the errors and try again.')
    ->submit('Save Settings');

// In your admin page template
function render_settings_page() {
    global $form;

    // ✨ Success/error messages are displayed automatically!
    // The form_start() method automatically includes settings_errors()
    // so validation messages appear in the standard WordPress location

    echo $form->form_start();

    echo '<div class="wrap">';
    echo '<h1>My Settings</h1>';

    // Form fields go here
    echo $form->render_field('site_name');
    echo $form->render_field('admin_email');
    echo $form->render_field('description');
    echo $form->render_field('theme');

    // Form actions
    echo $form->render_submit();
    echo '</div>';

    echo $form->form_end();
}

// 🎉 Universal Compatibility: The form system works in any WordPress context
```

### Pre-configured Forms

```php
// Contact form
$contact_form = Form_Factory::contact('contact_form');

// User registration form
$register_form = Form_Factory::register('user_register');

// Settings form (table layout)
$settings_form = Form_Factory::settings_api('plugin_settings');
```

## Basic Usage

### Trait-Based Architecture

The forms system uses a modern **trait-based architecture** that provides consistent APIs across all form contexts:

- **`Form_Field_Methods`** - Field creation methods (`text()`, `password()`, `select()`, etc.)
- **`Form_Config_Methods`** - Field configuration (`required()`, `default()`, `options()`, etc.)
- **`Form_Hook_Methods`** - Event callbacks (`before_save()`, `after_save()`, etc.)
- **`Form_Layout_Methods`** - Layout control (`description()`, `auto_layout()`, etc.)

**All traits are automatically available** on `Form` and `Form_Field_Builder` instances through composition.

### Method Naming Convention

All Form API methods use **snake_case** naming (e.g., `before_save`, `after_validate`, `on_success`). This follows WordPress PHP coding standards and is enforced throughout the codebase.

```php
// ✅ Correct - snake_case (trait-based)
$form->text('username', 'Username')
     ->required()                    // Form_Config_Methods trait
     ->before_save($callback);       // Form_Hook_Methods trait

// ❌ Incorrect - camelCase (not allowed)
$form->beforeSave($callback);        // Wrong!
$form->afterValidate($callback);     // Wrong!
$form->onSuccess($callback);         // Wrong!
```

**Note**: The trait system ensures consistent naming and availability across all form contexts.

### Creating Forms

#### Method 1: Static Factory Methods

```php
$form = Form::make('my_form_id')
    ->text('name', 'Full Name')
    ->email('email', 'Email Address')
    ->submit('Submit Form');
```

#### Method 2: Fluent Configuration

```php
$form = Form::make('contact_form')
    ->method('POST')
    ->action(admin_url('admin-post.php'))
    ->table() // Use table layout
    ->success('Thank you for your message!')
    ->error('Please correct the errors below.');
```

### Fluent API Chaining with Traits

The Form API uses **trait-based composition** for seamless chaining across all contexts. The system automatically manages field lifecycles through the `__call` proxy mechanism in `Form_Field_Builder`.

#### Automatic Field Management (Trait-Powered)
```php
$form = Form::make('user_profile')
    // ✅ Trait-based chaining - Form_Field_Methods + Form_Config_Methods
    ->text('first_name', 'First Name')      // Form_Field_Methods trait
        ->required()                        // Form_Config_Methods trait
        ->placeholder('Enter your first name')

    ->email('email', 'Email Address')       // ← Automatically closes previous field via __call
        ->required()                        // Form_Config_Methods trait
        ->placeholder('your@email.com')

    ->textarea('bio', 'Biography')          // Form_Field_Methods trait
        ->rows(4)                           // Form_Config_Methods trait
        ->description('Tell us about yourself') // Form_Layout_Methods trait

    ->select('country', 'Country')          // Form_Field_Methods trait
        ->options([                         // Form_Config_Methods trait
            'us' => 'United States',
            'ca' => 'Canada',
            'uk' => 'United Kingdom'
        ])
        ->default('us')                     // Form_Config_Methods trait
        ->before_save($callback)            // Form_Hook_Methods trait

    ->switch('notifications', 'Enable Notifications') // Form_Field_Methods trait
        ->default(true)                     // Form_Config_Methods trait

    // ✅ Form-level methods (all traits available)
    ->success('Profile saved successfully!') // Form_Config_Methods trait
    ->error('Please correct the errors below.')   // Form_Config_Methods trait
    ->submit('Save Profile');               // Form_Config_Methods trait
```

**How it works:** The trait system provides consistent APIs:
- **Field Creation**: `Form_Field_Methods` (available on `Form` and `Form_Field_Builder`)
- **Field Configuration**: `Form_Config_Methods` (available everywhere)
- **Event Hooks**: `Form_Hook_Methods` (available everywhere)
- **Layout Control**: `Form_Layout_Methods` (available everywhere)

**Automatic field closing** happens through the `__call` proxy in `Form_Field_Builder`, which detects field creation methods and automatically ends the current field context.

### Adding Fields

    // Checkbox
    ->checkbox('newsletter', 'Subscribe to newsletter')

    // Radio buttons
    ->radio('gender', 'Gender')
        ->fieldOptions([
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other'
        ]);
```

### Form Submission Handling

```php
$form = Form::make('contact_form');
// ... add fields ...

// Check if form was submitted
if ($form->submitted()) {
    if ($form->valid()) {
        // Process valid form data
        $data = $form->data();

        // Send email, save to database, etc.
        wp_mail($data['email'], 'Contact Form Submission', $data['message']);

        // Redirect or show success message
        wp_redirect(add_query_arg('success', '1', $_SERVER['REQUEST_URI']));
        exit;
    } else {
        // Show validation errors
        foreach ($form->errors() as $field => $error) {
            echo "<p class='error'>$error</p>";
        }
    }
}

// Render the form
echo $form->form_start();
// Your form content here
echo $form->render_field('username');
echo $form->render_field('email');
echo $form->render_submit();
echo $form->form_end();
```

## Universal Notice System

### Automatic Message Handling

The forms system provides universal, self-contained notice handling that works in any WordPress context:

```php
// Works everywhere - admin screens, frontend, standalone pages
$form = Form::make('my_form')
    ->text('field1', 'Field 1')->required()
    ->success('Data saved successfully!')
    ->error('Please check your input.')
    ->submit('Save');

// ✨ Fully automatic - no external dependencies needed!
echo $form->form_start();
echo $form->render_field('field1');
echo $form->render_submit();
echo $form->form_end();
// Success/error messages appear automatically as WordPress admin notices
```

### Self-Contained Notice Implementation

Form notices work automatically everywhere - no special handling or external dependencies needed:

```php
$form = Form::make('any_form')
    ->text('field1', 'Field 1')->required()
    ->success('Form saved successfully!')
    ->error('Please correct the errors.')
    ->submit('Save');

// Works in any context - admin screens, frontend, standalone pages, custom contexts
echo $form->form_start();
echo $form->render_field('field1');
echo $form->render_submit();
echo $form->form_end();
// Success/error messages display automatically as WordPress admin notices
```

**Implementation**: The form system uses `Form_Notice_Handler` to display notices directly through WordPress's `admin_notices` action hook.

### Benefits of Self-Contained Notice System

- **Complete Independence**: Form system owns messaging end-to-end with no external dependencies
- **Universal Compatibility**: Works identically in admin, frontend, or standalone contexts
- **Standard WordPress UI**: Uses official admin notice styling and dismissible behavior
- **Zero Configuration**: Just set `->success()` and `->error()` - notices appear automatically
- **Clean Architecture**: Single responsibility - forms handle their own notifications

## Field Types

### Standard HTML5 Fields

#### Text Input
```php
$form->text('username', 'Username')
    ->required()
    ->placeholder('Enter username')
    ->min_length(3)
    ->max_length(50)
    ->autocomplete('username');
```

#### Email Input
```php
$form->email('contact_email', 'Contact Email')
    ->required()
    ->placeholder('your@email.com')
    ->description('We will use this email to contact you');
```

#### Password Input
```php
$form->password('password', 'Password')
    ->required()
    ->min_length(8)
    ->description('Password must be at least 8 characters');
```

#### Number Input
```php
$form->number('age', 'Age')
    ->min(13)
    ->max(120)
    ->step(1)
    ->default(25);
```

#### URL Input
```php
$form->url('website', 'Website')
    ->placeholder('https://example.com')
    ->description('Include http:// or https://');
```

#### Telephone Input
```php
$form->tel('phone', 'Phone Number')
    ->placeholder('(555) 123-4567')
    ->autocomplete('tel');
```

#### Date/Time Inputs
```php
$form->date('birth_date', 'Birth Date')
    ->description('Select your date of birth');

$form->time('meeting_time', 'Meeting Time')
    ->default('09:00');

$form->datetime('event_datetime', 'Event Date & Time');
```

### Choice Fields

#### Select Dropdown
```php
$form->select('country', 'Country')
    ->fieldOptions([
        'us' => 'United States',
        'ca' => 'Canada',
        'uk' => 'United Kingdom',
        'au' => 'Australia'
    ])
    ->default('us')
    ->required();
```

#### Radio Buttons
```php
$form->radio('subscription', 'Subscription Plan')
    ->fieldOptions([
        'free' => 'Free Plan',
        'pro' => 'Pro Plan ($9.99/month)',
        'enterprise' => 'Enterprise Plan ($29.99/month)'
    ])
    ->default('free');
```

#### Checkbox (Single)
```php
$form->checkbox('terms_agreed', 'I agree to the terms and conditions')
    ->required()
    ->description('You must agree to continue');
```

#### Repeater Fields (Multiple Fields with Smart State Management)

The `repeater()` method creates multiple fields of the same type with smart state management. It automatically compares current state with persistent data and intelligently sets defaults.

**Basic Usage (Stateless Mode - 2 arguments):**
```php
// All fields start unchecked
$form->repeater('preferences', [
    'newsletter' => 'Subscribe to newsletter',
    'updates'    => 'Receive product updates',
    'promotions' => 'Receive promotional offers'
])->switch();
```

**State-Based Mode (3 arguments):**
```php
// Automatically checks fields that exist in persistent data
$enabled_post_types = get_option('campaignbridge_post_types', []);
$form->repeater('post_types', [
    'post'    => 'Posts',
    'page'    => 'Pages',
    'product' => 'Products'
], $enabled_post_types)->switch();
```

**With Default Checked:**
```php
// Specify a specific choice to be checked by default (stateless mode)
$form->repeater('features', [
    'feature_1' => 'Feature 1',
    'feature_2' => 'Feature 2'
])->default('feature_1')->switch();
```

**Real-World Example:**
```php
// Post types configuration screen
$all_post_types = [
    'post'    => 'Posts',
    'page'    => 'Pages',
    'product' => 'Products'
];
$enabled_types = get_option('campaignbridge_included_post_types', []);

$form = Form::make('post_types')
    ->save_to_options('campaignbridge_')
    ->repeater('included_post_types', $all_post_types, $enabled_types)->switch()
    ->submit('Save Post Types');
```

**Supported Field Types:**

The repeater supports multiple field types through chainable methods:

```php
// Switch/toggle fields (recommended for on/off choices)
// Creates multiple independent switches, one per choice
->repeater('field', $choices, $persistent)->switch()

// Checkbox fields
// Creates multiple independent checkboxes, one per choice
->repeater('field', $choices, $persistent)->checkbox()

// Radio button fields (single selection)
// Creates ONE radio group with all choices as options
->repeater('field', $choices, $persistent)->radio()

// Select dropdown fields (single selection)
// Creates ONE select dropdown with all choices as options
->repeater('field', $choices, $persistent)->select()
```

**Important:** Switch and checkbox types create **multiple independent fields** (one per choice), allowing multiple selections. Radio and select types create **one field with options**, allowing only one selection.

**Method Parameters:**
- `$field_id`: Base field name (e.g., 'post_types')
- `$populate_all_choices`: Array of all possible options `['key' => 'label']`
- `$persistent_data`: (Optional) Array of currently selected/checked keys

**State Management:**

The repeater intelligently handles state:

1. **With persistent data**: Compares persistent data with all choices and checks matching ones
2. **With default()**: Sets a specific choice as checked (only if no persistent data)
3. **Stateless**: All choices start unchecked

**Stale Data Handling:**

If persistent data contains keys not in choices (e.g., removed post types), they are silently ignored and automatically cleaned on next save.

```php
$choices = ['post' => 'Posts', 'page' => 'Pages'];
$persistent = ['post', 'product']; // 'product' was removed

$form->repeater('types', $choices, $persistent)->switch();
// Only 'post' and 'page' fields are rendered
// 'post' is checked, 'page' is unchecked
// On save, only 'post' will be persisted (stale 'product' is cleaned)
```

**Output Format:**

Form submits as an array of checked keys:
```php
// $_POST['form_id']['field_name'] => ['key1', 'key2', 'key3']
```

**Error Handling:**

The repeater includes comprehensive validation and throws `\InvalidArgumentException` for:

- Empty field ID
- Empty choices array
- Invalid choice keys (must be string/numeric)
- Invalid choice labels (must be string/numeric)
- Invalid persistent data type (PHP type hint throws `TypeError`)

```php
// ❌ Will throw exception: Empty field ID
$form->repeater('', $choices)->switch();

// ❌ Will throw exception: Empty choices
$form->repeater('field', [])->switch();

// ❌ Will throw TypeError: Invalid persistent data
$form->repeater('field', $choices, 'not_an_array')->switch();

// ❌ Will throw exception: Invalid choice label
$form->repeater('field', ['key' => ['invalid', 'array']])->switch();
```

**Migration from multiple():**

The legacy `multiple()` method is deprecated. Here's how to migrate:

```php
// OLD (deprecated)
$form->multiple('field', 'switch', $choices, $defaults)

// NEW
$form->repeater('field', $choices, $defaults)->switch()
```

**Benefits over multiple():**
- Clearer intent and more intuitive API
- Flexible field type chaining
- Smart state management with persistent data comparison
- Support for default() modifier
- Better error messages
- More consistent with builder pattern
- Easier to extend with custom field types

#### Switch/Toggle (Styled Checkbox)
```php
$form->switch('enable_feature', 'Enable Feature')
    ->default(true)
    ->description('Turn this feature on or off');
```

#### Encrypted Fields (Secure Sensitive Data)
```php
// API Key field (admin-only viewing)
$form->encrypted('api_key', 'API Key')
    ->context('api_key')  // Only admins can view decrypted values
    ->required()
    ->description('Your API key will be encrypted and stored securely');

// Sensitive user data (user can view their own)
$form->encrypted('card_number', 'Card Number')
    ->context('personal')  // Logged-in users can view their own data
    ->description('Card number will be masked for security');

// Public encrypted data
$form->encrypted('reference_code', 'Reference Code')
    ->context('public')  // No permission restrictions
    ->description('Reference code for public sharing');
```

**Security Contexts:**
- `'api_key'` - Only administrators can view decrypted values
- `'sensitive'` - Only administrators can view decrypted values
- `'personal'` - Logged-in users can view their own decrypted data
- `'public'` - No permission restrictions

**Features:**
- **Masked Display**: Shows `••••••••••••abcd` instead of raw values
- **Reveal on Demand**: Click to temporarily show full value (permission required)
- **Edit in Place**: Secure editing without exposing values in form HTML
- **Permission-Based**: Access control based on security context
- **Encrypted Storage**: Values are always encrypted in database

### Advanced Fields

#### Textarea
```php
$form->textarea('description', 'Description')
    ->rows(5)
    ->placeholder('Enter a detailed description...')
    ->max_length(1000)
    ->description('Maximum 1000 characters');
```

#### File Upload
```php
// Method 1: Set accept in field configuration
$form->file('profile_image', 'Profile Image')
    ->accept('image/jpeg,image/png,image/gif')
    ->description('Upload a JPG, PNG, or GIF image (max 2MB)');

// Method 2: Set accept directly in file() method
$form->file('document', 'Document', '.pdf,.doc,.docx')
    ->description('Upload a PDF or Word document');

// Multiple file uploads
$form->file('gallery_images', 'Gallery Images')
    ->accept('image/jpeg,image/png,image/gif')
    ->multiple_files() // Allow multiple files
    ->description('Upload multiple images for the gallery');

$form->enable_file_uploads(); // Required for file uploads
```

#### WYSIWYG Editor
```php
$form->wysiwyg('content', 'Page Content')
    ->required()
    ->description('Use the editor to format your content');
```

#### Range/Slider
```php
$form->range('volume', 'Volume Level')
    ->min(0)
    ->max(100)
    ->step(5)
    ->default(50);
```

#### Search Input
```php
$form->search('query', 'Search')
    ->placeholder('Search for content...')
    ->autocomplete('off');
```

## Form Configuration

### Layout Options

#### Table Layout (Default)
```php
$form = Form::make('settings')
    ->table() // Explicitly set table layout
    ->text('setting1', 'Setting 1')
    ->text('setting2', 'Setting 2');
// Renders as: <table><tr><th>Setting 1</th><td><input></td></tr>...</table>
```

#### Div Layout
```php
$form = Form::make('contact')
    ->div() // Use div layout
    ->text('name', 'Name')
    ->email('email', 'Email');
// Renders as: <div class="form-field"><label>Name</label><input></div>...
```

#### Custom Layout
```php
$form = Form::make('custom')
    ->render_custom(function($form) {
        echo '<div class="my-custom-form">';
        foreach ($form->get_config()->get_fields() as $field_id => $field_config) {
            echo '<div class="field-wrapper">';
            echo '<label>' . esc_html($field_config['label']) . '</label>';
            // Custom rendering logic
            echo '</div>';
        }
        echo '</div>';
    });
```

### Form Methods and Actions

```php
$form = Form::make('my_form')
    ->method('POST') // GET or POST
    ->action(admin_url('admin-post.php')) // Custom action URL
    ->enable_file_uploads(); // For file uploads
```

### Success/Error Messages

Configure success and error messages that appear after form submission. Default messages are provided if none are specified:

```php
$form = Form::make('contact')
    ->success('Thank you! Your message has been sent.')       // Custom success message
    ->error('Please correct the errors below and try again.') // Custom error message
    ->text('name')->required()
    ->email('email')->required();

// Or use defaults:
$form = Form::make('contact')
    ->text('name')->required()
    ->email('email')->required();
// Shows: "Saved successfully!" on success
// Shows: "Error occurred." on error
```

### Submit Button Configuration

```php
$form = Form::make('settings')
    ->submit('Save All Settings', 'primary') // Text and type
    ->text('option1', 'Option 1')
    ->text('option2', 'Option 2');
```

## Data Sources & Storage

### WordPress Options (Default)

```php
$form = Form::make('plugin_settings')
    ->save_to_options('my_plugin_') // Save to wp_options with prefix
    ->text('api_key', 'API Key')
    ->text('timeout', 'Timeout (seconds)')
    ->submit('Save Settings');

// Data stored as:
// my_plugin_api_key => 'value'
// my_plugin_timeout => '30'
```

### Post Meta Storage

```php
$form = Form::make('post_settings')
    ->save_to_post_meta($post_id) // Save to post meta for specific post
    ->text('custom_title', 'Custom Title')
    ->textarea('summary', 'Summary')
    ->submit('Save Post Settings');

// Data stored as post meta for post $post_id
```

### Custom Storage

For complete control over data storage (APIs, external databases, etc.), use `save_to_custom()`:

```php
$form = Form::make('api_settings')
    ->save_to_custom(function($data) {
        // Data is already sanitized and validated
        // Return boolean success/failure

        try {
            // Send to external API
            $response = wp_remote_post('https://api.example.com/settings', [
                'body' => wp_json_encode($data),
                'headers' => [
                    'Authorization' => 'Bearer ' . $data['api_token'],
                    'Content-Type' => 'application/json'
                ]
            ]);

            if (is_wp_error($response)) {
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            return $response_code >= 200 && $response_code < 300;

        } catch (Exception $e) {
            error_log('API save failed: ' . $e->getMessage());
            return false;
        }
    })
    ->text('api_token', 'API Token')
    ->text('endpoint_url', 'API Endpoint')
    ->submit('Save API Settings');

// The callback receives sanitized data:
// $data = [
//     'api_token' => 'sanitized_token',
//     'endpoint_url' => 'https://api.example.com'
// ]
```

**Custom saving features:**
- ✅ Data is **fully sanitized and validated** before callback
- ✅ Callback receives clean, safe data
- ✅ Return `true` for success, `false` for failure
- ✅ Perfect for external APIs, custom databases, cloud storage
- ✅ Full error handling control

**Alternative: Use hooks with standard storage:**

```php
$form = Form::make('enhanced_settings')
    ->save_to_options('my_plugin_') // Still save to options
    ->text('setting1')
    ->text('setting2')
    ->after_save(function($data) {
        // Additional actions after standard save
        wp_cache_flush();
        do_action('my_plugin_settings_saved', $data);
    });
```

**Note:** For custom saving logic (APIs, external services), use `->save_to_custom($callback)`. For custom rendering layouts, use `->render_custom($renderer)`.

### Loading Data

```php
// Auto-load from options
$form = Form::settings_api('my_settings'); // Automatically loads from wp_options

// Auto-load from post meta
$form = Form::make('post_form')->save_to_post_meta($post_id); // Loads post meta

// Manual data loading
$form = Form::make('custom_form')
    ->text('name')
    ->email('email');

// Later...
$form_data = get_option('my_form_data');
$form->set_data($form_data); // Manually set form data
```

## Data Encryption & Security

The forms system includes a comprehensive **AES-256-GCM encryption system** with context-aware permission levels for secure handling of sensitive data.

### Encryption Classes

**`Encryption` Class** - Core encryption functionality:
```php
use CampaignBridge\Core\Encryption;

// Context-aware encryption/decryption
$encrypted = Encryption::encrypt('sensitive_data');
$decrypted = Encryption::decrypt_for_context($encrypted, 'api_key'); // Admin-only
```

### Security Contexts

Different contexts provide different permission levels:

- **`'api_key'`** - Only administrators can view decrypted values
- **`'sensitive'`** - Only administrators can view decrypted values
- **`'personal'`** - Logged-in users can view their own decrypted data
- **`'public'`** - No permission restrictions

### Encrypted Field Types

```php
$form = Form::make('secure_settings')
    // API key - admin-only viewing
    ->encrypted('mailchimp_api_key', 'Mailchimp API Key')
        ->context('api_key')
        ->required()
        ->description('API key is encrypted and only visible to administrators')

    // User data - user can view their own
    ->encrypted('backup_email', 'Backup Email')
        ->context('personal')
        ->description('Encrypted backup email address')

    // Public data - no restrictions
    ->encrypted('public_token', 'Public Token')
        ->context('public')
        ->description('Publicly shareable encrypted token');
```

### Encryption Features

- **Military-grade encryption**: AES-256-GCM with authenticated encryption
- **Secure key management**: Automatic key rotation and secure storage
- **Context-aware permissions**: Different access levels for different data types
- **Masked UI**: Shows `••••••••••••abcd` instead of raw sensitive values
- **Reveal on demand**: Temporary display with permission checks
- **Secure editing**: Values never exposed in HTML, encrypted server-side
- **Audit logging**: All encryption/decryption operations are logged

## Validation

### Built-in Validation Rules

#### Required Fields
```php
$form->text('username', 'Username')->required();
$form->email('email', 'Email')->required();
```

#### Length Validation
```php
$form->text('username', 'Username')
    ->min_length(3)
    ->max_length(50);

$form->textarea('bio', 'Biography')
    ->max_length(500);
```

#### Numeric Validation
```php
$form->number('age', 'Age')
    ->min(13)
    ->max(120);

$form->range('percentage', 'Percentage')
    ->min(0)
    ->max(100)
    ->step(5);
```

#### Pattern Validation
```php
$form->text('zip_code', 'ZIP Code')
    ->rules(['pattern' => '/^\d{5}(-\d{4})?$/'])
    ->description('Format: 12345 or 12345-6789');
```

#### Custom Validation
```php
$form->text('username', 'Username')
    ->rules([
        'custom' => function($value) {
            if (username_exists($value)) {
                return new WP_Error('username_exists', 'Username already exists');
            }
            return true;
        }
    ]);
```

### Field-Specific Validation

#### Email Validation
```php
$form->email('contact_email', 'Contact Email')
    ->required();
// Automatically validates email format
```

#### URL Validation
```php
$form->url('website', 'Website URL')
    ->description('Must include http:// or https://');
// Automatically validates URL format
```

### Form-Level Validation

```php
$form = Form::make('registration')
    ->text('password', 'Password')->required()
    ->text('confirm_password', 'Confirm Password')->required()
    ->before_validate(function($data) {
        if ($data['password'] !== $data['confirm_password']) {
            throw new Exception('Passwords do not match');
        }
    })
    ->after_validate(function($data) {
        // Additional validation logic
        if (strlen($data['password']) < 8) {
            throw new Exception('Password must be at least 8 characters');
        }
    });
```

## Security & Validation

### Security Features

```php
$form = Form::make('secure_form')
    ->text('regular_data', 'Regular Data')
    ->encrypted('api_key', 'API Key')->context('api_key')
    ->submit('Save');

// Automatically includes:
// - Nonce verification
// - Input sanitization
// - XSS protection
// - CSRF protection
// - Context-aware encryption for sensitive fields
```

### Manual Security Controls

```php
$form = Form::make('admin_only')
    ->before_validate(function($data) {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            throw new Exception('Insufficient permissions');
        }
    })
    ->text('admin_setting', 'Admin Setting')
    ->submit('Save Admin Setting');
```

### File Upload Security

```php
$form = Form::make('file_upload')
    ->enable_file_uploads()
    ->file('document', 'Document')
        ->accept('.pdf,.doc,.docx')
        ->rules([
            'file_size' => 5 * 1024 * 1024, // 5MB max
            'mime_types' => ['application/pdf', 'application/msword']
        ])
    ->submit('Upload Document');
```

## Advanced Features

### Conditional Fields

```php
$form = Form::make('advanced_form')
    ->checkbox('show_extra', 'Show extra options')
    ->text('extra_field', 'Extra Field')
        ->rules([
            'required_if' => ['show_extra', true]
        ])
        ->attributes(['data-conditional' => 'show_extra']);
```

### Field Dependencies

```php
$form = Form::make('product_form')
    ->select('product_type', 'Product Type')
        ->fieldOptions([
            'physical' => 'Physical Product',
            'digital' => 'Digital Product'
        ])
    ->text('weight', 'Weight (kg)')
        ->attributes(['data-show-if' => 'product_type:physical'])
    ->url('download_url', 'Download URL')
        ->attributes(['data-show-if' => 'product_type:digital']);
```

### Dynamic Field Options

```php
$form = Form::make('dynamic_form')
    ->select('category', 'Category')
        ->fieldOptions(function() {
            // Load categories dynamically
            $categories = get_categories(['hide_empty' => false]);
            $options = [];
            foreach ($categories as $category) {
                $options[$category->term_id] = $category->name;
            }
            return $options;
        })
    ->select('post', 'Post')
        ->fieldOptions(function($form_data) {
            // Load posts based on selected category
            $category_id = $form_data['category'] ?? 0;
            $posts = get_posts([
                'category' => $category_id,
                'numberposts' => -1
            ]);
            $options = [];
            foreach ($posts as $post) {
                $options[$post->ID] = $post->post_title;
            }
            return $options;
        });
```

### Multi-Step Forms

```php
$form = Form::make('multi_step')
    ->text('step', 'Current Step')->default(1)
    ->before_validate(function($data) {
        $step = $data['step'] ?? 1;

        if ($step == 1) {
            // Step 1 validation
            if (empty($data['name'])) {
                throw new Exception('Name is required');
            }
        } elseif ($step == 2) {
            // Step 2 validation
            if (empty($data['email'])) {
                throw new Exception('Email is required');
            }
        }
    })
    ->before_save(function($data) {
        $step = $data['step'] ?? 1;

        if ($step < 3) {
            // Move to next step instead of saving
            $_POST['step'] = $step + 1;
            throw new Exception('Move to next step'); // Caught by handler
        }
    });
```

## Custom Fields

### Creating Custom Field Types

```php
<?php
use CampaignBridge\Admin\Core\Forms\Form_Field_Interface;

class Form_Field_Color_Picker implements Form_Field_Interface {

    private string $field_id;
    private array $config;
    private mixed $value;

    public function __construct(string $field_id, array $config, $value = null) {
        $this->field_id = $field_id;
        $this->config = $config;
        $this->value = $value ?? $config['default'] ?? '#000000';
    }

    public function render_table_row(): void {
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($this->field_id); ?>">
                    <?php echo esc_html($this->config['label']); ?>
                </label>
            </th>
            <td>
                <?php $this->render_input(); ?>
                <?php if (!empty($this->config['description'])): ?>
                    <p class="description"><?php echo esc_html($this->config['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public function render_div_field(): void {
        ?>
        <div class="form-field">
            <label for="<?php echo esc_attr($this->field_id); ?>">
                <?php echo esc_html($this->config['label']); ?>
            </label>
            <?php $this->render_input(); ?>
            <?php if (!empty($this->config['description'])): ?>
                <p class="description"><?php echo esc_html($this->config['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_input(): void {
        ?>
        <input
            type="color"
            id="<?php echo esc_attr($this->field_id); ?>"
            name="<?php echo esc_attr($this->field_id); ?>"
            value="<?php echo esc_attr($this->value); ?>"
            class="color-picker <?php echo esc_attr($this->config['class'] ?? ''); ?>"
            <?php echo $this->is_required() ? 'required' : ''; ?>
        />
        <?php
    }

    public function get_config(): array {
        return $this->config;
    }

    public function get_value() {
        return $this->value;
    }

    public function set_value($value): void {
        $this->value = $value;
    }

    public function is_required(): bool {
        return $this->config['required'] ?? false;
    }

    public function get_validation_rules(): array {
        return $this->config['validation'] ?? [];
    }

    public function validate($value): bool|\WP_Error {
        if ($this->is_required() && empty($value)) {
            return new \WP_Error(
                'field_required',
                sprintf(__('%s is required.', 'campaignbridge'), $this->config['label'])
            );
        }

        // Validate hex color format
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
            return new \WP_Error(
                'invalid_color',
                __('Please enter a valid hex color code.', 'campaignbridge')
            );
        }

        return true;
    }
}
```

### Registering Custom Fields

```php
// In your plugin's main file or initialization
use CampaignBridge\Admin\Core\Forms\Form_Field_Factory;

// Register custom field type
add_action('init', function() {
    Form_Field_Factory::register_field_type('color_picker', Form_Field_Color_Picker::class);
});

// Now you can use it in forms
$form = Form::make('theme_settings')
    ->add('primary_color', 'color_picker', 'Primary Color')
        ->default('#0073aa')
        ->description('Choose your primary theme color')
    ->submit('Save Theme');
```

### Extending Existing Fields

```php
class Extended_Text_Field extends \CampaignBridge\Admin\Core\Forms\Form_Field_Input {

    public function render_input(): void {
        // Add custom wrapper
        echo '<div class="extended-text-field">';

        // Call parent render method
        parent::render_input();

        // Add custom features
        echo '<button type="button" class="clear-field">Clear</button>';
        echo '</div>';
    }

    public function validate($value): bool|\WP_Error {
        // Custom validation logic
        $parent_validation = parent::validate($value);
        if (is_wp_error($parent_validation)) {
            return $parent_validation;
        }

        // Additional validation
        if (str_contains($value, 'spam')) {
            return new \WP_Error('contains_spam', 'Field contains prohibited content');
        }

        return true;
    }
}
```

## Hooks & Lifecycle

### Available Hook Methods

The forms system provides several lifecycle hooks:

- **`before_validate($data)`** - Called before form validation begins
- **`after_validate($data, $errors)`** - Called after validation completes (passes validation errors as second parameter)
- **`before_save($data)`** - Called before data is saved to storage
- **`after_save($data, $save_result)`** - Called after data is saved (passes save result as second parameter)
- **`on_success($data)`** - Called when form submission succeeds (data saved successfully)
- **`on_error($data)`** - Called when form submission fails (data save failed)

### Form Lifecycle Hooks

```php
$form = Form::make('advanced_form')
    ->before_validate(function($data) {
        // Pre-validation logic
        // Sanitize data, check permissions, etc.
        error_log('Form validation starting for: ' . print_r($data, true));
    })
    ->after_validate(function($data, $errors) {
        // Post-validation logic
        // Additional validation rules, logging, etc.
        if (!empty($errors)) {
            error_log('Form validation failed with errors: ' . print_r($errors, true));
        } else {
            error_log('Form validation completed successfully');
        }
    })
    ->before_save(function($data) {
        // Pre-save logic
        // Data transformation, additional validation, etc.

        // Example: Hash passwords
        if (!empty($data['password'])) {
            $data['password_hash'] = wp_hash_password($data['password']);
            unset($data['password']); // Remove plain password
        }

        return $data; // Return modified data
    })
    ->after_save(function($data, $save_result) {
        // Post-save logic
        // Send notifications, clear caches, trigger actions, etc.

        if ($save_result) {
            // Example: Send welcome email
            if (!empty($data['email'])) {
                wp_mail($data['email'], 'Welcome!', 'Thank you for registering.');
            }

            // Clear relevant caches
            wp_cache_flush();
        } else {
            error_log('Failed to save form data');
        }
    })
    ->on_success(function($data) {
        // Success handling logic - called only when form submission succeeds
        error_log('Form submitted successfully: ' . print_r($data, true));

        // Send success notification
        wp_mail($data['email'], 'Registration Successful', 'Welcome to our site!');
    })
    ->on_error(function($data) {
        // Error handling logic - called only when form submission fails
        error_log('Form submission failed: ' . print_r($data, true));

        // Send error notification to admin
        wp_mail(get_option('admin_email'), 'Form Submission Error', 'A form submission failed');
    })
    ->text('username', 'Username')->required()
    ->email('email', 'Email')->required()
    ->password('password', 'Password')->required()
    ->submit('Register');
```

### Field-Level Hooks

```php
$form = Form::make('profile_form')
    ->text('username', 'Username')
        ->rules([
            'custom' => function($value) {
                if (username_exists($value)) {
                    return new WP_Error('username_taken', 'Username already exists');
                }
                return true;
            }
        ])
    ->file('avatar', 'Profile Picture')
        ->rules([
            'custom' => function($file) {
                // Custom file validation
                if ($file['size'] > 2 * 1024 * 1024) { // 2MB
                    return new WP_Error('file_too_large', 'File size must be less than 2MB');
                }
                return true;
            }
        ])
    ->submit('Update Profile');
```

### Global Form Hooks

```php
// Add global hooks that apply to all forms
add_action('campaignbridge_form_before_validate', function($form, $data) {
    // Global pre-validation logic
    error_log('Global form validation starting');
}, 10, 2);

add_action('campaignbridge_form_after_save', function($form, $data) {
    // Global post-save logic
    error_log('Global form save completed');
}, 10, 2);

// Form-specific hooks
add_action('campaignbridge_form_before_validate_my_form', function($data) {
    // Specific to 'my_form'
    error_log('My form validation starting');
});
```

## Styling & Theming

### Default CSS Classes

```css
/* Form container */
.campaignbridge-form {
    max-width: 100%;
}

/* Table layout */
.campaignbridge-form table {
    width: 100%;
    border-collapse: collapse;
}

.campaignbridge-form th {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #ddd;
    background: #f9f9f9;
}

.campaignbridge-form td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

/* Div layout */
.campaignbridge-form .form-field {
    margin-bottom: 15px;
}

.campaignbridge-form .form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.campaignbridge-form .form-field input,
.campaignbridge-form .form-field textarea,
.campaignbridge-form .form-field select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

/* Field descriptions */
.campaignbridge-form .description {
    color: #666;
    font-size: 0.9em;
    margin-top: 3px;
}

/* Error styling */
.campaignbridge-form .field-error {
    border-color: #dc3232;
    box-shadow: 0 0 0 1px #dc3232;
}

.campaignbridge-form .field-error-message {
    color: #dc3232;
    font-size: 0.9em;
    margin-top: 3px;
}

/* Submit button */
.campaignbridge-form .submit-button {
    background: #0073aa;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.campaignbridge-form .submit-button:hover {
    background: #005177;
}
```

### Custom Styling

```php
$form = Form::make('styled_form')
    ->class('my-custom-form') // Add custom class to form
    ->text('field1', 'Field 1')
        ->class('my-custom-input') // Add custom class to field
    ->submit('Submit', 'primary');

// Custom CSS
add_action('admin_enqueue_scripts', function() {
    wp_add_inline_style('wp-admin', '
        .my-custom-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .my-custom-input {
            border: 2px solid #0073aa;
            border-radius: 6px;
        }
    ');
});
```

### Theme Integration

```php
// Check for theme support
add_action('after_setup_theme', function() {
    add_theme_support('campaignbridge-forms');
});

// Theme-specific styling
add_action('wp_enqueue_scripts', function() {
    if (get_theme_support('campaignbridge-forms')) {
        wp_enqueue_style(
            'campaignbridge-theme-integration',
            get_template_directory_uri() . '/campaignbridge-forms.css'
        );
    }
});
```

## Troubleshooting

### Common Issues

#### Form Not Saving Data

**Problem**: Form submits but data isn't saved.

**Solutions**:
```php
// Check 1: Verify nonce and permissions
if ($form->submitted()) {
    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    // Check for validation errors
    if (!$form->valid()) {
        foreach ($form->errors() as $field => $error) {
            error_log("Field '$field' error: $error");
        }
    }
}

// Check 2: Verify data source configuration
$form = Form::make('test_form')
    ->save_to_options('test_') // Ensure correct prefix
    ->text('field1', 'Field 1');

// Check 3: Add debugging
$form->after_save(function($data) {
    error_log('Form data saved: ' . print_r($data, true));
    error_log('WordPress options: ' . print_r(get_option('test_field1'), true));
});
```

#### Validation Not Working

**Problem**: Form accepts invalid data.

**Solutions**:
```php
// Check 1: Ensure field is marked as required
$form->email('email', 'Email Address')
    ->required(); // Don't forget ->required()

// Check 2: Add custom validation
$form->text('username', 'Username')
    ->rules([
        'required' => true,
        'min_length' => 3,
        'custom' => function($value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                return new WP_Error('invalid_username', 'Username contains invalid characters');
            }
            return true;
        }
    ]);

// Check 3: Debug validation
$form->after_validate(function($data) {
    error_log('Validation data: ' . print_r($data, true));
    error_log('Validation errors: ' . print_r($form->errors(), true));
});
```

#### Success/Error Messages Not Showing

**Problem**: Form submits but configured success/error messages don't appear as notices.

**Solutions**:
```php
// Check 1: Ensure success/error messages are configured
$form->success('Your changes have been saved!')
     ->error('Please correct the errors below.');

// Check 2: Notice system works automatically in all contexts

// Check 3: Verify form has save method configured
$form->save_to_options('my_prefix_'); // or ->save_to_post_meta($post_id) for post meta

// Check 4: Ensure form submission is handled
if ($form->submitted() && $form->valid()) {
    // Success message appears automatically as WordPress admin notice
} elseif ($form->submitted()) {
    // Error message appears automatically as WordPress admin notice
}
```

#### Fields Not Appearing

**Problem**: Form renders but fields are missing.

**Solutions**:
```php
// Check 1: Ensure proper chaining
$form = Form::make('test_form')
    ->text('field1', 'Field 1') // Don't forget field label
    ->email('field2', 'Field 2')
    ->submit('Submit');

// Check 2: Verify field configuration
$form->before_save(function($data) {
    error_log('Form configuration: ' . print_r($form->get_config()->get_fields(), true));
});

// Check 3: Check for PHP errors
$form->on_error(function($error) {
    error_log('Form error: ' . $error);
});
```

### Debug Mode

```php
// Enable debug mode for troubleshooting
define('CAMPAIGNBRIDGE_DEBUG', true);

// Add debug information to form
$form = Form::make('debug_form')
    ->before_save(function($data) {
        if (defined('CAMPAIGNBRIDGE_DEBUG') && CAMPAIGNBRIDGE_DEBUG) {
            error_log('Form data before save: ' . print_r($data, true));
            error_log('Current user: ' . get_current_user_id());
            error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
        }
    })
    ->after_save(function($data) {
        if (defined('CAMPAIGNBRIDGE_DEBUG') && CAMPAIGNBRIDGE_DEBUG) {
            error_log('Form data after save: ' . print_r($data, true));
            error_log('Saved to database: ' . print_r(get_option('debug_form_data'), true));
        }
    });
```

## Best Practices

### Code Organization

```php
// Organize form classes by feature
class UserManagementForms {

    public static function create_user_form(): Form {
        return Form::make('create_user')
            ->text('username', 'Username')->required()
            ->email('email', 'Email')->required()
            ->password('password', 'Password')->required()
            ->select('role', 'Role')->fieldOptions([
                'subscriber' => 'Subscriber',
                'editor' => 'Editor',
                'administrator' => 'Administrator'
            ])
            ->before_validate([self::class, 'validate_user_data'])
            ->before_save([self::class, 'prepare_user_data'])
            ->after_save([self::class, 'send_welcome_email'])
            ->submit('Create User');
    }

    public static function validate_user_data($data) {
        if (username_exists($data['username'])) {
            throw new Exception('Username already exists');
        }
    }

    public static function prepare_user_data($data) {
        $data['user_pass'] = wp_hash_password($data['password']);
        unset($data['password']);
        return $data;
    }

    public static function send_welcome_email($data) {
        wp_mail($data['email'], 'Welcome!', 'Your account has been created.');
    }
}
```

### Security Best Practices

```php
class SecureFormBuilder {

    public static function create_secure_form(string $form_id): Form {
        return Form::make($form_id)
            ->before_validate([self::class, 'check_permissions'])
            ->before_save([self::class, 'sanitize_data'])
            ->before_save([self::class, 'validate_business_rules'])
            ->after_save([self::class, 'log_activity'])
            ->on_error([self::class, 'handle_errors']);
    }

    public static function check_permissions($data) {
        if (!current_user_can('manage_options')) {
            throw new Exception('Access denied');
        }
    }

    public static function sanitize_data($data) {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = sanitize_text_field($value);
            }
        }
        return $data;
    }

    public static function validate_business_rules($data) {
        // Business logic validation
        if (isset($data['email']) && email_exists($data['email'])) {
            throw new Exception('Email already registered');
        }
    }

    public static function log_activity($data) {
        error_log(sprintf(
            'Form %s submitted by user %d',
            $data['form_id'] ?? 'unknown',
            get_current_user_id()
        ));
    }

    public static function handle_errors($errors) {
        error_log('Form errors: ' . print_r($errors, true));
        // Send admin notification if needed
    }
}
```

### Performance Optimization

```php
class OptimizedFormBuilder {

    public static function create_cached_form(string $form_id): Form {
        // Cache form configuration
        $cache_key = "form_config_{$form_id}";
        $form_config = wp_cache_get($cache_key);

        if (false === $form_config) {
            $form_config = self::build_form_config($form_id);
            wp_cache_set($cache_key, $form_config, '', HOUR_IN_SECONDS);
        }

        return Form::make($form_id, $form_config);
    }

    public static function create_lazy_loaded_form(string $form_id): Form {
        return Form::make($form_id)
            ->on('before_render', function($form) {
                // Lazy load field options
                if (!$form->get_config()->has('field_options_loaded')) {
                    self::load_dynamic_options($form);
                    $form->get_config()->set('field_options_loaded', true);
                }
            });
    }

    private static function load_dynamic_options($form) {
        // Load options from database/cache
        $categories = get_categories(['hide_empty' => false]);
        $options = [];
        foreach ($categories as $category) {
            $options[$category->term_id] = $category->name;
        }

        // Update form field options
        $form->get_config()->update_field('category', ['options' => $options]);
    }
}
```

## Architecture Overview

### Trait-Based Core Components

```
Form (Facade with Traits)
├── Form_Field_Methods (Field Creation: text, password, select, etc.)
├── Form_Config_Methods (Field Config: required, default, options, etc.)
├── Form_Hook_Methods (Event Hooks: before_save, after_save, etc.)
├── Form_Layout_Methods (Layout Control: description, auto_layout, etc.)
├── Form_Builder (Fluent API Implementation)
│   ├── Form_Field_Builder (Field Configuration - Uses All Traits)
│   │   ├── Form_Field_Methods ✅
│   │   ├── Form_Config_Methods ✅
│   │   ├── Form_Hook_Methods ✅
│   │   └── Form_Layout_Methods ✅
│   └── Form_Field_Manager (Field Management)
├── Form_Config (Configuration Management)
├── Form_Container (Dependency Injection)
│   ├── Form_Security (Security Layer with Automatic Validation)
│   ├── Form_Validator (Validation Engine)
│   ├── Form_Handler (Submission Logic)
│   ├── Form_Renderer (Output Generation)
│   ├── Form_Data_Manager (Data Persistence)
│   └── Encryption (AES-256-GCM with Context-Aware Permissions)
└── Form_Field_* (Field Implementations including Encrypted Fields)
```

### Data Flow with Security Integration

1. **Initialization**: Form created with trait-based configuration
2. **Security Setup**: Form_Security automatically configured with nonces and validation
3. **Data Loading**: Form_Data_Manager loads existing data (decrypts encrypted fields based on context permissions)
4. **Rendering**: Form_Renderer generates HTML output (masks encrypted fields, shows permission-restricted access)
5. **Submission**: Form_Handler processes submitted data with automatic security validation
6. **Security Check**: Form_Security verifies CSRF, permissions, and request integrity
7. **Input Sanitization**: Form_Handler sanitizes all input using field-specific sanitizers
8. **Validation**: Form_Validator checks data integrity with custom and built-in rules
9. **Encryption**: Sensitive data encrypted using AES-256-GCM with context-aware permissions
10. **Persistence**: Form_Data_Manager saves validated data (encrypted fields stored securely)
11. **Response**: Success/error messages displayed via automatic notice system

### Trait-Based Extension Points

- **Field Types**: Add methods to `Form_Field_Methods` trait (automatic availability everywhere)
- **Configuration**: Extend `Form_Config_Methods` trait for field settings
- **Event Hooks**: Add lifecycle methods to `Form_Hook_Methods` trait
- **Layout Control**: Extend `Form_Layout_Methods` trait for UI customization
- **Security Validation**: Extend `SecurityValidationSniff` for custom security rules
- **Custom Fields**: Implement `Form_Field_Interface` (includes encrypted fields)
- **Validation Rules**: Extend `Form_Validator` with custom validation logic
- **Data Sources**: Implement custom data managers extending `Form_Data_Manager`
- **Renderers**: Create custom layout engines extending `Form_Renderer`
- **Encryption**: Extend `Encryption` class with custom contexts and permissions

### Extending the Trait-Based API

The trait-based architecture makes extending the form API incredibly simple:

#### Adding a New Field Type

```php
// 1. Add method to Form_Field_Methods.php
public function color( string $name, string $label = '' ): Form_Field_Builder {
    return $this->builder->color( $name, $label );
}

// 2. Implement in Form_Builder.php
public function color( string $name, string $label = '' ): Form_Field_Builder {
    return $this->add_field( $name, 'color', $label );
}

// 3. Add to Form_Renderer.php if custom rendering needed
case 'color':
    $html .= '<input type="color" name="' . esc_attr( $field_data['name'] ) .
             '" value="' . esc_attr( $field_data['value'] ) . '">';
    break;

// 4. Usage - automatically available everywhere!
$form->color('theme_color', 'Theme Color')->required();
$form->select('type')->color('accent_color'); // ✅ Works in fluent chains
```

#### Adding Configuration Methods

```php
// Add to Form_Config_Methods.php
public function max_length( int $length ): self {
    // Configure max length for text fields
    return $this;
}

// Automatically available on all form contexts
$form->text('username')->max_length(50)->required(); // ✅ Works
$form->textarea('bio')->max_length(500); // ✅ Works
```

#### Adding Hook Methods

```php
// Add to Form_Hook_Methods.php
public function on_validation_error( callable $callback ): self {
    $this->builder->on_validation_error( $callback );
    return $this;
}

// Available everywhere in fluent chains
$form->text('email')->required()
     ->on_validation_error(function($errors) {
         // Custom error handling
     });
```

**Key Benefits:**
- **Zero Configuration**: New methods automatically available everywhere
- **Type Safe**: PHPStan understands the complete API
- **Consistent**: Same patterns work across all contexts
- **Maintainable**: Single source of truth for each method type

### Dependencies

- **WordPress Core**: Options API, Post Meta API, Nonces, Users API
- **PHP Standards**: PSR-4 autoloading, modern PHP features
- **Security**: Built-in WordPress security functions
- **Validation**: WordPress sanitization and validation functions

This forms system provides a robust, extensible foundation for building WordPress admin interfaces with a focus on developer experience, security, and maintainability.

### Recent Changes (v3.0+ - Trait-Based Architecture)

- **Trait-Based Architecture**: Complete refactor using composition over inheritance
  - `Form_Field_Methods`: Field creation methods (`text()`, `password()`, `select()`, etc.)
  - `Form_Config_Methods`: Field configuration (`required()`, `default()`, `options()`, etc.)
  - `Form_Hook_Methods`: Event callbacks (`before_save()`, `after_save()`, etc.)
  - `Form_Layout_Methods`: Layout control (`description()`, `auto_layout()`, etc.)

- **Automatic Security Validation**: Enhanced `SecurityValidationSniff`
  - Auto-detects storage layer classes and methods
  - Excludes legitimate storage operations from nonce validation
  - Prevents false positives in PHPCS/PHPStan

- **Unified Fluent API**: Consistent methods across all chaining contexts
  - `Form_Field_Builder` now uses all traits for seamless chaining
  - No more "undefined method" errors in fluent chains
  - `__call` proxy mechanism with trait-based delegation

- **Enhanced PHPDoc**: Proper `@return static` typing for trait inheritance
  - Fixes PHPStan type incompatibility errors
  - Ensures proper type checking across trait usage
  - Maintains backward compatibility

- **Security Architecture**: Comprehensive defense in depth
  - Form_Security: CSRF, capabilities, request validation
  - Input sanitization per field type
  - AES-256-GCM encryption with context-aware permissions
  - Automatic output escaping in Form_Renderer

- **Storage Layer**: Wrapper-based data persistence
  - Automatic key prefixing for namespace isolation
  - WordPress API compliance (prepared statements)
  - Centralized data access patterns

- **Deprecated Code Removal**: Legacy methods and parameters removed for cleaner API
