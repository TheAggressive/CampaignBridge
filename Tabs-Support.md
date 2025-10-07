# Complete Auto-Discovery Admin System - Implementation Guide

> **Convention-Over-Configuration with Optional Override**
>
> Build a file-based admin system where screens auto-discover from files, controllers auto-attach by name, and `_config.php` is only needed for overrides.

---

## 🎯 System Overview

### Core Principles

1. **Zero Config Default** - Just create files, everything works
2. **Convention Over Configuration** - Naming determines behavior
3. **Optional Overrides** - Add `_config.php` only when you need custom settings
4. **Auto-Discovery** - Controllers, tabs, assets auto-detected
5. **Progressive Enhancement** - Start simple, add complexity only when needed

### What Gets Auto-Discovered

- ✅ **Screens** - From files/folders in `Screens/`
- ✅ **Tabs** - From PHP files inside screen folders
- ✅ **Controllers** - Match folder/file names automatically
- ✅ **Menu Titles** - Generated from folder/file names
- ✅ **Tab Navigation** - Built automatically for folders

---

## 📋 Implementation Steps

### **Step 1: Directory Structure**

Create the base structure:

```
includes/Admin/
├── Core/
│   ├── Screen_Registry.php      # Auto-discovery engine
│   ├── Screen_Context.php       # $screen helper object
│   └── Base_Controller.php      # Optional controller parent
├── Screens/                      # Your admin pages (auto-discovered)
│   ├── dashboard.php            # Simple: File = Screen
│   ├── reports.php              # Simple: File = Screen
│   └── settings/                # Tabbed: Folder = Screen with tabs
│       ├── _config.php          # OPTIONAL override config
│       ├── general.php          # Tab 1 (auto-discovered)
│       ├── mailchimp.php        # Tab 2 (auto-discovered)
│       └── advanced.php         # Tab 3 (auto-discovered)
├── Controllers/                  # Optional business logic (auto-discovered)
│   ├── Dashboard_Controller.php # Auto-attached to dashboard.php
│   └── Settings_Controller.php  # Auto-attached to settings/
├── Models/                       # Optional data layer
│   └── Settings_Model.php
└── Admin.php                     # Bootstrap file
```

**Key Conventions:**
- **Single `.php` file** → Simple screen (no tabs)
- **Folder** → Tabbed screen (files inside = tabs)
- **Folder/file name** → Auto-generates menu title
  - `dashboard.php` → "Dashboard"
  - `email_templates.php` → "Email Templates"
  - `settings/` → "Settings"
- **Controller auto-discovery:**
  - `dashboard.php` → looks for `Dashboard_Controller.php`
  - `settings/` → looks for `Settings_Controller.php`

---

### **Step 2: Core Files - Screen_Context**

The `$screen` helper object available in all screen files.

**File:** `includes/Admin/Core/Screen_Context.php`

```php
<?php
namespace CampaignBridge\Admin\Core;

class Screen_Context {

    private string $screen_name;
    private string $screen_type;
    private ?string $current_tab;
    private $controller;
    private array $data = [];
    private array $messages = [];
    private array $errors = [];

    public function __construct(string $screen_name, string $screen_type, ?string $current_tab, $controller) {
        $this->screen_name = $screen_name;
        $this->screen_type = $screen_type;
        $this->current_tab = $current_tab;
        $this->controller = $controller;
    }

    // Data methods
    public function set(string $key, $value): void {
        $this->data[$key] = $value;
    }

    public function get(string $key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    public function get_all(): array {
        return $this->data;
    }

    // Form methods
    public function is_post(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public function nonce_field(string $action): void {
        wp_nonce_field('cb_' . $action, '_wpnonce', true, true);
    }

    public function verify_nonce(string $action): bool {
        return isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'cb_' . $action);
    }

    public function post(string $key, $default = null) {
        if (!isset($_POST[$key])) return $default;
        $value = $_POST[$key];
        if (is_string($value)) return sanitize_text_field($value);
        if (is_array($value)) return array_map('sanitize_text_field', $value);
        return $value;
    }

    // Asset methods
    public function enqueue_style(string $handle, string $src, array $deps = [], string $version = null): void {
        wp_enqueue_style(
            'cb-' . $handle,
            CAMPAIGNBRIDGE_PLUGIN_URL . $src,
            array_merge(['cb-admin-global'], $deps),
            $version ?? CAMPAIGNBRIDGE_VERSION
        );
    }

    public function enqueue_script(string $handle, string $src, array $deps = ['jquery'], string $version = null, bool $in_footer = true): void {
        wp_enqueue_script(
            'cb-' . $handle,
            CAMPAIGNBRIDGE_PLUGIN_URL . $src,
            $deps,
            $version ?? CAMPAIGNBRIDGE_VERSION,
            $in_footer
        );
    }

    public function localize_script(string $handle, string $object_name, array $data): void {
        wp_localize_script('cb-' . $handle, $object_name, $data);
    }

    // Message methods
    public function add_message(string $message): void {
        $this->messages[] = $message;
        add_action('admin_notices', [$this, 'display_messages']);
    }

    public function add_error(string $error): void {
        $this->errors[] = $error;
        add_action('admin_notices', [$this, 'display_messages']);
    }

    public function display_messages(): void {
        foreach ($this->messages as $message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        foreach ($this->errors as $error) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        }
    }

    // Info methods
    public function get_screen_info(): array {
        return [
            'name' => $this->screen_name,
            'type' => $this->screen_type,
            'current_tab' => $this->current_tab,
        ];
    }

    public function is_tab(string $tab_name): bool {
        return $this->current_tab === $tab_name;
    }

    public function get_controller() {
        return $this->controller;
    }
}
```

---

### **Step 3: Core Files - Screen_Registry (Auto-Discovery Engine)**

The heart of the system - discovers screens, tabs, and controllers automatically.

**File:** `includes/Admin/Core/Screen_Registry.php`

```php
<?php
namespace CampaignBridge\Admin\Core;

class Screen_Registry {

    private string $screens_path;
    private string $controllers_path;
    private string $parent_slug;
    private array $registered_screens = [];

    public function __construct(string $screens_path, string $parent_slug = 'campaignbridge') {
        $this->screens_path = trailingslashit($screens_path);
        $this->controllers_path = dirname($screens_path) . '/Controllers/';
        $this->parent_slug = $parent_slug;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'discover_and_register_screens'], 20);
    }

    /**
     * Scan Screens directory and auto-discover all screens
     */
    public function discover_and_register_screens(): void {
        if (!is_dir($this->screens_path)) return;

        foreach (scandir($this->screens_path) as $item) {
            // Skip special files
            if ($item === '.' || $item === '..' || strpos($item, '_') === 0 || strpos($item, '.') === 0) {
                continue;
            }

            $path = $this->screens_path . $item;

            if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                // Single file = Simple screen
                $this->register_simple_screen($item);
            } elseif (is_dir($path)) {
                // Folder = Tabbed screen
                $this->register_tabbed_screen($item);
            }
        }
    }

    /**
     * Register simple screen (single PHP file)
     */
    private function register_simple_screen(string $filename): void {
        $screen_name = pathinfo($filename, PATHINFO_FILENAME);
        $slug = $this->generate_slug($screen_name);

        // Load optional config (future: could support dashboard_config.php)
        $config = [];

        // Auto-discover controller
        if (!isset($config['controller'])) {
            $config['controller'] = $this->discover_controller($screen_name);
        }

        // Merge with defaults
        $config = array_merge([
            'menu_title' => $this->generate_title($screen_name),
            'page_title' => $this->generate_title($screen_name),
            'capability' => 'manage_options',
        ], $config);

        $this->register_screen($screen_name, $slug, $config, 'single');
    }

    /**
     * Register tabbed screen (folder-based)
     */
    private function register_tabbed_screen(string $folder_name): void {
        $slug = $this->generate_slug($folder_name);
        $folder_path = $this->screens_path . $folder_name;

        // Check for optional _config.php
        $config_file = $folder_path . '/_config.php';
        $config = file_exists($config_file) ? require $config_file : [];

        // Auto-discover controller if not specified
        if (!isset($config['controller'])) {
            $config['controller'] = $this->discover_controller($folder_name);
        }

        // Merge with defaults
        $config = array_merge([
            'menu_title' => $this->generate_title($folder_name),
            'page_title' => $this->generate_title($folder_name),
            'capability' => 'manage_options',
        ], $config);

        $this->register_screen($folder_name, $slug, $config, 'tabbed');
    }

    /**
     * Register screen with WordPress
     */
    private function register_screen(string $screen_name, string $slug, array $config, string $type): void {
        $full_slug = $this->parent_slug . '-' . $slug;

        // Initialize controller if found
        $controller = null;
        if ($config['controller'] && class_exists($config['controller'])) {
            $controller = new $config['controller']();
        }

        // Register WordPress submenu page
        $hook = add_submenu_page(
            $this->parent_slug,
            $config['page_title'],
            $config['menu_title'],
            $config['capability'],
            $full_slug,
            fn() => $this->render_screen($screen_name, $type, $controller, $config),
            $config['position'] ?? null
        );

        // Store for reference
        $this->registered_screens[$full_slug] = [
            'name' => $screen_name,
            'slug' => $full_slug,
            'type' => $type,
            'hook' => $hook,
            'config' => $config,
            'controller' => $controller,
        ];

        // Hook: on page load (for form handling via controller)
        add_action("load-{$hook}", function() use ($controller) {
            if ($controller && method_exists($controller, 'handle_request')) {
                $controller->handle_request();
            }
        });
    }

    /**
     * Render screen (simple or tabbed)
     */
    private function render_screen(string $screen_name, string $type, $controller, array $config): void {
        echo '<div class="wrap campaignbridge-screen">';
        echo '<h1>' . esc_html($config['page_title']) . '</h1>';

        if (!empty($config['description'])) {
            echo '<p class="description">' . esc_html($config['description']) . '</p>';
        }

        if ($type === 'single') {
            $this->render_simple_screen($screen_name, $controller);
        } else {
            $this->render_tabbed_screen($screen_name, $controller);
        }

        echo '</div>';
    }

    /**
     * Render simple screen (no tabs)
     */
    private function render_simple_screen(string $screen_name, $controller): void {
        $screen_file = $this->screens_path . $screen_name . '.php';

        if (!file_exists($screen_file)) {
            echo '<div class="notice notice-error"><p>Screen file not found: ' . esc_html($screen_name) . '.php</p></div>';
            return;
        }

        // Create $screen context
        $screen = new Screen_Context($screen_name, 'single', null, $controller);

        // Load data from controller
        if ($controller && method_exists($controller, 'get_data')) {
            foreach ($controller->get_data() as $key => $value) {
                $screen->set($key, $value);
            }
        }

        // Include screen file
        include $screen_file;
    }

    /**
     * Render tabbed screen
     */
    private function render_tabbed_screen(string $screen_name, $controller): void {
        $screen_folder = $this->screens_path . $screen_name;

        // Auto-discover tabs
        $tabs = $this->discover_tabs($screen_folder);

        if (empty($tabs)) {
            echo '<div class="notice notice-error"><p>No tabs found in: ' . esc_html($screen_name) . '/</p></div>';
            return;
        }

        // Get active tab
        $active_tab = $_GET['tab'] ?? array_key_first($tabs);

        // Validate tab exists
        if (!isset($tabs[$active_tab])) {
            $active_tab = array_key_first($tabs);
        }

        // Render tab navigation
        echo '<nav class="nav-tab-wrapper wp-clearfix">';
        foreach ($tabs as $tab_slug => $tab_info) {
            $active_class = $active_tab === $tab_slug ? ' nav-tab-active' : '';
            $url = add_query_arg('tab', $tab_slug);

            printf(
                '<a href="%s" class="nav-tab%s">%s</a>',
                esc_url($url),
                esc_attr($active_class),
                esc_html($tab_info['title'])
            );
        }
        echo '</nav>';

        // Render active tab content
        if (isset($tabs[$active_tab])) {
            echo '<div class="tab-content">';

            // Create $screen context for tab
            $screen = new Screen_Context($screen_name, 'tabbed', $active_tab, $controller);

            // Load data from controller
            if ($controller && method_exists($controller, 'get_data')) {
                foreach ($controller->get_data() as $key => $value) {
                    $screen->set($key, $value);
                }
            }

            // Include tab file
            include $tabs[$active_tab]['file'];

            echo '</div>';
        }
    }

    /**
     * Auto-discover tabs from folder
     */
    private function discover_tabs(string $folder_path): array {
        $tabs = [];

        if (!is_dir($folder_path)) {
            return $tabs;
        }

        foreach (glob($folder_path . '/*.php') as $file) {
            $filename = basename($file);

            // Skip files starting with _ (like _config.php)
            if (strpos($filename, '_') === 0) {
                continue;
            }

            $tab_name = pathinfo($filename, PATHINFO_FILENAME);

            $tabs[$tab_name] = [
                'name' => $tab_name,
                'title' => $this->generate_title($tab_name),
                'slug' => $this->generate_slug($tab_name),
                'file' => $file,
            ];
        }

        return $tabs;
    }

    /**
     * Auto-discover controller by convention
     *
     * Examples:
     * - dashboard.php → Dashboard_Controller
     * - settings/ → Settings_Controller
     * - email_templates/ → Email_Templates_Controller
     */
    private function discover_controller(string $name): ?string {
        $controller_class = $this->name_to_controller_class($name);

        if (class_exists($controller_class)) {
            return $controller_class;
        }

        return null;
    }

    /**
     * Convert name to controller class name
     */
    private function name_to_controller_class(string $name): string {
        // Replace hyphens and underscores with spaces
        $class_name = str_replace(['-', '_'], ' ', $name);

        // Capitalize each word
        $class_name = ucwords($class_name);

        // Remove spaces
        $class_name = str_replace(' ', '_', $class_name);

        // Add Controller suffix and namespace
        return "CampaignBridge\\Admin\\Controllers\\{$class_name}_Controller";
    }

    /**
     * Generate slug from name
     */
    private function generate_slug(string $name): string {
        return strtolower(str_replace(['_', ' '], '-', $name));
    }

    /**
     * Generate title from name
     */
    private function generate_title(string $name): string {
        return ucwords(str_replace(['_', '-'], ' ', $name));
    }
}
```

---

### **Step 4: Bootstrap File**

**File:** `includes/Admin/Admin.php`

```php
<?php
namespace CampaignBridge\Admin;

use CampaignBridge\Admin\Core\Screen_Registry;

class Admin {

    private Screen_Registry $screen_registry;
    private static ?Admin $instance = null;

    public static function get_instance(): Admin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!is_admin()) {
            return;
        }

        $this->init();
    }

    private function init(): void {
        // Initialize screen registry
        $screens_path = dirname(__FILE__) . '/Screens';
        $this->screen_registry = new Screen_Registry($screens_path, 'campaignbridge');
        $this->screen_registry->init();

        // Add parent menu
        add_action('admin_menu', [$this, 'add_parent_menu'], 9);
    }

    public function add_parent_menu(): void {
        add_menu_page(
            __('CampaignBridge', 'campaignbridge'),
            __('CampaignBridge', 'campaignbridge'),
            'manage_options',
            'campaignbridge',
            null,
            'dashicons-email-alt',
            30
        );
    }
}

// Initialize
Admin::get_instance();
```

---

### **Step 5: Create Test Screens**

#### Test 1: Simple Screen (Dashboard)

**File:** `includes/Admin/Screens/dashboard.php`

```php
<?php
/**
 * Dashboard Screen
 *
 * This is a simple screen with no tabs.
 * Controller auto-discovered: Dashboard_Controller (if exists)
 */

// Get data from controller or set defaults
$stats = $screen->get('stats', [
    'total_campaigns' => 0,
    'total_sent' => 0,
    'open_rate' => '0%',
]);
?>

<div class="dashboard-screen">
    <h2>Welcome to Campaign Bridge</h2>
    <p>This dashboard was auto-discovered from dashboard.php</p>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Campaigns</h3>
            <p class="stat-number"><?php echo number_format($stats['total_campaigns']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Emails Sent</h3>
            <p class="stat-number"><?php echo number_format($stats['total_sent']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Open Rate</h3>
            <p class="stat-number"><?php echo esc_html($stats['open_rate']); ?></p>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 20px;
}
.stat-card {
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: center;
}
.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}
</style>
```

#### Test 2: Tabbed Screen (Settings) - Zero Config

**Folder:** `includes/Admin/Screens/settings/`

**File:** `includes/Admin/Screens/settings/general.php`

```php
<?php
/**
 * General Settings Tab
 *
 * Auto-discovered as part of Settings screen
 * Controller: Settings_Controller (auto-discovered)
 */

$from_name = get_option('cb_from_name', get_bloginfo('name'));
$from_email = get_option('cb_from_email', get_option('admin_email'));

// Handle form submission
if ($screen->is_post() && $screen->verify_nonce('save_general')) {
    $from_name = $screen->post('from_name');
    $from_email = $screen->post('from_email');

    // Validate
    if (empty($from_name)) {
        $screen->add_error('From Name is required');
    } elseif (!is_email($from_email)) {
        $screen->add_error('Valid email required');
    } else {
        update_option('cb_from_name', $from_name);
        update_option('cb_from_email', $from_email);
        $screen->add_message('General settings saved!');
    }
}
?>

<h2>General Settings</h2>

<form method="post">
    <?php $screen->nonce_field('save_general'); ?>

    <table class="form-table">
        <tr>
            <th><label for="from_name">From Name</label></th>
            <td>
                <input type="text" id="from_name" name="from_name"
                       value="<?php echo esc_attr($from_name); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="from_email">From Email</label></th>
            <td>
                <input type="email" id="from_email" name="from_email"
                       value="<?php echo esc_attr($from_email); ?>" class="regular-text">
            </td>
        </tr>
    </table>

    <?php submit_button('Save General Settings'); ?>
</form>
```

**File:** `includes/Admin/Screens/settings/mailchimp.php`

```php
<?php
/**
 * Mailchimp Settings Tab
 */

$api_key = get_option('cb_mailchimp_api_key', '');

if ($screen->is_post() && $screen->verify_nonce('save_mailchimp')) {
    $api_key = $screen->post('api_key');
    update_option('cb_mailchimp_api_key', $api_key);
    $screen->add_message('Mailchimp settings saved!');
}
?>

<h2>Mailchimp Integration</h2>

<form method="post">
    <?php $screen->nonce_field('save_mailchimp'); ?>

    <table class="form-table">
        <tr>
            <th><label for="api_key">API Key</label></th>
            <td>
                <input type="text" id="api_key" name="api_key"
                       value="<?php echo esc_attr($api_key); ?>" class="regular-text">
            </td>
        </tr>
    </table>

    <?php submit_button('Save Mailchimp Settings'); ?>
</form>
```

**File:** `includes/Admin/Screens/settings/advanced.php`

```php
<?php
/**
 * Advanced Settings Tab
 */

$debug_mode = get_option('cb_debug_mode', false);

if ($screen->is_post() && $screen->verify_nonce('save_advanced')) {
    $debug_mode = isset($_POST['debug_mode']);
    update_option('cb_debug_mode', $debug_mode);
    $screen->add_message('Advanced settings saved!');
}
?>

<h2>Advanced Settings</h2>

<form method="post">
    <?php $screen->nonce_field('save_advanced'); ?>

    <table class="form-table">
        <tr>
            <th>Debug Mode</th>
            <td>
                <label>
                    <input type="checkbox" name="debug_mode" value="1"
                           <?php checked($debug_mode, true); ?>>
                    Enable debug logging
                </label>
            </td>
        </tr>
    </table>

    <?php submit_button('Save Advanced Settings'); ?>
</form>
```

---

### **Step 6: Optional - Add _config.php Override**

If you need custom settings, create `_config.php`:

**File:** `includes/Admin/Screens/settings/_config.php`

```php
<?php
/**
 * Settings Configuration (OPTIONAL)
 *
 * Only create this file if you need to override defaults
 */

return [
    // Override menu title
    'menu_title' => __('CB Settings', 'campaignbridge'),

    // Custom page title
    'page_title' => __('CampaignBridge Settings', 'campaignbridge'),

    // Different capability
    'capability' => 'manage_options',

    // Custom menu position
    'position' => 58,

    // Add description
    'description' => __('Configure your email campaign settings and integrations.', 'campaignbridge'),

    // Override auto-discovered controller
    // 'controller' => Custom_Settings_Controller::class,
];
```

---

### **Step 7: Optional - Create Controller**

**File:** `includes/Admin/Controllers/Settings_Controller.php`

```php
<?php
namespace CampaignBridge\Admin\Controllers;

class Settings_Controller {

    private array $data = [];

    public function __construct() {
        // Initialize - load data needed by views
        $this->data = [
            'from_name' => get_option('cb_from_name', get_bloginfo('name')),
            'from_email' => get_option('cb_from_email', get_option('admin_email')),
            'mailchimp_api_key' => get_option('cb_mailchimp_api_key', ''),
            'debug_mode' => get_option('cb_debug_mode', false),
        ];
    }

    /**
     * Get data for views
     */
    public function get_data(): array {
        return $this->data;
    }

    /**
     * Handle form submissions (called before render)
     */
    public function handle_request(): void {
        // Controller can intercept POST requests here
        // For now, forms handle themselves in tab files
    }
}
```

**Note:** Controller auto-attaches because filename matches: `Settings_Controller.php` matches `settings/` folder.

---

## ✅ Testing Checklist

### Phase 1: Installation
- [ ] Copy all files to correct locations
- [ ] Activate plugin in WordPress
- [ ] Check for PHP errors in debug.log
- [ ] Verify no fatal errors

### Phase 2: Simple Screen Test
- [ ] Navigate to WordPress admin
- [ ] Look for "Dashboard" in CampaignBridge menu
- [ ] Click Dashboard
- [ ] Verify page loads with stats cards
- [ ] Check browser console for errors
- [ ] **Expected:** Dashboard screen displays with 3 stat cards

### Phase 3: Tabbed Screen Test (Zero Config)
- [ ] Look for "Settings" in CampaignBridge menu
- [ ] Click Settings
- [ ] Verify 3 tabs appear: General, Mailchimp, Advanced
- [ ] Click each tab - verify switching works
- [ ] Check URL changes: `?tab=general`, `?tab=mailchimp`, `?tab=advanced`
- [ ] Verify correct content shows for each tab
- [ ] **Expected:** Settings page with working tab navigation

### Phase 4: Form Functionality
- [ ] Go to Settings → General tab
- [ ] Enter "Test Name" in From Name
- [ ] Enter "test@example.com" in From Email
- [ ] Click "Save General Settings"
- [ ] Verify success message appears
- [ ] Refresh page - verify data persists
- [ ] Repeat for Mailchimp tab
- [ ] Repeat for Advanced tab
- [ ] **Expected:** All forms save and reload data correctly

### Phase 5: Auto-Discovery Verification
- [ ] No `_config.php` exists yet - still works ✅
- [ ] Menu title auto-generated: "Settings" from folder name ✅
- [ ] Tab titles auto-generated: "General", "Mailchimp", "Advanced" ✅
- [ ] Controller NOT required - forms work inline ✅

### Phase 6: Optional Config Test
- [ ] Create `_config.php` in settings folder
- [ ] Set `menu_title` to "CB Settings"
- [ ] Reload admin
- [ ] Verify menu now shows "CB Settings"
- [ ] Delete `_config.php`
- [ ] Verify menu reverts to "Settings"
- [ ] **Expected:** Config overrides work, removal reverts to defaults

### Phase 7: Controller Auto-Discovery Test
- [ ] Create `Settings_Controller.php`
- [ ] Add data loading in constructor
- [ ] Reload settings page
- [ ] Verify controller data available in tabs
- [ ] Delete controller file
- [ ] Verify still works without controller
- [ ] **Expected:** Controller auto-attaches when present, works without it

---

## 🐛 Troubleshooting

### "Menu doesn't appear"
- Check file permissions on Screens directory
- Verify PHP files are named correctly (.php extension)
- Check for PHP syntax errors in files
- Enable WP_DEBUG and check debug.log

### "Tabs don't show"
- Verify folder structure: folder must contain .php files
- Check tab files don't start with _ (underscore)
- Verify files are actually PHP files (