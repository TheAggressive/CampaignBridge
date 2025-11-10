# CampaignBridge Admin System Documentation

A comprehensive, developer-friendly admin system for WordPress that provides screens, tabs, controllers, and advanced features for building modern WordPress admin interfaces.

## Table of Contents

### Getting Started
1. [Quick Start](#quick-start)
2. [Architecture Overview](#architecture-overview)

### Core Concepts
3. [Screen System](#screen-system)
   - Auto-Discovery
   - Screen Context
4. [Creating Screens](#creating-screens)
   - File Naming Conventions
   - Simple Screens
   - Tabbed Screens
5. [Tab System](#tab-system)
   - Creating Tabbed Screens
   - Tab File Structure
   - Tab Discovery & Configuration
   - Tab Navigation
6. [Controller System](#controller-system)
   - Auto-Discovery
   - Controller Structure
   - Controller Data Access

### Data & Logic
7. [Controller Data Flow](#controller-data-flow)
   - Data Lifecycle
   - Data Storage Methods
   - Data Access Patterns
   - Data Validation and Sanitization
   - Performance Considerations
8. [Asset Management](#asset-management)
   - Automatic Asset Loading
   - Asset Configuration in _config.php
   - Built Asset Enqueuing (.asset.php files)
   - Script Localization
   - Global Assets
   - Asset Loading Priority

### Security & Quality
9. [Security Features](#security-features)
   - Automatic Security
   - Manual Security Controls
   - Security Best Practices
10. [Best Practices](#best-practices)
    - Code Organization
    - Security
    - Performance
    - User Experience
11. [Troubleshooting](#troubleshooting)
    - Common Issues
    - Debug Mode
    - Error Logging

### Advanced Features
12. [Advanced Features](#advanced-features)
    - Custom Screen Components
    - Dynamic Data Loading
    - Conditional Logic in Screens
    - Multi-Step Processes
13. [Extending the System](#extending-the-system)
    - Custom Controllers
    - Custom Screen Types
    - Custom Form Validators
14. [Screen Registry Advanced Features](#screen-registry-advanced-features)
    - Complete Configuration Options
    - Custom Screen Types
    - Advanced Controller Discovery
    - Custom Asset Loading Strategies
    - Screen Registration Hooks
    - Advanced Screen Discovery
    - Performance Optimizations

### Reference
15. [API Reference](#api-reference)
    - Screen_Registry Methods
    - Enhanced Screen_Context Methods
    - Data Management Methods
    - Advanced Asset Management Methods
    - Tab Navigation Methods
    - Screen Information Methods

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
└── admin-interface.md           # This documentation
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

Each screen gets a `Screen_Context` object (`$screen`) that provides helper methods for data management, asset loading, and screen information. Here are all available methods:

```php
class Screen_Context {
    // Data Management
    public function set(string $key, $value): void;
    public function get(string $key, $fallback = null);
    public function get_all(): array;
    public function has(string $key): bool;

    // Asset Management
    public function enqueue_style(string $handle, string $src, array $deps = [], ?string $version = null): void;
    public function enqueue_script(string $handle, string $src, array $deps = [], ?string $version = null, bool $in_footer = true): void;
    public function asset_enqueue_style(string $handle, string $asset_file_path, array $additional_deps = []): bool;
    public function asset_enqueue_script(string $handle, string $asset_file_path, array $additional_deps = [], bool $in_footer = true): bool;
    public function asset_enqueue(string $handle, string $asset_file_path, bool $enqueue_style = true, bool $enqueue_script = true): array;
    public function localize_script(string $handle, string $object_name, array $data): void;

    // Screen Information
    public function get_screen_info(): array;
    public function get_controller();

    // Tab Navigation
    public function is_tab(string $tab_name): bool;
    public function get_tab_url(string $tab_name): string;
}
```

> **Note**: This shows all public methods available in Screen_Context. For detailed usage examples and parameter explanations, see the [API Reference](#api-reference) section.

## Creating Screens

### File Naming Conventions

The admin system uses file/folder naming conventions to automatically discover and register screens:

#### Rules

1. **Filename → Menu Title**
   - `dashboard.php` → "Dashboard"
   - `email_templates.php` → "Email Templates"
   - `api_logs.php` → "Api Logs"

2. **Filename → URL Slug**
   - `dashboard.php` → `campaignbridge-dashboard`
   - `email_templates.php` → `campaignbridge-email-templates`

3. **Files starting with `_` are ignored**
   - `_config.php` - Configuration file (not a screen)
   - `_helpers.php` - Helper functions (not a screen)
   - `_template.php` - Template partial (not a screen)

4. **Folders create tabbed screens**
   - `settings/` folder → "Settings" page with tabs
   - Each `.php` file inside = a tab

### Simple Screens (No Tabs)

#### Example 1: Dashboard Screen

**File**: `includes/Admin/Screens/dashboard.php`

```php
<?php
/**
 * Dashboard Screen
 *
 * Available variables:
 * - $screen: Screen_Context object with helper methods
 * - $controller: Controller instance (if configured)
 */

// Get data from controller (or use defaults)
$stats = $screen->get('stats', [
    'total_campaigns' => 0,
    'total_sent' => 0,
    'open_rate' => '0%',
    'click_rate' => '0%',
]);

$recent_campaigns = $screen->get('recent_campaigns', []);
?>

<div class="dashboard-screen">

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php _e('Total Campaigns', 'campaignbridge'); ?></h3>
            <p class="stat-number"><?php echo esc_html($stats['total_campaigns']); ?></p>
        </div>

        <div class="stat-card">
            <h3><?php _e('Emails Sent', 'campaignbridge'); ?></h3>
            <p class="stat-number"><?php echo esc_html($stats['total_sent']); ?></p>
        </div>

        <div class="stat-card">
            <h3><?php _e('Open Rate', 'campaignbridge'); ?></h3>
            <p class="stat-number"><?php echo esc_html($stats['open_rate']); ?></p>
        </div>

        <div class="stat-card">
            <h3><?php _e('Click Rate', 'campaignbridge'); ?></h3>
            <p class="stat-number"><?php echo esc_html($stats['click_rate']); ?></p>
        </div>
    </div>

    <!-- Recent Campaigns -->
    <div class="recent-campaigns">
        <h2><?php _e('Recent Campaigns', 'campaignbridge'); ?></h2>

        <?php if (empty($recent_campaigns)): ?>
            <p><?php _e('No campaigns yet.', 'campaignbridge'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Campaign', 'campaignbridge'); ?></th>
                        <th><?php _e('Status', 'campaignbridge'); ?></th>
                        <th><?php _e('Sent', 'campaignbridge'); ?></th>
                        <th><?php _e('Opens', 'campaignbridge'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_campaigns as $campaign): ?>
                        <tr>
                            <td><strong><?php echo esc_html($campaign['title']); ?></strong></td>
                            <td><?php echo esc_html($campaign['status']); ?></td>
                            <td><?php echo number_format($campaign['sent']); ?></td>
                            <td><?php echo number_format($campaign['opens']); ?> (<?php echo esc_html($campaign['open_rate']); ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-align: center;
    }

    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #666;
    }

    .stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #0073aa;
        margin: 0;
    }

    .recent-campaigns {
        background: white;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
</style>

<?php
// Load screen-specific assets
$screen->enqueue_style('dashboard-screen', 'assets/css/admin/screens/dashboard.css');
$screen->enqueue_script('dashboard-screen', 'assets/js/admin/screens/dashboard.js', ['jquery', 'chart-js']);

// Localize data for JavaScript
$screen->localize_script('dashboard-screen', 'dashboardData', [
    'stats' => $stats,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('dashboard_refresh'),
]);
?>
```

**Result**: Creates "Dashboard" menu item that shows stats and recent campaigns.

#### Example 2: Reports Screen

**File**: `includes/Admin/Screens/reports.php`

```php
<?php
/**
 * Reports Screen
 */

// Get data from controller
$reports = $screen->get('reports', []);
$stats = $screen->get('stats', []);
?>

<div class="reports-screen">

    <!-- Stats Summary -->
    <div class="stats-summary">
        <h2><?php _e('Campaign Statistics', 'campaignbridge'); ?></h2>
        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-label"><?php _e('Total Campaigns', 'campaignbridge'); ?></span>
                <span class="stat-value"><?php echo number_format($stats['total_campaigns'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label"><?php _e('Total Recipients', 'campaignbridge'); ?></span>
                <span class="stat-value"><?php echo number_format($stats['total_recipients'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label"><?php _e('Average Open Rate', 'campaignbridge'); ?></span>
                <span class="stat-value"><?php echo esc_html($stats['avg_open_rate'] ?? '0%'); ?></span>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="reports-table">
        <h2><?php _e('Campaign Reports', 'campaignbridge'); ?></h2>

        <?php if (empty($reports)): ?>
            <p><?php _e('No campaign reports available.', 'campaignbridge'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Campaign Name', 'campaignbridge'); ?></th>
                        <th><?php _e('Date Sent', 'campaignbridge'); ?></th>
                        <th><?php _e('Recipients', 'campaignbridge'); ?></th>
                        <th><?php _e('Opens', 'campaignbridge'); ?></th>
                        <th><?php _e('Clicks', 'campaignbridge'); ?></th>
                        <th><?php _e('Status', 'campaignbridge'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><strong><?php echo esc_html($report['campaign_name']); ?></strong></td>
                            <td><?php echo esc_html($report['date_sent']); ?></td>
                            <td><?php echo number_format($report['recipients']); ?></td>
                            <td><?php echo number_format($report['opens']); ?> (<?php echo esc_html($report['open_rate']); ?>)</td>
                            <td><?php echo number_format($report['clicks']); ?> (<?php echo esc_html($report['click_rate']); ?>)</td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($report['status']); ?>">
                                    <?php echo esc_html(ucfirst($report['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<style>
    .stats-summary {
        background: white;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }

    .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 6px;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #0073aa;
    }

    .reports-table {
        background: white;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
    }

    .status-completed {
        background: #d4edda;
        color: #155724;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-failed {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<?php
$screen->enqueue_style('reports-screen', 'assets/css/admin/screens/reports.css');
$screen->enqueue_script('reports-screen', 'assets/js/admin/screens/reports.js', ['jquery']);
?>
```

**Result**: Creates "Reports" menu with campaign statistics and detailed reports table.

### Tabbed Screens

#### Step 1: Create Folder and Config

**Folder**: `includes/Admin/Screens/settings/`

**File**: `includes/Admin/Screens/settings/_config.php`

```php
<?php
/**
 * Settings Page Configuration
 *
 * This file is OPTIONAL. If not present, defaults are used.
 */

return [
    // Page configuration
    'menu_title'   => __('Settings', 'campaignbridge'),
    'page_title'   => __('CampaignBridge Settings', 'campaignbridge'),
    'capability'   => 'manage_options',
    'position'     => 10,
    'description'  => __('Configure your email campaign settings and integrations.', 'campaignbridge'),

    // Optional: Controller for business logic
    'controller'   => \CampaignBridge\Admin\Controllers\Settings_Controller::class,

    // Optional: Page-level assets (loaded on ALL tabs)
    'assets' => [
        'styles' => [
            'settings-page' => 'assets/css/admin/screens/settings/page.css',
        ],
        'scripts' => [
            'settings-page' => [
                'src' => 'assets/js/admin/screens/settings/page.js',
                'deps' => ['jquery', 'wp-api'],
            ],
        ],
    ],
];
```

#### Step 2: Create Tabs

##### Tab 1: General Settings

**File**: `includes/Admin/Screens/settings/general.php`

```php
<?php
/**
 * General Settings Tab
 */

// Get data from controller or options
$from_name = $screen->get('from_name', get_option('campaignbridge_from_name', get_bloginfo('name')));
$from_email = $screen->get('from_email', get_option('campaignbridge_from_email', get_option('admin_email')));
$reply_to = $screen->get('reply_to', get_option('campaignbridge_reply_to', ''));

// Load data and display form using the Form API
require_once __DIR__ . '/../../Core/Form.php';
$form = \CampaignBridge\Admin\Core\Form::make('general_settings')
    ->text('from_name', __('From Name', 'campaignbridge'))
        ->default($from_name)
        ->required()
        ->description(__('The name that appears in the "From" field of emails.', 'campaignbridge'))
    ->email('from_email', __('From Email', 'campaignbridge'))
        ->default($from_email)
        ->required()
        ->description(__('The email address that appears in the "From" field.', 'campaignbridge'))
    ->email('reply_to', __('Reply-To Email', 'campaignbridge'))
        ->default($reply_to)
        ->description(__('Optional. Email address where replies should be sent.', 'campaignbridge'))
    ->before_save(function($data) {
        update_option('campaignbridge_from_name', $data['from_name']);
        update_option('campaignbridge_from_email', $data['from_email']);
        update_option('campaignbridge_reply_to', $data['reply_to']);
        return $data;
    })
    ->success(__('General settings saved successfully!', 'campaignbridge'))
    ->submit(__('Save General Settings', 'campaignbridge'));

$form->render();
?>
```

##### Tab 2: API Settings

**File**: `includes/Admin/Screens/settings/api.php`

```php
<?php
/**
 * API Settings Tab
 */

// Get data from controller or options
$api_key = $screen->get('api_key', get_option('campaignbridge_api_key', ''));
$api_endpoint = $screen->get('api_endpoint', get_option('campaignbridge_api_endpoint', ''));
$debug_mode = $screen->get('debug_mode', get_option('campaignbridge_debug_mode', false));

// Load data and display form using the Form API
require_once __DIR__ . '/../../Core/Form.php';
$form = \CampaignBridge\Admin\Core\Form::make('api_settings')
    ->password('api_key', __('API Key', 'campaignbridge'))
        ->default($api_key)
        ->description(__('Enter your API key for external service integration.', 'campaignbridge'))
    ->url('api_endpoint', __('API Endpoint', 'campaignbridge'))
        ->default($api_endpoint)
        ->description(__('The API endpoint URL for service integration.', 'campaignbridge'))
    ->checkbox('debug_mode', __('Enable Debug Mode', 'campaignbridge'))
        ->default($debug_mode)
        ->description(__('Log API requests and responses for debugging.', 'campaignbridge'))
    ->before_save(function($data) {
        update_option('campaignbridge_api_key', $data['api_key']);
        update_option('campaignbridge_api_endpoint', $data['api_endpoint']);
        update_option('campaignbridge_debug_mode', $data['debug_mode'] ?? false);
        return $data;
    })
    ->success(__('API settings saved successfully!', 'campaignbridge'))
    ->submit(__('Save API Settings', 'campaignbridge'));

$form->render();
?>
```

#### Step 3: Result

**Final Structure**:
```
includes/Admin/Screens/settings/
├── _config.php          # Configuration
├── general.php          # General tab
└── api.php              # API tab
```

**Creates**: "Settings" menu item with tabs for General and API configuration.

### Key Takeaways

1. **Simple screens** = One `.php` file in `Screens/` folder
2. **Tabbed screens** = Folder in `Screens/` with multiple `.php` files
3. **`$screen` variable** is available in all screen files for data access
4. **Form handling** = Use the dedicated **Form API** (separate documentation)
5. **Assets** = Load at bottom of screen file with `$screen->enqueue_*()`
6. **No registration needed** = Just create the file!

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

### Tab Discovery & Configuration

The tab system supports **both automatic discovery AND custom configuration** for maximum flexibility. **This feature is now fully implemented** in the codebase.

#### Tab-Specific Controllers

**✅ FULLY IMPLEMENTED**: Tabs can optionally use their own controllers for better separation of concerns:

```php
<?php
// includes/Admin/Screens/settings/_config.php
return array(
    'tabs' => array(
        'general' => array(
            'label'      => __('General', 'campaignbridge'),
            'controller' => 'General_Settings_Controller',  // Tab-specific controller
            'order'      => 10,
        ),
        'advanced' => array(
            'label'      => __('Advanced', 'campaignbridge'),
            'controller' => 'Advanced_Settings_Controller', // Tab-specific controller
            'order'      => 20,
        ),
        // This tab uses the main screen controller
        'security' => array(
            'label' => __('Security', 'campaignbridge'),
            'order' => 30,
            // No controller specified - uses screen controller
        ),
    ),
);
```

**Controller Discovery:**
- Tab controllers are auto-discovered: `tab-name_Controller.php`
- Located in: `includes/Admin/Controllers/Tab_Name_Controller.php`
- Fallback to screen controller if tab controller not found
- Tab controllers can access screen controller via `$this->screen_controller`

#### Tab Controller Example

```php
<?php
// includes/Admin/Controllers/General_Settings_Controller.php
namespace CampaignBridge\Admin\Controllers;

class General_Settings_Controller {

    public function __construct() {
        // Access to screen controller if needed
        // $this->screen_controller is automatically injected
    }

    public function get_data(): array {
        return array(
            'from_name'    => get_option('campaignbridge_from_name', get_bloginfo('name')),
            'from_email'   => get_option('campaignbridge_from_email', get_option('admin_email')),
            'reply_to'     => get_option('campaignbridge_reply_to', ''),
            'general_data' => $this->get_general_settings_data(),
        );
    }

    public function handle_request(): void {
        // Tab-specific request handling
        if (isset($_POST['save_general'])) {
            $this->save_general_settings();
        }
    }

    private function save_general_settings(): void {
        // Tab-specific logic
        update_option('campaignbridge_from_name', sanitize_text_field($_POST['from_name']));
        update_option('campaignbridge_from_email', sanitize_email($_POST['from_email']));

        wp_redirect(add_query_arg('updated', '1', $_SERVER['REQUEST_URI']));
        exit;
    }
}
```

#### Automatic Discovery (Default)

By default, tabs are discovered automatically from PHP files in the screen folder:

- Tab files must be named with `.php` extension
- Tab files cannot start with underscore (`_`)
- Tab names are derived from filenames (e.g., `general.php` → "general" tab)
- Tab labels are auto-generated from filenames (e.g., `email_settings.php` → "Email Settings")
- All tabs use the same capability as the parent screen
- Tab order follows filesystem order

#### Custom Tab Configuration

You can override automatic discovery with custom configuration in `_config.php`:

```php
<?php
return array(
    'menu_title' => __('Settings', 'campaignbridge'),

    // ===== CUSTOM TAB CONFIGURATION =====
    'tabs' => array(
        // Tab definition by filename (must match actual PHP file)
        'general' => array(
            'label'       => __('General Settings', 'campaignbridge'),  // Custom label
            'capability'  => 'read',                                    // Per-tab capability
            'order'       => 10,                                        // Custom ordering
            'description' => __('Basic configuration options', 'campaignbridge'),
        ),

        'advanced' => array(
            'label'       => __('Advanced Options', 'campaignbridge'),
            'capability'  => 'manage_options',                         // Higher permission
            'order'       => 20,
            'description' => __('Advanced configuration for power users', 'campaignbridge'),
        ),

        'security' => array(
            'label'       => __('Security Settings', 'campaignbridge'),
            'capability'  => 'manage_options',
            'order'       => 5,                                         // Higher priority (lower number)
            'description' => __('Security and access control settings', 'campaignbridge'),
        ),

        // You can hide tabs by setting capability to false
        'debug' => array(
            'label'       => __('Debug Tools', 'campaignbridge'),
            'capability'  => defined('WP_DEBUG') && WP_DEBUG ? 'manage_options' : false, // Conditional
            'order'       => 30,
        ),
    ),

    // ... other config options
);
```

#### Configuration Rules

1. **File Requirement**: Each tab in config must have a corresponding PHP file
2. **Capability Checking**: Tabs with insufficient capability are hidden
3. **Ordering**: Lower `order` values appear first (10, 20, 30...)
4. **Fallback**: Any tabs not in config use automatic discovery with defaults
5. **Conditional Logic**: Use `false` capability to conditionally hide tabs

#### Examples

**Hide Advanced Tab for Non-Admins:**
```php
'tabs' => array(
    'advanced' => array(
        'capability' => current_user_can('manage_options') ? 'read' : false,
    ),
),
```

**Custom Ordering:**
```php
'tabs' => array(
    'security' => array('order' => 1),    // First
    'general'  => array('order' => 10),   // Second
    'advanced' => array('order' => 20),   // Third
),
```

### Tab Navigation

Automatic tab navigation is provided:

```php
<!-- Automatic tab navigation (handled by Screen_Registry) -->
<!-- This is automatically generated and cannot be customized -->
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

## Controller Data Flow

### Data Lifecycle

The controller data flow follows a clear lifecycle that ensures data is available throughout the screen rendering process:

```
1. Controller Instantiation
       ↓
2. get_data() Method Called
       ↓
3. Data Stored in Screen_Context
       ↓
4. Screen/Tab Template Rendered
       ↓
5. Data Accessed via $screen or $controller
```

### Data Storage Methods

Controllers can store data in multiple ways:

#### Direct Screen_Context Storage

```php
<?php
class Analytics_Controller {
    public function __construct() {
        // Method 1: Direct storage in constructor
        $screen = $this->get_screen_context();
        $screen->set('stats', $this->get_stats());
        $screen->set('chart_data', $this->get_chart_data());
    }

    public function get_data(): array {
        // Method 2: Return data for automatic storage
        return [
            'users' => $this->get_users(),
            'settings' => get_option('analytics_settings'),
        ];
    }
}
```

#### Automatic Data Storage

The system automatically calls `get_data()` and stores the result:

```php
<?php
class Settings_Controller {
    public function get_data(): array {
        return [
            'current_settings' => get_option('my_settings', []),
            'available_options' => $this->get_available_options(),
            'validation_rules' => $this->get_validation_rules(),
            'last_updated' => get_option('settings_last_updated'),
        ];
    }
}
```

### Data Access Patterns

#### In Screen Templates

Data is accessible through both `$controller` and `$screen` variables:

```php
<?php
// In any screen or tab template

// Method 1: Via controller (direct access to get_data() result)
if ($controller) {
    $data = $controller->get_data();
    $stats = $data['stats'];
    $settings = $data['settings'];
}

// Method 2: Via screen context (same data, different access)
$stats = $screen->get('stats', []);
$settings = $screen->get('settings', []);

// Method 3: Check if data exists
if ($screen->has('stats')) {
    $stats = $screen->get('stats');
    // Process stats
}

// Method 4: Get all data at once
$all_data = $screen->get_all();
```

#### In Controller Methods

Controllers can access their own stored data:

```php
<?php
class My_Controller {
    private array $processed_data = [];

    public function get_data(): array {
        return [
            'raw_data' => $this->get_raw_data(),
            'processed_data' => $this->processed_data,
            'metadata' => $this->get_metadata(),
        ];
    }

    public function handle_request(): void {
        // Access data during request handling
        $data = $this->get_data();
        $current_settings = $data['raw_data'];

        // Process and update
        $this->processed_data = $this->process_data($current_settings);
    }
}
```

### Data Flow Timing

Understanding when data is available is crucial:

#### Request Handling Phase
- Controllers are instantiated
- `handle_request()` is called for form processing
- Data can be modified during this phase

#### Rendering Phase
- `get_data()` is called
- Data is stored in Screen_Context
- Templates are rendered with access to data

```php
<?php
class Dynamic_Controller {
    private array $request_data = [];

    public function handle_request(): void {
        // Phase 1: Handle form submissions
        if (isset($_POST['update_data'])) {
            $this->request_data = $this->process_form_data($_POST);
            // Data modified during request handling
        }
    }

    public function get_data(): array {
        // Phase 2: Return data for template rendering
        return [
            'request_data' => $this->request_data,
            'server_data' => $this->get_server_data(),
            'computed_data' => $this->compute_data($this->request_data),
        ];
    }
}
```

### Advanced Data Patterns

#### Lazy Loading

Load heavy data only when needed:

```php
<?php
class Heavy_Controller {
    private ?array $heavy_data = null;

    public function get_data(): array {
        return [
            'basic_data' => $this->get_basic_data(),
            'heavy_data' => $this->get_heavy_data(), // Called only when get_data() is invoked
        ];
    }

    private function get_heavy_data(): array {
        if ($this->heavy_data === null) {
            // Load heavy data only once
            $this->heavy_data = $this->load_heavy_dataset();
        }
        return $this->heavy_data;
    }
}
```

#### Conditional Data Loading

Load different data based on context:

```php
<?php
class Context_Controller {
    public function get_data(): array {
        $base_data = [
            'user_role' => wp_get_current_user()->roles[0] ?? 'subscriber',
            'is_admin' => current_user_can('manage_options'),
        ];

        // Add admin-only data
        if ($base_data['is_admin']) {
            $base_data['admin_stats'] = $this->get_admin_stats();
            $base_data['system_info'] = $this->get_system_info();
        }

        // Add user-specific data
        $base_data['user_preferences'] = $this->get_user_preferences();

        return $base_data;
    }
}
```

#### Data Sharing Between Controllers

Controllers can share data through WordPress options or transients:

```php
<?php
class Shared_Controller {
    private const SHARED_DATA_KEY = 'shared_controller_data';

    public function get_data(): array {
        $shared_data = get_transient(self::SHARED_DATA_KEY);

        if (!$shared_data) {
            $shared_data = $this->generate_shared_data();
            set_transient(self::SHARED_DATA_KEY, $shared_data, HOUR_IN_SECONDS);
        }

        return [
            'shared_data' => $shared_data,
            'instance_data' => $this->get_instance_data(),
        ];
    }

    private function generate_shared_data(): array {
        // Expensive operation shared across controller instances
        return $this->compute_expensive_shared_data();
    }
}
```

### Data Validation and Sanitization

Always validate and sanitize controller data:

```php
<?php
class Secure_Controller {
    public function get_data(): array {
        return [
            'user_input' => $this->sanitize_user_input(
                get_option('user_input', '')
            ),
            'numeric_value' => absint(
                get_option('numeric_value', 0)
            ),
            'boolean_flag' => (bool) get_option('boolean_flag', false),
            'safe_array' => array_map('sanitize_text_field',
                (array) get_option('safe_array', [])
            ),
        ];
    }

    private function sanitize_user_input(string $input): string {
        // Additional custom sanitization
        return wp_kses($input, ['strong' => [], 'em' => []]);
    }
}
```

### Performance Considerations

1. **Cache expensive operations** in `get_data()`
2. **Use transients** for shared data between controllers
3. **Lazy load** heavy data only when needed
4. **Avoid database queries** in constructors
5. **Pre-compute** data when possible

### Debugging Data Flow

Enable debugging to trace data flow:

```php
<?php
class Debug_Controller {
    public function get_data(): array {
        $data = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'debug_info' => WP_DEBUG ? $this->get_debug_info() : null,
        ];

        // Log data flow for debugging
        if (WP_DEBUG) {
            error_log('Controller data: ' . print_r($data, true));
        }

        return $data;
    }
}
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

The admin system provides comprehensive asset management through the `Screen_Context` class, offering multiple methods for different asset loading scenarios.

#### Standard WordPress Enqueuing

```php
// Standard WordPress asset enqueuing
$screen->enqueue_style(
    'my-custom-style',                    // Handle
    plugin_dir_url(__FILE__) . 'assets/style.css', // Source URL
    ['wp-components'],                    // Dependencies
    '1.0.0'                              // Version
);

$screen->enqueue_script(
    'my-custom-script',                   // Handle
    plugin_dir_url(__FILE__) . 'assets/script.js', // Source URL
    ['jquery', 'wp-api'],                // Dependencies
    '1.0.0',                             // Version
    true                                 // Load in footer
);
```

#### Asset Configuration in _config.php

Assets are loaded based on explicit configuration in `_config.php` files. There is **no automatic asset discovery** based on file naming conventions in the current implementation.

All assets must be explicitly declared in the configuration:

```php
<?php
// includes/Admin/Screens/my-screen/_config.php
return [
    'menu_title' => 'My Screen',
    'assets' => [
        // Traditional assets
        'styles' => [
            'my-screen' => 'assets/css/admin/screens/my-screen.css',
        ],
        'scripts' => [
            'my-screen' => 'assets/js/admin/screens/my-screen.js',
        ],

        // Built assets (.asset.php files)
        'asset_styles' => [
            'my-screen' => 'build/admin/my-screen.asset.php',
        ],
        'asset_scripts' => [
            'my-screen' => 'build/admin/my-screen.asset.php',
        ],
    ],
];
```

#### Built Asset Enqueuing (.asset.php files)

For modern build tools, enqueue assets from .asset.php files with automatic dependency resolution:

```php
// Enqueue style from .asset.php file with automatic dependency detection
$success = $screen->asset_enqueue_style(
    'my-component',                      // Handle
    'build/admin/my-component.asset.php', // Path to .asset.php file
    ['wp-components']                    // Additional dependencies
);

// Enqueue script from .asset.php file with automatic dependency detection
$success = $screen->asset_enqueue_script(
    'my-component',                      // Handle
    'build/admin/my-component.asset.php', // Path to .asset.php file
    ['jquery'],                          // Additional dependencies
    true                                 // Load in footer
);

// Combined enqueuing for both CSS and JS from .asset.php
$result = $screen->asset_enqueue(
    'my-component',                      // Handle
    'build/admin/my-component.asset.php', // Path to .asset.php file
    true,                                // Enqueue style
    true                                 // Enqueue script
);

// Result contains success status for each asset type
if ($result['style'] && $result['script']) {
    // Both assets enqueued successfully
}
```

#### Script Localization

Localize scripts with PHP data for JavaScript consumption:

```php
// Localize script with data
$screen->localize_script(
    'my-script',                         // Script handle (without 'cb-' prefix)
    'myScriptData',                      // JavaScript object name
    [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('my_action'),
        'strings' => [
            'loading' => __('Loading...', 'campaignbridge'),
            'error' => __('An error occurred', 'campaignbridge'),
        ],
        'settings' => get_option('my_settings', []),
    ]
);

// In JavaScript, access the data:
console.log(myScriptData.ajaxUrl);       // Access localized data
console.log(myScriptData.strings.loading); // Access translated strings
```

### Global Assets

Global admin assets are loaded for all CampaignBridge screens:

```php
// In Admin.php - global assets loaded for all screens
wp_enqueue_style('campaignbridge-admin-global-styles', \CampaignBridge_Plugin::url() . 'dist/styles/styles.css');
wp_enqueue_script('campaignbridge-admin-global-scripts', \CampaignBridge_Plugin::url() . 'dist/scripts/admin/settings.js');

// With localization for JavaScript
wp_localize_script('campaignbridge-admin-global-scripts', 'campaignBridge', [
    'ajaxUrl'   => admin_url('admin-ajax.php'),
    'restUrl'   => rest_url('campaignbridge/v1/'),
    'restNonce' => wp_create_nonce('wp_rest'),
    'pluginUrl' => \CampaignBridge_Plugin::url(),
]);
```

### Asset Loading Priority

Assets are loaded in the following order:
1. **Global assets** - Loaded for all CampaignBridge screens (from Admin.php)
2. **Configured assets** - Assets explicitly declared in `_config.php` files
3. **Manual assets** - Assets enqueued directly via `Screen_Context` methods

### Best Practices

1. **Use explicit configuration** for all assets in `_config.php` files
2. **Localize scripts** to pass PHP data to JavaScript
3. **Use semantic versioning** for cache busting
4. **Minimize global assets** to reduce overhead
5. **Prefer built assets** (.asset.php files) for better dependency management

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
2. Ensure tab filename doesn't start with underscore (`_`)
3. Check for PHP syntax errors in tab files
4. Verify tab files are in the correct screen folder
5. Check browser console for JavaScript errors

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

### Enhanced Screen_Context Methods

```php
class Screen_Context {
    // Data Management
    public function set(string $key, $value): void;
    public function get(string $key, $fallback = null);
    public function get_all(): array;
    public function has(string $key): bool;

    // Asset Management
    public function enqueue_style(string $handle, string $src, array $deps = [], ?string $version = null): void;
    public function enqueue_script(string $handle, string $src, array $deps = [], ?string $version = null, bool $in_footer = true): void;
    public function asset_enqueue_style(string $handle, string $asset_file_path, array $additional_deps = []): bool;
    public function asset_enqueue_script(string $handle, string $asset_file_path, array $additional_deps = [], bool $in_footer = true): bool;
    public function asset_enqueue(string $handle, string $asset_file_path, bool $enqueue_style = true, bool $enqueue_script = true): array;
    public function localize_script(string $handle, string $object_name, array $data): void;

    // Tab Navigation
    public function is_tab(string $tab_name): bool;
    public function get_tab_url(string $tab_name): string;

    // Screen Information
    public function get_screen_info(): array;
    public function get_controller();
}
```

#### Data Management Methods

```php
// Store arbitrary data for use in templates
$screen->set('stats', ['visitors' => 1234, 'pageviews' => 5678]);
$screen->set('settings', get_option('my_settings'));

// Retrieve data with optional fallback
$stats = $screen->get('stats', []);
$settings = $screen->get('settings', []);

// Check if data key exists
if ($screen->has('stats')) {
    // Data exists, safe to process
}

// Get all stored data at once
$all_data = $screen->get_all();
```

> **Note**: Form handling methods (`is_post()`, `post()`, `nonce_field()`, `verify_nonce()`) have been removed from Screen_Context. Use the **Form API** instead for all form-related functionality. This provides better security, validation, and user experience.

#### Advanced Asset Management Methods

```php
// Standard WordPress enqueuing
$screen->enqueue_style('my-style', 'assets/css/admin/screens/my-style.css');
$screen->enqueue_script('my-script', 'assets/js/admin/screens/my-script.js');

// Built asset enqueuing (.asset.php files)
$success = $screen->asset_enqueue_style('component', 'build/admin/component.asset.php');
$success = $screen->asset_enqueue_script('component', 'build/admin/component.asset.php');

// Combined asset enqueuing (both CSS and JS from same .asset.php)
$result = $screen->asset_enqueue('component', 'build/admin/component.asset.php');
// Returns: ['style' => true, 'script' => true]

// Script localization for JavaScript data
$screen->localize_script('my-script', 'MyScriptData', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('my_action'),
    'strings' => [
        'loading' => __('Loading...', 'plugin'),
        'success' => __('Success!', 'plugin'),
    ],
]);
```

#### Tab Navigation Methods

```php
// Check current tab
if ($screen->is_tab('general')) {
    // Currently on general tab
}

// Generate tab URLs
$general_url = $screen->get_tab_url('general');
$advanced_url = $screen->get_tab_url('advanced');
```

#### Screen Information Methods

```php
// Get screen metadata
$info = $screen->get_screen_info();
// Returns: ['name', 'type', 'slug', 'config', etc.]

// Access controller instance
$controller = $screen->get_controller();
if ($controller && method_exists($controller, 'custom_method')) {
    $controller->custom_method();
}
```

## Screen Registry Advanced Features

### Complete Configuration Options

The Screen Registry supports comprehensive configuration options for both simple and tabbed screens:

#### Screen Configuration Array

```php
<?php
// 100% ACCURATE: Complete _config.php with ALL supported options
// Based on actual Screen_Registry.php implementation

return array(
    // ===== CORE SETTINGS (Required for add_submenu_page) =====
    'menu_title'  => __('My Custom Screen', 'campaignbridge'),     // Menu item text
    'page_title'  => __('My Custom Screen - Full Title', 'campaignbridge'), // Page header
    'capability'  => 'manage_options',                            // Required capability
    'position'    => 25,                                          // Menu position (optional)

    // ===== DISPLAY SETTINGS =====
    'description' => __('Description shown under the page title.', 'campaignbridge'), // Displayed description

    // ===== CONTROLLER SETTINGS =====
    'controller'  => 'My_Custom_Controller',                      // Custom controller class name

    // ===== TAB CONFIGURATION (NEW - Fully Implemented) =====
    'tabs' => array(
        // Tab definition by filename (must match actual PHP file)
        'general' => array(
            'label'       => __('General Settings', 'campaignbridge'),  // Custom label
            'capability'  => 'read',                                    // Per-tab capability
            'order'       => 10,                                        // Custom ordering
            'description' => __('Basic configuration options', 'campaignbridge'),
        ),

        'advanced' => array(
            'label'       => __('Advanced Options', 'campaignbridge'),
            'capability'  => 'manage_options',                         // Higher permission
            'order'       => 20,
            'description' => __('Advanced configuration for power users', 'campaignbridge'),
        ),

        'security' => array(
            'label'       => __('Security Settings', 'campaignbridge'),
            'capability'  => 'manage_options',
            'order'       => 5,                                         // Higher priority (lower number)
            'description' => __('Security and access control settings', 'campaignbridge'),
        ),

        // You can hide tabs by setting capability to false
        'debug' => array(
            'label'       => __('Debug Tools', 'campaignbridge'),
            'capability'  => defined('WP_DEBUG') && WP_DEBUG ? 'manage_options' : false, // Conditional
            'order'       => 30,
        ),
    ),

    // ===== DATA SETTINGS =====
    'data' => array(                                              // Custom data for screen context
        'custom_setting' => 'value',
        'api_endpoint' => 'https://api.example.com',
        'max_items' => 50,
    ),

    // ===== ASSET SETTINGS (All variations supported) =====
    'assets' => array(
        // Traditional CSS files (relative paths)
        'styles' => array(
            'my-screen-css' => 'assets/css/admin/screens/my-screen.css',
        ),

        // Traditional JS files (can be string or array)
        'scripts' => array(
            // Simple string format
            'my-screen-js' => 'assets/js/admin/screens/my-screen.js',

            // Array format with dependencies
            'my-validation-js' => array(
                'src' => 'assets/js/admin/screens/my-validation.js',
                'deps' => array('jquery', 'wp-api'),             // Additional dependencies
            ),
        ),

        // Built CSS assets (.asset.php files)
        'asset_styles' => array(
            'my-built-css' => 'build/admin/my-screen.asset.php',
        ),

        // Built JS assets (.asset.php files - can be string or array)
        'asset_scripts' => array(
            // Simple string format
            'my-built-js' => 'build/admin/my-screen.asset.php',

            // Array format with options
            'my-enhanced-js' => array(
                'src' => 'build/admin/my-enhanced.asset.php',     // Asset file path
                'deps' => array('wp-notices'),                    // Additional dependencies
                'in_footer' => true,                              // Load in footer (default: true)
            ),
        ),

        // Both CSS and JS from same .asset.php file
        'asset_both' => array(
            'my-components' => 'build/admin/my-components.asset.php',
        ),
    ),
);
```

#### Simple Screen Configuration

```php
<?php
// Simple screen configuration (stored in same directory or via filter)
// Example: includes/Admin/Screens/my-screen.php with _config.php

return [
    'menu_title'  => 'My Custom Screen',
    'page_title'  => 'My Screen - Advanced Configuration',
    'capability'  => 'manage_options',
    'description' => 'Configure your custom settings here',
    'controller'  => 'My_Custom_Controller',
    'position'    => 30,
];
```

#### Tabbed Screen Configuration

```php
<?php
// Tabbed screen configuration (_config.php in screen folder)
// Example: includes/Admin/Screens/settings/_config.php

return [
    'menu_title'  => 'Settings',
    'page_title'  => 'Plugin Settings',
    'capability'  => 'manage_options',
    'description' => 'Configure plugin settings and preferences',
    'controller'  => 'Settings_Controller',

    // Asset configuration (optional)
    'assets' => [
        'styles' => [
            'settings-page' => 'assets/css/admin/screens/settings/page.css',
        ],
        'scripts' => [
            'settings-page' => 'assets/js/admin/screens/settings/page.js',
        ],
    ],
];
```

**Note**: Tab configuration is **file-based only**. Tabs are automatically discovered from PHP files in the screen folder. The system does not support custom tab ordering, labels, or per-tab capabilities in the current implementation.

### Custom Screen Types

Extend the registry to support custom screen types:

```php
<?php
class Custom_Screen_Registry extends \CampaignBridge\Admin\Core\Screen_Registry {

    /**
     * Override to add custom screen type detection
     */
    public function discover_and_register_screens(): void {
        if (!is_dir($this->screens_path)) {
            return;
        }

        foreach (scandir($this->screens_path) as $item) {
            if ('.' === $item || '..' === $item || strpos($item, '_') === 0) {
                continue;
            }

            $path = $this->screens_path . $item;

            if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $this->register_simple_screen($item);
            } elseif (is_dir($path)) {
                // Check for custom screen type markers
                if (file_exists($path . '/dashboard.json')) {
                    $this->register_dashboard_screen($item);
                } elseif (file_exists($path . '/wizard.json')) {
                    $this->register_wizard_screen($item);
                } else {
                    $this->register_tabbed_screen($item);
                }
            }
        }
    }

    /**
     * Register custom dashboard screen
     */
    private function register_dashboard_screen(string $folder_name): void {
        $config = $this->load_config($folder_name, 'dashboard');
        $this->register_screen($folder_name, $this->generate_slug($folder_name), $config, 'dashboard');
    }

    /**
     * Register custom wizard screen
     */
    private function register_wizard_screen(string $folder_name): void {
        $config = $this->load_config($folder_name, 'wizard');
        $this->register_screen($folder_name, $this->generate_slug($folder_name), $config, 'wizard');
    }

    /**
     * Load configuration from JSON file
     */
    private function load_config(string $folder_name, string $type): array {
        $config_file = $this->screens_path . $folder_name . '/' . $type . '.json';
        $config = json_decode(file_get_contents($config_file), true);

        // Merge with defaults
        return array_merge($this->get_default_config($type), $config);
    }

    /**
     * Get default configuration for custom screen types
     */
    private function get_default_config(string $type): array {
        $defaults = [
            'dashboard' => [
                'capability' => 'read',
                'widgets_per_row' => 3,
                'show_sidebar' => true,
            ],
            'wizard' => [
                'capability' => 'manage_options',
                'steps' => [],
                'allow_skip' => false,
            ],
        ];

        return $defaults[$type] ?? [];
    }
}
```

### Advanced Controller Discovery

Customize how controllers are discovered and loaded:

```php
<?php
class Advanced_Screen_Registry extends \CampaignBridge\Admin\Core\Screen_Registry {

    /**
     * Enhanced controller discovery with namespace support
     */
    private function discover_controller(string $name): ?string {
        $possible_names = [
            $name . '_Controller',
            'Controller_' . $name,
            ucfirst($name) . '_Controller',
            'Admin_' . ucfirst($name) . '_Controller',
        ];

        $possible_paths = [
            $this->controllers_path . $name . '_Controller.php',
            $this->controllers_path . 'Controller_' . $name . '.php',
            $this->controllers_path . ucfirst($name) . '_Controller.php',
            dirname($this->controllers_path) . '/Controllers/' . ucfirst($name) . '_Controller.php',
        ];

        foreach ($possible_names as $index => $class_name) {
            $file_path = $possible_paths[$index] ?? null;

            if ($file_path && file_exists($file_path)) {
                require_once $file_path;

                if (class_exists($class_name)) {
                    return $class_name;
                }
            }
        }

        // Try with custom namespace
        $custom_class = 'MyPlugin\\Admin\\Controllers\\' . ucfirst($name) . '_Controller';
        if (class_exists($custom_class)) {
            return $custom_class;
        }

        return null;
    }

    /**
     * Load controller with dependency injection
     */
    private function instantiate_controller(string $class_name) {
        // Check if controller needs dependencies
        $reflection = new ReflectionClass($class_name);

        if ($reflection->hasMethod('__construct')) {
            $constructor = $reflection->getMethod('__construct');
            $parameters = $constructor->getParameters();

            if (!empty($parameters)) {
                // Controller has dependencies - use DI container
                return $this->container->get($class_name);
            }
        }

        // No dependencies - instantiate directly
        return new $class_name();
    }
}
```

### Custom Asset Loading Strategies

Implement custom asset loading logic:

```php
<?php
class Custom_Screen_Registry extends \CampaignBridge\Admin\Core\Screen_Registry {

    /**
     * Custom asset enqueuing with conditional loading
     */
    private function enqueue_screen_assets(string $screen_name, string $type, array $config): void {
        // Always load global assets
        $this->enqueue_global_assets();

        // Load screen-specific assets
        if (!empty($config['enqueue_screen'])) {
            $this->enqueue_screen_specific_assets($screen_name, $config);
        }

        // Load tab-specific assets for tabbed screens
        if ($type === 'tabbed' && !empty($config['enqueue_tab'])) {
            $this->enqueue_tab_specific_assets($screen_name, $config);
        }

        // Load conditional assets based on user capabilities
        $this->enqueue_conditional_assets($config);

        // Load custom assets from configuration
        if (!empty($config['custom_assets'])) {
            $this->enqueue_custom_assets($config['custom_assets']);
        }
    }

    /**
     * Enqueue assets based on user capabilities
     */
    private function enqueue_conditional_assets(array $config): void {
        if (current_user_can('manage_options')) {
            // Admin-only assets
            wp_enqueue_script('admin-enhanced-features', '...');
        }

        if (current_user_can('edit_posts')) {
            // Editor assets
            wp_enqueue_style('editor-custom-styles', '...');
        }
    }

    /**
     * Load assets from external CDN or custom sources
     */
    private function enqueue_custom_assets(array $custom_assets): void {
        foreach ($custom_assets as $asset) {
            if ($asset['type'] === 'css') {
                wp_enqueue_style(
                    $asset['handle'],
                    $asset['src'],
                    $asset['deps'] ?? [],
                    $asset['version'] ?? null
                );
            } elseif ($asset['type'] === 'js') {
                wp_enqueue_script(
                    $asset['handle'],
                    $asset['src'],
                    $asset['deps'] ?? [],
                    $asset['version'] ?? null,
                    $asset['in_footer'] ?? true
                );
            }
        }
    }
}
```

### Screen Registration Hooks

Utilize WordPress hooks for advanced customization:

```php
<?php
class Hookable_Screen_Registry extends \CampaignBridge\Admin\Core\Screen_Registry {

    /**
     * Allow customization through WordPress hooks
     */
    private function register_screen(string $screen_name, string $slug, array $config, string $type): void {
        // Apply filters before registration
        $config = apply_filters('campaignbridge_screen_config', $config, $screen_name, $type);
        $config = apply_filters("campaignbridge_screen_config_{$type}", $config, $screen_name);

        // Allow plugins to modify or skip registration
        $should_register = apply_filters('campaignbridge_register_screen', true, $screen_name, $config, $type);

        if (!$should_register) {
            return;
        }

        // Proceed with registration...

        // Fire action after registration
        do_action('campaignbridge_screen_registered', $screen_name, $config, $type);
        do_action("campaignbridge_{$type}_screen_registered", $screen_name, $config);
    }

    /**
     * Allow custom controller instantiation
     */
    private function instantiate_controller(string $class_name) {
        // Allow plugins to provide custom controller instances
        $controller = apply_filters('campaignbridge_instantiate_controller', null, $class_name);

        if ($controller !== null) {
            return $controller;
        }

        // Default instantiation
        return parent::instantiate_controller($class_name);
    }
}

// Usage in plugin:
add_filter('campaignbridge_screen_config', function($config, $screen_name, $type) {
    // Add custom configuration
    if ($screen_name === 'settings') {
        $config['custom_setting'] = 'value';
    }
    return $config;
});

add_filter('campaignbridge_register_screen', function($should_register, $screen_name, $config, $type) {
    // Skip registration for certain screens
    if ($screen_name === 'debug' && !WP_DEBUG) {
        return false;
    }
    return $should_register;
});
```

### Advanced Screen Discovery

Customize how screens are discovered:

```php
<?php
class Advanced_Discovery_Registry extends \CampaignBridge\Admin\Core\Screen_Registry {

    /**
     * Multi-path screen discovery
     */
    private array $discovery_paths = [];

    public function __construct(array $paths, string $parent_slug = 'campaignbridge') {
        $this->discovery_paths = $paths;
        parent::__construct('', $parent_slug); // Empty path since we override
    }

    /**
     * Discover screens from multiple paths
     */
    public function discover_and_register_screens(): void {
        foreach ($this->discovery_paths as $path_config) {
            $this->discover_from_path($path_config);
        }
    }

    /**
     * Discover screens from a specific path with custom logic
     */
    private function discover_from_path(array $path_config): void {
        $path = $path_config['path'];
        $namespace = $path_config['namespace'] ?? '';
        $priority = $path_config['priority'] ?? 10;

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $item_path = $path . '/' . $item;

            // Apply custom filtering
            if (!$this->should_discover_item($item, $item_path, $path_config)) {
                continue;
            }

            // Register with custom logic
            $this->register_discovered_item($item, $item_path, $namespace, $priority);
        }
    }

    /**
     * Custom filtering logic for discovered items
     */
    private function should_discover_item(string $item, string $path, array $config): bool {
        // Skip hidden files
        if (strpos($item, '_') === 0) {
            return false;
        }

        // Apply custom filters
        if (isset($config['exclude_pattern']) && preg_match($config['exclude_pattern'], $item)) {
            return false;
        }

        // Check file permissions
        if (!is_readable($path)) {
            return false;
        }

        return true;
    }

    /**
     * Custom registration logic
     */
    private function register_discovered_item(string $item, string $path, string $namespace, int $priority): void {
        if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $this->register_simple_screen_from_path($item, $path, $namespace, $priority);
        } elseif (is_dir($path)) {
            $this->register_tabbed_screen_from_path($item, $path, $namespace, $priority);
        }
    }
}
```

### Performance Optimizations

Implement caching and optimization for screen registration:

```php
<?php
class Optimized_Screen_Registry extends \CampaignBridge\Admin\Core\Screen_Registry {

    private const CACHE_KEY = 'campaignbridge_screen_registry';
    private const CACHE_GROUP = 'campaignbridge_admin';

    /**
     * Cached screen discovery
     */
    public function discover_and_register_screens(): void {
        // Check cache first (only in production)
        if (!WP_DEBUG) {
            $cached_screens = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);
            if ($cached_screens !== false) {
                $this->register_cached_screens($cached_screens);
                return;
            }
        }

        // Discover screens normally
        parent::discover_and_register_screens();

        // Cache the results
        if (!WP_DEBUG) {
            $this->cache_screen_data();
        }
    }

    /**
     * Register screens from cached data
     */
    private function register_cached_screens(array $cached_screens): void {
        foreach ($cached_screens as $screen_data) {
            // Reconstruct and register screens from cached data
            $this->register_screen(
                $screen_data['name'],
                $screen_data['slug'],
                $screen_data['config'],
                $screen_data['type']
            );
        }
    }

    /**
     * Cache screen registration data
     */
    private function cache_screen_data(): void {
        $cache_data = [];

        foreach ($this->registered_screens as $slug => $screen_data) {
            $cache_data[] = [
                'name'   => $screen_data['name'],
                'slug'   => $slug,
                'config' => $screen_data['config'],
                'type'   => $screen_data['type'],
            ];
        }

        wp_cache_set(self::CACHE_KEY, $cache_data, self::CACHE_GROUP, HOUR_IN_SECONDS);
    }

    /**
     * Clear cache when screens are modified
     */
    public function clear_cache(): void {
        wp_cache_delete(self::CACHE_KEY, self::CACHE_GROUP);
    }
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

### Recent Changes (v2.0+)

- **Documentation Reorganization**: Better structured table of contents with logical grouping
- **Enhanced Auto-Discovery**: Improved screen and controller discovery with better error handling
- **Advanced Configuration**: Complete `_config.php` support with asset management and custom settings
- **Performance Optimizations**: Caching support and optimized asset loading
- **Security Enhancements**: Built-in CSRF protection and capability checking
- **Asset Management**: Support for built assets (.asset.php files) and script localization
- **Tab System Improvements**: Custom tab ordering, per-tab capabilities, and conditional loading
- **Controller Architecture**: Enhanced data flow with lazy loading and performance optimizations
