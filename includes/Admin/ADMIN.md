# CampaignBridge Admin System Documentation

A comprehensive, developer-friendly admin system for WordPress that provides screens, tabs, controllers, and advanced features for building modern WordPress admin interfaces.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Architecture Overview](#architecture-overview)
3. [Screen System](#screen-system)
4. [Tab System](#tab-system)
5. [Controller System](#controller-system)
6. [Security Features](#security-features)
7. [Asset Management](#asset-management)
8. [Advanced Features](#advanced-features)
9. [Extending the System](#extending-the-system)
10. [Best Practices](#best-practices)
11. [Troubleshooting](#troubleshooting)
12. [API Reference](#api-reference)

## Quick Start

### Creating Your First Admin Screen

```php
<?php
/**
 * My Custom Admin Screen
 *
 * @package CampaignBridge\Admin\Screens
 */

// Filename: includes/Admin/Screens/my-screen.php

// Screen automatically discovered and registered by naming convention
// URL: wp-admin/admin.php?page=campaignbridge-my-screen
// Title: "My Screen" (auto-generated from filename)

// Optional: Add controller class includes/Admin/Controllers/My_Screen_Controller.php
```

### Creating Your First Tabbed Screen

```php
<?php
/**
 * Settings with Tabs
 *
 * @package CampaignBridge\Admin\Screens\settings
 */

// Folder: includes/Admin/Screens/settings/
// Main file: includes/Admin/Screens/settings/settings.php
// Controller: includes/Admin/Controllers/Settings_Controller.php

// Tabs auto-discovered from folder contents:
// - includes/Admin/Screens/settings/general.php
// - includes/Admin/Screens/settings/advanced.php
// - includes/Admin/Screens/settings/security.php
```

### Using Controllers

```php
<?php
// In includes/Admin/Controllers/My_Controller.php
namespace CampaignBridge\Admin\Controllers;

class My_Controller {
    private array $data = [];

    public function __construct() {
        // Load data needed by the screen
        $this->data['stats'] = $this->get_statistics();
        $this->data['settings'] = get_option('my_settings', []);
    }

    public function get_data(): array {
        return $this->data;
    }

    public function handle_request(): void {
        // Handle form submissions and actions
        if (isset($_POST['save_settings'])) {
            $this->save_settings();
        }
    }

    private function save_settings(): void {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'save_settings')) {
            wp_die('Security check failed');
        }

        update_option('my_settings', $_POST['settings']);
        wp_redirect(add_query_arg('updated', '1', $_SERVER['REQUEST_URI']));
        exit;
    }
}
```

## Architecture Overview

### Core Components

```
Admin System Architecture
├── Admin (Bootstrap)
│   ├── Screen_Registry (Auto-discovery & Registration)
│   ├── Screen_Context (Request/Response Context)
│   └── Controllers (Business Logic)
└── Security Layer (Built-in Protection)
    ├── Security Features (CSRF, Sanitization)
    ├── Notice Handler (User Feedback)
    └── Rate Limiting
```

### File Structure

```
includes/Admin/
├── Admin.php                     # Main bootstrap
├── Controllers/                  # Business logic controllers
│   ├── Post_Types_Controller.php
│   ├── Settings_Controller.php
│   └── Status_Controller.php
├── Core/                         # Core system classes
│   ├── Screen_Registry.php       # Screen discovery & registration
│   ├── Screen_Context.php        # Request/response context
│   └── Forms/                    # Form system (separate documentation)
├── Screens/                      # Admin screens (auto-discovered)
│   ├── simple-screen.php         # Simple screen
│   ├── settings/                 # Tabbed screen folder
│   │   ├── settings.php          # Main settings screen
│   │   ├── general.php           # General tab
│   │   ├── advanced.php          # Advanced tab
│   │   └── _config.php           # Tab configuration
│   └── status.php                # Status screen
└── ADMIN.md                      # This documentation
```

## Screen System

### Auto-Discovery

The admin system automatically discovers and registers screens based on file/folder naming conventions:

#### Simple Screens
- **File**: `includes/Admin/Screens/my-screen.php`
- **URL**: `wp-admin/admin.php?page=campaignbridge-my-screen`
- **Title**: "My Screen" (auto-generated from filename)
- **Menu**: Added to CampaignBridge menu

#### Tabbed Screens
- **Folder**: `includes/Admin/Screens/settings/`
- **Main File**: `includes/Admin/Screens/settings/settings.php`
- **Tabs**: Auto-discovered from PHP files in folder
- **URL**: `wp-admin/admin.php?page=campaignbridge-settings&tab=general`

### Screen Registration

Screens are automatically registered during admin initialization. No manual registration required.

```php
// Automatic registration happens in Admin::init()
// - Discovers all screens in includes/Admin/Screens/
// - Registers menu pages and hooks
// - Sets up controllers and contexts
```

### Screen Context

Each screen gets a `Screen_Context` object that provides:

```php
class Screen_Context {
    // Data storage and retrieval
    public function set(string $key, $value): void;
    public function get(string $key, $fallback = null);

    // Request handling
    public function is_post(): bool;
    public function post(string $key, $fallback = null);
    public function verify_nonce(string $action): bool;

    // Asset management
    public function enqueue_style(string $handle, string $src, array $deps = [], ?string $version = null): void;
    public function enqueue_script(string $handle, string $src, array $deps = [], ?string $version = null, bool $in_footer = true): void;

    // Tab utilities
    public function is_tab(string $tab_name): bool;
    public function get_tab_url(string $tab_name): string;
}
```

## Tab System

### Creating Tabbed Screens

1. **Create a folder** in `includes/Admin/Screens/` (e.g., `settings/`)
2. **Create the main screen file** (e.g., `settings/settings.php`)
3. **Add tab files** in the folder (e.g., `general.php`, `advanced.php`)

### Tab File Structure

```php
<?php
/**
 * General Settings Tab
 *
 * @package CampaignBridge\Admin\Screens\settings
 */

// Tab content goes here
// Variables available:
// - $screen: Screen_Context instance
// - $controller: Controller instance (if exists)
// - $current_tab: Current tab name
// - $tabs: Array of available tabs

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if ($controller) {
        $data = $controller->get_data();
        // Use controller data
    } ?>

    <form method="post" action="">
        <?php $screen->nonce_field('save_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="setting_name">Setting Name</label>
                </th>
                <td>
                    <input type="text" id="setting_name" name="setting_name"
                           value="<?php echo esc_attr($data['setting_name'] ?? ''); ?>" />
                </td>
            </tr>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>
</div>
```

### Tab Configuration

Create `_config.php` in the tab folder for advanced configuration:

```php
<?php
/**
 * Tab Configuration
 *
 * @package CampaignBridge\Admin\Screens\settings
 */

return [
    'default_tab' => 'general',
    'tab_order' => ['general', 'advanced', 'security', 'integrations'],
    'tab_labels' => [
        'general' => 'General',
        'advanced' => 'Advanced Settings',
        'security' => 'Security',
        'integrations' => 'Integrations'
    ],
    'capabilities' => [
        'general' => 'manage_options',
        'advanced' => 'manage_options',
        'security' => 'manage_options',
        'integrations' => 'manage_options'
    ]
];
```

### Tab Navigation

Automatic tab navigation is provided:

```php
<!-- Automatic tab navigation -->
<?php if (!empty($tabs)): ?>
<div class="nav-tab-wrapper">
    <?php foreach ($tabs as $tab_key => $tab_config): ?>
        <?php
        $active_class = ($current_tab === $tab_key) ? 'nav-tab-active' : '';
        $tab_url = $screen->get_tab_url($tab_key);
        $tab_label = $tab_config['label'] ?? ucfirst(str_replace('_', ' ', $tab_key));
        ?>
        <a href="<?php echo esc_url($tab_url); ?>"
           class="nav-tab <?php echo esc_attr($active_class); ?>">
            <?php echo esc_html($tab_label); ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
```

## Controller System

### Auto-Discovery

Controllers are automatically discovered based on naming conventions:

- `post-types.php` screen → `Post_Types_Controller` class
- `settings/` folder → `Settings_Controller` class
- `status.php` screen → `Status_Controller` class

### Controller Structure

```php
<?php
namespace CampaignBridge\Admin\Controllers;

class My_Controller {

    /**
     * Controller data array
     * @var array
     */
    private array $data = [];

    /**
     * Constructor - Initialize controller data
     */
    public function __construct() {
        // Load data needed by the screen
        $this->load_data();
    }

    /**
     * Get data for views (available via $screen->get())
     *
     * @return array
     */
    public function get_data(): array {
        return $this->data;
    }

    /**
     * Handle requests (called before any tab renders)
     * Perfect place for form processing that affects multiple tabs
     *
     * @return void
     */
    public function handle_request(): void {
        // Global actions that affect all tabs
        if (isset($_POST['global_action'])) {
            $this->handle_global_action();
        }
    }

    /**
     * Load controller data
     */
    private function load_data(): void {
        $this->data = [
            'version' => '1.0.0',
            'settings' => get_option('my_settings', []),
            'stats' => $this->get_stats()
        ];
    }

    /**
     * Handle global actions
     */
    private function handle_global_action(): void {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'global_action')) {
            wp_die('Security check failed');
        }

        // Process global action
        update_option('global_setting', sanitize_text_field($_POST['global_setting']));

        // Redirect with success message
        wp_redirect(add_query_arg('updated', '1', $_SERVER['REQUEST_URI']));
        exit;
    }
}
```

### Controller Data Access

In screen/tab files, controller data is available through the `$controller` variable:

```php
<?php
// In any tab file
if ($controller) {
    $data = $controller->get_data();

    // Access controller data
    $version = $data['version'];
    $settings = $data['settings'];
    $stats = $data['stats'];
}
?>
```


## Security Features

### Automatic Security

All forms include automatic security features:

- **CSRF Protection**: Automatic nonce verification
- **Input Sanitization**: All inputs automatically sanitized
- **XSS Prevention**: Output escaping and validation
- **Rate Limiting**: Protection against abuse
- **File Upload Security**: Safe file handling with validation

### Manual Security Controls

```php
// In your controller's handle_request method
public function handle_request(): void {
    if (isset($_POST['upload_file'])) {
        $this->handle_secure_file_upload();
    }
}

private function handle_secure_file_upload(): void {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    // Rate limiting
    $rate_limit_key = 'file_upload_' . get_current_user_id();
    $last_upload = get_transient($rate_limit_key);

    if ($last_upload && (time() - $last_upload) < 300) { // 5 minutes
        wp_die('Too many uploads. Please wait 5 minutes.');
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'file_upload')) {
        wp_die('Security check failed');
    }

    // Handle file upload with security checks
    if (!empty($_FILES['document'])) {
        // Additional file validation here
        // Process upload...
    }

    // Set rate limiting
    set_transient($rate_limit_key, time(), 300);
}
```

### Security Best Practices

1. **Always verify nonces** for form submissions
2. **Sanitize all input** data
3. **Validate user capabilities** before sensitive operations
4. **Use prepared statements** for database queries
5. **Escape all output** to prevent XSS
6. **Validate file uploads** thoroughly
7. **Implement rate limiting** for public forms
8. **Log security events** for monitoring

## Asset Management

### Automatic Asset Loading

The admin system automatically loads assets based on screen context:

```php
// In Screen_Context - automatic asset management
public function enqueue_style(string $handle, string $src, array $deps = [], ?string $version = null): void;
public function enqueue_script(string $handle, string $src, array $deps = [], ?string $version = null, bool $in_footer = true): void;
```

### Asset Auto-Discovery

Assets are automatically discovered and loaded:

- `assets/css/screen-name.css` → Auto-enqueued for screen
- `assets/js/screen-name.js` → Auto-enqueued for screen
- `assets/css/tabs/tab-name.css` → Auto-enqueued for tab
- `assets/js/tabs/tab-name.js` → Auto-enqueued for tab

### Global Assets

Global admin assets are loaded for all CampaignBridge screens:

```php
// In Admin.php
wp_enqueue_style('cb-admin-global', plugin_url() . 'dist/styles/admin.css');
wp_enqueue_script('cb-admin-global', plugin_url() . 'dist/scripts/admin.js');
```

## Advanced Features

### Custom Screen Components

Create custom screen components by extending the screen system:

```php
<?php
class Custom_Dashboard_Screen {
    private array $widgets = [];

    public function __construct() {
        $this->register_widgets();
    }

    public function render(): void {
        echo '<div class="dashboard-wrapper">';

        foreach ($this->widgets as $widget) {
            $this->render_widget($widget);
        }

        echo '</div>';
    }

    private function register_widgets(): void {
        $this->widgets = [
            [
                'title' => 'System Status',
                'callback' => [$this, 'render_system_status'],
                'priority' => 10
            ],
            [
                'title' => 'Recent Activity',
                'callback' => [$this, 'render_recent_activity'],
                'priority' => 20
            ],
            [
                'title' => 'Quick Stats',
                'callback' => [$this, 'render_quick_stats'],
                'priority' => 30
            ]
        ];

        usort($this->widgets, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    private function render_widget(array $widget): void {
        echo '<div class="dashboard-widget">';
        echo '<h3>' . esc_html($widget['title']) . '</h3>';
        echo '<div class="widget-content">';

        if (is_callable($widget['callback'])) {
            call_user_func($widget['callback']);
        }

        echo '</div>';
        echo '</div>';
    }

    private function render_system_status(): void {
        $status = $this->get_system_status();
        echo '<ul class="status-list">';

        foreach ($status as $item => $value) {
            $class = $value['status'] === 'good' ? 'status-good' : 'status-warning';
            echo '<li class="' . esc_attr($class) . '">';
            echo esc_html($item) . ': ' . esc_html($value['message']);
            echo '</li>';
        }

        echo '</ul>';
    }

    private function render_recent_activity(): void {
        $activities = $this->get_recent_activities();

        if (empty($activities)) {
            echo '<p>No recent activity.</p>';
            return;
        }

        echo '<ul class="activity-list">';
        foreach ($activities as $activity) {
            echo '<li>';
            echo '<span class="activity-time">' . esc_html($activity['time']) . '</span> ';
            echo '<span class="activity-desc">' . esc_html($activity['description']) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    private function render_quick_stats(): void {
        $stats = $this->get_quick_stats();

        echo '<div class="stats-grid">';
        foreach ($stats as $stat) {
            echo '<div class="stat-item">';
            echo '<div class="stat-number">' . esc_html($stat['value']) . '</div>';
            echo '<div class="stat-label">' . esc_html($stat['label']) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
}
```

### Dynamic Data Loading

Load data dynamically in controllers:

```php
<?php
class Analytics_Controller {
    private array $data = [];

    public function __construct() {
        $this->load_analytics_data();
    }

    public function get_data(): array {
        return $this->data;
    }

    private function load_analytics_data(): void {
        // Load categories dynamically
        $categories = get_categories(['hide_empty' => false]);
        $this->data['categories'] = array_column($categories, 'name', 'term_id');

        // Load posts based on selected category
        $category_id = $_GET['category'] ?? 0;
        if ($category_id) {
            $posts = get_posts([
                'category' => $category_id,
                'numberposts' => -1
            ]);
            $this->data['posts'] = array_column($posts, 'post_title', 'ID');
        }

        // Load analytics data
        $this->data['stats'] = $this->get_analytics_stats();
    }
}
```

### Conditional Logic in Screens

Show/hide content based on conditions:

```php
<?php
// In your screen template
$product_type = $_POST['product_type'] ?? 'physical';

echo '<select name="product_type">';
echo '<option value="physical"' . selected($product_type, 'physical', false) . '>Physical</option>';
echo '<option value="digital"' . selected($product_type, 'digital', false) . '>Digital</option>';
echo '</select>';

if ($product_type === 'physical') {
    echo '<input type="text" name="weight" placeholder="Weight (kg)">';
}

if ($product_type === 'digital') {
    echo '<input type="url" name="download_url" placeholder="Download URL">';
}
```

### Multi-Step Processes

Create wizard-style processes in controllers:

```php
<?php
class Setup_Controller {
    public function handle_request(): void {
        $step = intval($_POST['step'] ?? 1);

        switch ($step) {
            case 1:
                $this->handle_step_1();
                break;
            case 2:
                $this->handle_step_2();
                break;
            case 3:
                $this->handle_step_3();
                break;
        }
    }

    private function handle_step_1(): void {
        // Validate step 1 data
        if (empty($_POST['site_name']) || empty($_POST['admin_email'])) {
            wp_die('Please fill in all required fields');
        }

        // Move to step 2
        $_POST['step'] = 2;
        $this->redirect_to_step(2);
    }

    private function handle_step_2(): void {
        // Process step 2 and move to step 3
        $_POST['step'] = 3;
        $this->redirect_to_step(3);
    }

    private function handle_step_3(): void {
        // Final step - save all data
        $this->save_setup_data();
        wp_redirect(admin_url('admin.php?page=campaignbridge-dashboard&setup_complete=1'));
        exit;
    }

    private function redirect_to_step(int $step): void {
        wp_redirect(add_query_arg(['step' => $step], $_SERVER['REQUEST_URI']));
        exit;
    }
}
```

## Extending the System

### Custom Controllers

Create custom controllers for complex business logic:

```php
<?php
namespace CampaignBridge\Admin\Controllers;

class Analytics_Controller {

    private array $data = [];

    public function __construct() {
        $this->load_analytics_data();
        $this->load_chart_config();
    }

    public function get_data(): array {
        return $this->data;
    }

    public function handle_request(): void {
        if (isset($_POST['refresh_analytics'])) {
            $this->refresh_analytics();
        }

        if (isset($_POST['export_data'])) {
            $this->export_analytics_data();
        }
    }

    private function load_analytics_data(): void {
        $this->data['visitors_today'] = $this->get_visitor_count('today');
        $this->data['pageviews_today'] = $this->get_pageview_count('today');
        $this->data['top_pages'] = $this->get_top_pages(10);
        $this->data['traffic_sources'] = $this->get_traffic_sources();
    }

    private function load_chart_config(): void {
        $this->data['chart_config'] = [
            'type' => 'line',
            'data' => $this->get_chart_data(),
            'options' => [
                'responsive' => true,
                'scales' => [
                    'y' => ['beginAtZero' => true]
                ]
            ]
        ];
    }

    private function refresh_analytics(): void {
        // Clear cached data
        wp_cache_flush();

        // Reload fresh data
        $this->load_analytics_data();

        // Redirect with success message
        wp_redirect(add_query_arg('refreshed', '1', $_SERVER['REQUEST_URI']));
        exit;
    }

    private function export_analytics_data(): void {
        // Generate CSV export
        $csv_data = $this->generate_csv_export();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="analytics-export-' . date('Y-m-d') . '.csv"');
        echo $csv_data;
        exit;
    }
}
```

### Custom Screen Types

Extend the screen registry for custom screen types:

```php
<?php
use CampaignBridge\Admin\Core\Screen_Registry;

class Custom_Screen_Registry extends Screen_Registry {

    protected function register_custom_screen(string $filename): void {
        // Custom screen registration logic
        $screen_name = pathinfo($filename, PATHINFO_FILENAME);
        $config = $this->get_screen_config($filename);

        if ($config['type'] === 'dashboard') {
            $this->register_dashboard_screen($screen_name, $config);
        } elseif ($config['type'] === 'wizard') {
            $this->register_wizard_screen($screen_name, $config);
        }
    }

    private function register_dashboard_screen(string $screen_name, array $config): void {
        // Dashboard-specific registration
        add_menu_page(
            $config['title'],
            $config['menu_title'],
            $config['capability'],
            $config['slug'],
            [$this, 'render_dashboard_screen'],
            $config['icon'],
            $config['position']
        );
    }

    public function render_dashboard_screen(): void {
        // Custom dashboard rendering
        include $this->get_screen_file_path($screen_name);

        // Add dashboard widgets
        $this->render_dashboard_widgets();
    }
}
```

### Custom Form Validators

Create custom validation rules:

```php
<?php
class Custom_Form_Validator extends \CampaignBridge\Admin\Core\Forms\Form_Validator {

    public function validate_domain($value, array $field_config): bool|\WP_Error {
        if (empty($value)) {
            return true; // Not required
        }

        // Check if domain is valid format
        if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $value)) {
            return new \WP_Error('invalid_domain', 'Please enter a valid domain name');
        }

        // Check if domain is accessible
        if ($this->is_domain_accessible($value)) {
            return new \WP_Error('domain_inaccessible', 'Domain is not accessible');
        }

        return true;
    }

    public function validate_unique_username($value, array $field_config): bool|\WP_Error {
        if (username_exists($value)) {
            return new \WP_Error('username_exists', 'Username already exists');
        }

        return true;
    }

    public function validate_strong_password($value, array $field_config): bool|\WP_Error {
        $errors = [];

        if (strlen($value) < 8) {
            $errors[] = 'at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = 'one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = 'one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = 'one number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $errors[] = 'one special character';
        }

        if (!empty($errors)) {
            return new \WP_Error(
                'weak_password',
                'Password must contain: ' . implode(', ', $errors)
            );
        }

        return true;
    }

    private function is_domain_accessible(string $domain): bool {
        $response = wp_remote_head('http://' . $domain, ['timeout' => 5]);
        return is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400;
    }
}

// Register custom validators
add_filter('campaignbridge_form_validation_rules', function($rules) {
    $rules['domain'] = 'validate_domain';
    $rules['unique_username'] = 'validate_unique_username';
    $rules['strong_password'] = 'validate_strong_password';
    return $rules;
});
```

## Best Practices

### Code Organization

1. **Separate Concerns**: Keep controllers focused on business logic, screens on presentation
2. **Naming Conventions**: Use consistent naming for screens, controllers, and tabs
3. **File Structure**: Follow the established folder structure for maintainability
4. **Documentation**: Document all custom functionality and hooks

### Security

1. **Always verify nonces** for form submissions
2. **Sanitize all input** data before processing
3. **Validate user capabilities** before sensitive operations
4. **Use prepared statements** for all database queries
5. **Escape all output** to prevent XSS attacks
6. **Validate file uploads** and check file types/sizes
7. **Implement rate limiting** for public-facing forms
8. **Log security events** for monitoring and debugging

### Performance

1. **Cache expensive operations** using WordPress object cache
2. **Use lazy loading** for heavy components
3. **Optimize database queries** with proper indexing
4. **Minify and combine assets** for production
5. **Implement pagination** for large data sets
6. **Use transients** for temporary data storage

### User Experience

1. **Provide clear feedback** for all user actions
2. **Use consistent styling** across all screens
3. **Implement proper loading states** for async operations
4. **Add helpful descriptions** and tooltips
5. **Provide keyboard navigation** support
6. **Ensure mobile responsiveness**

## Troubleshooting

### Common Issues

#### Screen Not Appearing

**Problem**: Admin screen doesn't show in menu

**Solutions**:
1. Check file permissions on screen files
2. Verify naming conventions (lowercase, hyphens)
3. Check for PHP syntax errors in screen files
4. Ensure parent menu exists (`campaignbridge`)

#### Controller Not Loading

**Problem**: Controller data not available in screen

**Solutions**:
1. Verify controller class exists and follows naming convention
2. Check namespace is correct (`CampaignBridge\Admin\Controllers`)
3. Ensure constructor doesn't throw exceptions
4. Check PHP error logs for initialization errors

#### Form Not Saving

**Problem**: Form submits but data isn't saved

**Solutions**:
1. Check nonce verification in form handler
2. Verify user has required capabilities
3. Check for validation errors
4. Ensure save method is properly configured (options, post_meta, custom)
5. Check database connection and permissions

#### Assets Not Loading

**Problem**: CSS/JS files not enqueued

**Solutions**:
1. Verify file paths are correct
2. Check file permissions
3. Ensure assets are enqueued before `admin_enqueue_scripts` hook
4. Use browser dev tools to check network errors

#### Tab Navigation Not Working

**Problem**: Tab switching doesn't work

**Solutions**:
1. Check tab file exists and is readable
2. Verify tab configuration in `_config.php`
3. Check for PHP errors in tab files
4. Ensure tab URLs are properly generated

### Debug Mode

Enable debug mode for troubleshooting:

```php
// Add to wp-config.php or theme functions.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Add to plugin for admin system debugging
define('CAMPAIGNBRIDGE_DEBUG', true);

// Check debug logs
tail -f wp-content/debug.log
```

### Error Logging

The system provides comprehensive error logging:

```php
// In controllers or screens
error_log('Debug message: ' . print_r($data, true));

// Controller errors are logged manually
class My_Controller {
    public function handle_request(): void {
        try {
            $this->process_request();
        } catch (Exception $e) {
            error_log('Controller error: ' . $e->getMessage());
            wp_die('An error occurred while processing your request.');
        }
    }
}
```

## API Reference

### Screen_Registry Methods

```php
class Screen_Registry {
    public function __construct(string $screens_path, string $parent_slug = 'campaignbridge');
    public function init(): void;
    public function discover_and_register_screens(): void;
    public function get_screen_info(string $screen_name): ?array;
}
```

### Screen_Context Methods

```php
class Screen_Context {
    public function set(string $key, $value): void;
    public function get(string $key, $fallback = null);
    public function is_post(): bool;
    public function post(string $key, $fallback = null);
    public function verify_nonce(string $action): bool;
    public function enqueue_style(string $handle, string $src, array $deps = [], ?string $version = null): void;
    public function enqueue_script(string $handle, string $src, array $deps = [], ?string $version = null, bool $in_footer = true): void;
    public function is_tab(string $tab_name): bool;
    public function get_tab_url(string $tab_name): string;
}
```


### Hooks and Filters

#### System Hooks
- `campaignbridge_admin_init` - Fired when admin system initializes
- `campaignbridge_screen_registered` - Fired when a screen is registered
- `campaignbridge_controller_loaded` - Fired when a controller is loaded

#### Filter Reference
- `campaignbridge_screens_path` - Modify screens discovery path
- `campaignbridge_controller_class` - Modify controller class name

This comprehensive admin system provides a solid foundation for building modern WordPress admin interfaces with advanced features, security, and developer experience in mind.
