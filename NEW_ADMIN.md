# Fresh File-Based Admin System Setup

> **Clean Slate Approach**: Preserve old system, build new system from scratch

---

## 🎯 Strategy

1. **Rename** `includes/Admin/` → `includes/Admin_Legacy/` (preserve old system)
2. **Create** brand new `includes/Admin/` (new file-based system)
3. **Build** new system completely independent
4. **Test** new system thoroughly
5. **Migrate** old pages one-by-one later (separate guide)
6. **Delete** `Admin_Legacy/` when done

**Benefits**:
- ✅ Old system keeps working during development
- ✅ Can test new system alongside old
- ✅ Easy rollback if needed (just rename back)
- ✅ No confusion between old and new
- ✅ Clean slate - no legacy code to work around

---

## 📋 Step-by-Step Implementation

### Step 1: Preserve Old System

**Rename the current Admin directory:**

```bash
# From plugin root
mv includes/Admin includes/Admin_Legacy
```

**Result**:
```
includes/
├── Admin_Legacy/              # Old system (still works)
│   ├── Admin.php
│   ├── Pages/
│   └── ...
└── (Admin/ will be created fresh)
```

**Important**: Your plugin will temporarily break until we create the new `Admin/` directory in next steps.

---

### Step 2: Update Plugin Bootstrap (Temporarily)

**File**: Main plugin file (e.g., `campaignbridge.php`)

**Find this line**:
```php
require_once CAMPAIGNBRIDGE_PLUGIN_DIR . 'includes/Admin/Admin.php';
```

**Replace with**:
```php
// TEMPORARY: Using legacy admin during transition
require_once CAMPAIGNBRIDGE_PLUGIN_DIR . 'includes/Admin_Legacy/Admin.php';
```

**Test**: Your plugin should work exactly as before using the legacy system.

---

### Step 3: Create Fresh Admin Directory Structure

```bash
mkdir -p includes/Admin/Core
mkdir -p includes/Admin/Screens
mkdir -p includes/Admin/Controllers
mkdir -p includes/Admin/Models
```

**Result**:
```
includes/
├── Admin/                     # NEW - Fresh system
│   ├── Core/                 # NEW - System core files
│   ├── Screens/              # NEW - Your admin pages go here
│   ├── Controllers/          # NEW - Business logic (optional)
│   └── Models/               # NEW - Data layer (optional)
└── Admin_Legacy/              # OLD - Preserved for reference
    └── ...
```

---

### Step 4: Create Core System Files

#### File 1: Screen_Context.php

**Location**: `includes/Admin/Core/Screen_Context.php`

**Full file**:

```php
<?php
/**
 * Screen Context - Available in all screen files as $screen
 *
 * @package CampaignBridge\Admin\Core
 */

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

    public function has(string $key): bool {
        return isset($this->data[$key]);
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

    // Traditional asset methods
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

    // Built asset methods (.asset.php support)
    public function asset_enqueue_style(string $handle, string $asset_file_path, array $additional_deps = []): bool {
        $asset_file = CAMPAIGNBRIDGE_PLUGIN_DIR . $asset_file_path;
        if (!file_exists($asset_file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("CampaignBridge: Asset file not found: {$asset_file}");
            }
            return false;
        }

        $asset = require $asset_file;
        $css_file = str_replace('.asset.php', '.css', $asset_file_path);

        if (!file_exists(CAMPAIGNBRIDGE_PLUGIN_DIR . $css_file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("CampaignBridge: CSS file not found: {$css_file}");
            }
            return false;
        }

        wp_enqueue_style(
            'cb-' . $handle,
            CAMPAIGNBRIDGE_PLUGIN_URL . $css_file,
            array_merge(['cb-admin-global'], $asset['dependencies'] ?? [], $additional_deps),
            $asset['version'] ?? CAMPAIGNBRIDGE_VERSION
        );

        return true;
    }

    public function asset_enqueue_script(string $handle, string $asset_file_path, array $additional_deps = [], bool $in_footer = true): bool {
        $asset_file = CAMPAIGNBRIDGE_PLUGIN_DIR . $asset_file_path;
        if (!file_exists($asset_file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("CampaignBridge: Asset file not found: {$asset_file}");
            }
            return false;
        }

        $asset = require $asset_file;
        $js_file = str_replace('.asset.php', '.js', $asset_file_path);

        if (!file_exists(CAMPAIGNBRIDGE_PLUGIN_DIR . $js_file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("CampaignBridge: JS file not found: {$js_file}");
            }
            return false;
        }

        wp_enqueue_script(
            'cb-' . $handle,
            CAMPAIGNBRIDGE_PLUGIN_URL . $js_file,
            array_merge($asset['dependencies'] ?? [], $additional_deps),
            $asset['version'] ?? CAMPAIGNBRIDGE_VERSION,
            $in_footer
        );

        return true;
    }

    public function asset_enqueue(string $handle, string $asset_file_path, bool $enqueue_style = true, bool $enqueue_script = true): array {
        return [
            'style' => $enqueue_style ? $this->asset_enqueue_style($handle, $asset_file_path) : false,
            'script' => $enqueue_script ? $this->asset_enqueue_script($handle, $asset_file_path) : false,
        ];
    }

    // Utility methods
    public function localize_script(string $handle, string $object_name, array $data): void {
        wp_localize_script('cb-' . $handle, $object_name, $data);
    }

    public function add_message(string $message): void {
        $this->messages[] = $message;
        add_action('admin_notices', [$this, 'display_messages']);
    }

    public function add_error(string $error): void {
        $this->errors[] = $error;
        add_action('admin_notices', [$this, 'display_messages']);
    }

    public function add_warning(string $warning): void {
        add_action('admin_notices', fn() => printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($warning)));
    }

    public function add_info(string $info): void {
        add_action('admin_notices', fn() => printf('<div class="notice notice-info"><p>%s</p></div>', esc_html($info)));
    }

    public function display_messages(): void {
        foreach ($this->messages as $message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        foreach ($this->errors as $error) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        }
    }

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

    public function get_tab_url(string $tab_name): string {
        return add_query_arg('tab', $tab_name);
    }

    public function get_controller() {
        return $this->controller;
    }
}
```

#### File 2: Screen_Registry.php

**Location**: `includes/Admin/Core/Screen_Registry.php`

**Full file**:

```php
<?php
/**
 * Screen Registry - Auto-discovers and registers screens
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

class Screen_Registry {

    private string $screens_path;
    private string $parent_slug;
    private array $registered_screens = [];

    public function __construct(string $screens_path, string $parent_slug = 'campaignbridge') {
        $this->screens_path = trailingslashit($screens_path);
        $this->parent_slug = $parent_slug;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'discover_and_register_screens'], 20);
    }

    public function discover_and_register_screens(): void {
        if (!is_dir($this->screens_path)) return;

        foreach (scandir($this->screens_path) as $item) {
            // Skip special files
            if ($item === '.' || $item === '..' || strpos($item, '_') === 0 || strpos($item, '.') === 0) {
                continue;
            }

            $path = $this->screens_path . $item;

            // File = single screen, Directory = tabbed screen
            if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $this->register_single_screen($item);
            } elseif (is_dir($path)) {
                $this->register_tabbed_screen($item);
            }
        }
    }

    private function register_single_screen(string $filename): void {
        $screen_name = pathinfo($filename, PATHINFO_FILENAME);
        $slug = $this->generate_slug($screen_name);
        $title = $this->generate_title($screen_name);

        $this->register_screen($screen_name, $slug, [
            'menu_title' => $title,
            'page_title' => $title,
            'capability' => 'manage_options',
        ], 'single');
    }

    private function register_tabbed_screen(string $folder_name): void {
        $screen_name = $folder_name;
        $slug = $this->generate_slug($screen_name);
        $title = $this->generate_title($screen_name);

        // Check for optional _config.php
        $config_file = $this->screens_path . $folder_name . '/_config.php';
        $config = file_exists($config_file) ? require $config_file : [];

        // Merge with defaults
        $config = array_merge([
            'menu_title' => $title,
            'page_title' => $title,
            'capability' => 'manage_options',
        ], $config);

        $this->register_screen($screen_name, $slug, $config, 'tabbed');
    }

    private function register_screen(string $screen_name, string $slug, array $config, string $type): void {
        $full_slug = $this->parent_slug . '-' . $slug;

        // Initialize controller if specified
        $controller = null;
        if (isset($config['controller']) && class_exists($config['controller'])) {
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

        // Hook: on page load (for form handling)
        add_action("load-{$hook}", function() use ($controller) {
            if ($controller && method_exists($controller, 'handle_request')) {
                $controller->handle_request();
            }
        });

        // Hook: enqueue assets
        add_action('admin_enqueue_scripts', function($hook_suffix) use ($hook, $screen_name, $type, $config) {
            if ($hook_suffix === $hook) {
                $this->enqueue_screen_assets($screen_name, $type, $config);
            }
        });
    }

    private function render_screen(string $screen_name, string $type, $controller, array $config): void {
        echo '<div class="wrap campaignbridge-screen">';
        echo '<h1>' . esc_html($config['page_title']) . '</h1>';

        if (!empty($config['description'])) {
            echo '<p class="description">' . esc_html($config['description']) . '</p>';
        }

        if ($type === 'single') {
            $this->render_single_screen($screen_name, $controller);
        } else {
            $this->render_tabbed_screen($screen_name, $controller);
        }

        echo '</div>';
    }

    private function render_single_screen(string $screen_name, $controller): void {
        $screen_file = $this->screens_path . $screen_name . '.php';

        if (!file_exists($screen_file)) {
            echo '<div class="notice notice-error"><p>Screen file not found: ' . esc_html($screen_name) . '.php</p></div>';
            return;
        }

        // Create context
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

    private function render_tabbed_screen(string $screen_name, $controller): void {
        $screen_folder = $this->screens_path . $screen_name;
        $tabs = [];

        // Discover tabs
        foreach (glob($screen_folder . '/*.php') as $file) {
            $filename = basename($file);

            // Skip special files (starting with _)
            if (strpos($filename, '_') === 0) continue;

            $tab_name = pathinfo($filename, PATHINFO_FILENAME);
            $tabs[$tab_name] = [
                'name' => $tab_name,
                'title' => $this->generate_title($tab_name),
                'slug' => $this->generate_slug($tab_name),
                'file' => $file,
            ];
        }

        if (empty($tabs)) {
            echo '<div class="notice notice-error"><p>No tabs found in: ' . esc_html($screen_name) . '/</p></div>';
            return;
        }

        // Get active tab
        $active_tab = $_GET['tab'] ?? array_key_first($tabs);

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

            // Create context for tab
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

    private function enqueue_screen_assets(string $screen_name, string $type, array $config): void {
        $screen = new Screen_Context($screen_name, $type, $_GET['tab'] ?? null, null);

        // Traditional assets
        if (isset($config['assets']['styles'])) {
            foreach ($config['assets']['styles'] as $handle => $src) {
                $screen->enqueue_style($handle, $src);
            }
        }

        if (isset($config['assets']['scripts'])) {
            foreach ($config['assets']['scripts'] as $handle => $script) {
                $src = is_array($script) ? $script['src'] : $script;
                $deps = is_array($script) && isset($script['deps']) ? $script['deps'] : ['jquery'];
                $screen->enqueue_script($handle, $src, $deps);
            }
        }

        // Built assets
        if (isset($config['assets']['asset_styles'])) {
            foreach ($config['assets']['asset_styles'] as $handle => $asset_file) {
                $screen->asset_enqueue_style($handle, $asset_file);
            }
        }

        if (isset($config['assets']['asset_scripts'])) {
            foreach ($config['assets']['asset_scripts'] as $handle => $asset_data) {
                if (is_string($asset_data)) {
                    $screen->asset_enqueue_script($handle, $asset_data);
                } elseif (is_array($asset_data)) {
                    $asset_file = $asset_data['src'] ?? $asset_data['path'] ?? '';
                    if ($asset_file) {
                        $screen->asset_enqueue_script(
                            $handle,
                            $asset_file,
                            $asset_data['deps'] ?? [],
                            $asset_data['in_footer'] ?? true
                        );
                    }
                }
            }
        }

        if (isset($config['assets']['asset_both'])) {
            foreach ($config['assets']['asset_both'] as $handle => $asset_file) {
                $screen->asset_enqueue($handle, $asset_file);
            }
        }
    }

    private function generate_slug(string $name): string {
        return strtolower(str_replace(['_', ' '], '-', $name));
    }

    private function generate_title(string $name): string {
        return ucwords(str_replace(['_', '-'], ' ', $name));
    }
}
```

#### File 3: Admin.php Bootstrap

**Location**: `includes/Admin/Admin.php`

**Full file**:

```php
<?php
/**
 * Admin Bootstrap - NEW System
 *
 * @package CampaignBridge\Admin
 */

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

        // Enqueue global assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_global_assets']);
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

    public function enqueue_global_assets(string $hook): void {
        // Only on CampaignBridge pages
        if (strpos($hook, 'campaignbridge') === false) {
            return;
        }

        // Global admin CSS
        wp_enqueue_style(
            'cb-admin-global',
            CAMPAIGNBRIDGE_PLUGIN_URL . 'assets/css/admin/global.css',
            [],
            CAMPAIGNBRIDGE_VERSION
        );

        // Global admin JS
        wp_enqueue_script(
            'cb-admin-global',
            CAMPAIGNBRIDGE_PLUGIN_URL . 'assets/js/admin/global.js',
            ['jquery'],
            CAMPAIGNBRIDGE_VERSION,
            true
        );

        // Localize global data
        wp_localize_script('cb-admin-global', 'campaignBridge', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('campaignbridge/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => CAMPAIGNBRIDGE_PLUGIN_URL,
        ]);
    }
}

// Initialize
Admin::get_instance();
```

---

### Step 5: Switch to New System

**File**: Main plugin file (e.g., `campaignbridge.php`)

**Change from**:
```php
require_once CAMPAIGNBRIDGE_PLUGIN_DIR . 'includes/Admin_Legacy/Admin.php';
```

**To**:
```php
require_once CAMPAIGNBRIDGE_PLUGIN_DIR . 'includes/Admin/Admin.php';
```

---

### Step 6: Create Test Screen

**File**: `includes/Admin/Screens/test.php`

```php
<?php
/**
 * Test Screen - Verify new system works
 */
?>

<div class="test-screen" style="padding: 20px; background: white; border: 1px solid #ddd; margin-top: 20px;">
    <h2><?php _e('✅ New System Working!', 'campaignbridge'); ?></h2>
    <p><?php _e('If you can see this, the file-based routing system is working correctly.', 'campaignbridge'); ?></p>

    <div class="notice notice-success inline">
        <p><strong>Success!</strong> Screen auto-discovered from: <code>includes/Admin/Screens/test.php</code></p>
    </div>

    <h3>System Info:</h3>
    <ul>
        <li><strong>Screen Name:</strong> <?php echo esc_html($screen->get_screen_info()['name']); ?></li>
        <li><strong>Screen Type:</strong> <?php echo esc_html($screen->get_screen_info()['type']); ?></li>
        <li><strong>Context Available:</strong> ✅ $screen variable</li>
    </ul>
</div>
```

---

### Step 7: Test Everything

1. **Clear WordPress cache** (if using caching plugin)
2. **Reload WP admin**
3. **Look for "Test" menu** under Campaign

# Part 2: Creating Screens

> **File 2 of 5**: How to create simple screens and tabbed screens

---

## 🎯 What This Part Covers

- File naming conventions
- Creating simple screens (no tabs)
- Creating tabbed screens
- Using the `$screen` context object
- Handling forms and data
- Examples for common use cases

---

## 📝 File Naming Conventions

### Rules

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

---

## 🔸 Simple Screens (No Tabs)

### Example 1: Dashboard Screen

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
            <p class="stat-number"><?php echo number_format($stats['total_campaigns']); ?></p>
        </div>

        <div class="stat-card">
            <h3><?php _e('Emails Sent', 'campaignbridge'); ?></h3>
            <p class="stat-number"><?php echo number_format($stats['total_sent']); ?></p>
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
                            <td><?php echo esc_html($campaign['opens']); ?></td>
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

---

### Example 2: Reports Screen with Form

**File**: `includes/Admin/Screens/reports.php`

```php
<?php
/**
 * Reports Screen
 */

// Default date range
$date_from = $screen->post('date_from', date('Y-m-d', strtotime('-30 days')));
$date_to = $screen->post('date_to', date('Y-m-d'));

// Get reports data
$reports = $screen->get('reports', []);
?>

<div class="reports-screen">

    <!-- Date Filter Form -->
    <div class="report-filters">
        <form method="post">
            <?php $screen->nonce_field('filter_reports'); ?>

            <label for="date_from"><?php _e('From:', 'campaignbridge'); ?></label>
            <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>">

            <label for="date_to"><?php _e('To:', 'campaignbridge'); ?></label>
            <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>">

            <?php submit_button(__('Filter', 'campaignbridge'), 'secondary', 'submit', false); ?>
        </form>
    </div>

    <!-- Reports Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Campaign', 'campaignbridge'); ?></th>
                <th><?php _e('Date', 'campaignbridge'); ?></th>
                <th><?php _e('Recipients', 'campaignbridge'); ?></th>
                <th><?php _e('Opens', 'campaignbridge'); ?></th>
                <th><?php _e('Clicks', 'campaignbridge'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo esc_html($report['campaign_name']); ?></td>
                    <td><?php echo esc_html($report['date']); ?></td>
                    <td><?php echo number_format($report['recipients']); ?></td>
                    <td><?php echo number_format($report['opens']); ?> (<?php echo esc_html($report['open_rate']); ?>)</td>
                    <td><?php echo number_format($report['clicks']); ?> (<?php echo esc_html($report['click_rate']); ?>)</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

<style>
    .report-filters {
        background: white;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid #ddd;
    }

    .report-filters form {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .report-filters label {
        margin: 0;
    }
</style>

<?php
$screen->enqueue_style('reports-screen', 'assets/css/admin/screens/reports.css');
?>
```

**Result**: Creates "Reports" menu with date filtering.

---

## 🔷 Tabbed Screens

### Step 1: Create Folder and Config

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

### Step 2: Create Tabs

#### Tab 1: General Settings

**File**: `includes/Admin/Screens/settings/general.php`

```php
<?php
/**
 * General Settings Tab
 */

// Get data from controller or options
$from_name = $screen->get('from_name', get_option('cb_from_name', get_bloginfo('name')));
$from_email = $screen->get('from_email', get_option('cb_from_email', get_option('admin_email')));
$reply_to = $screen->get('reply_to', get_option('cb_reply_to', ''));

// Handle form submission
if ($screen->is_post() && $screen->verify_nonce('save_general_settings')) {
    $from_name = $screen->post('from_name');
    $from_email = $screen->post('from_email');
    $reply_to = $screen->post('reply_to');

    // Validate
    $errors = [];
    if (empty($from_name)) {
        $errors[] = __('From Name is required', 'campaignbridge');
    }
    if (!is_email($from_email)) {
        $errors[] = __('Valid From Email is required', 'campaignbridge');
    }
    if (!empty($reply_to) && !is_email($reply_to)) {
        $errors[] = __('Reply-To must be a valid email', 'campaignbridge');
    }

    if (empty($errors)) {
        // Save
        update_option('cb_from_name', $from_name);
        update_option('cb_from_email', $from_email);
        update_option('cb_reply_to', $reply_to);

        $screen->add_message(__('General settings saved successfully!', 'campaignbridge'));
    } else {
        foreach ($errors as $error) {
            $screen->add_error($error);
        }
    }
}
?>

<div class="general-settings-tab">

    <h2><?php _e('General Email Settings', 'campaignbridge'); ?></h2>
    <p class="description">
        <?php _e('Configure the default sender information for your email campaigns.', 'campaignbridge'); ?>
    </p>

    <form method="post" action="">
        <?php $screen->nonce_field('save_general_settings'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="from_name">
                            <?php _e('From Name', 'campaignbridge'); ?>
                            <span class="required">*</span>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="from_name"
                            name="from_name"
                            value="<?php echo esc_attr($from_name); ?>"
                            class="regular-text"
                            required
                        >
                        <p class="description">
                            <?php _e('The name that appears in the "From" field of emails.', 'campaignbridge'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="from_email">
                            <?php _e('From Email', 'campaignbridge'); ?>
                            <span class="required">*</span>
                        </label>
                    </th>
                    <td>
                        <input
                            type="email"
                            id="from_email"
                            name="from_email"
                            value="<?php echo esc_attr($from_email); ?>"
                            class="regular-text"
                            required
                        >
                        <p class="description">
                            <?php _e('The email address that appears in the "From" field.', 'campaignbridge'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="reply_to">
                            <?php _e('Reply-To Email', 'campaignbridge'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="email"
                            id="reply_to"
                            name="reply_to"
                            value="<?php echo esc_attr($reply_to); ?>"
                            class="regular-text"
                        >
                        <p class="description">
                            <?php _e('Optional. Email address where replies should be sent.', 'campaignbridge'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save General Settings', 'campaignbridge')); ?>
    </form>

</div>

<style>
    .general-settings-tab {
        background: white;
        padding: 20px;
        margin-top: 20px;
        border: 1px solid #ddd;
    }

    .required {
        color: #d63638;
        font-weight: bold;
    }
</style>

<?php
// Tab-specific assets (only loads when this tab is active)
$screen->enqueue_style('general-tab', 'assets/css/admin/screens/settings/general.css');
?>
```

#### Tab 2: Mailchimp Integration

**File**: `includes/Admin/Screens/settings/mailchimp.php`

```php
<?php
/**
 * Mailchimp Settings Tab
 */

$api_key = $screen->get('mailchimp_api_key', get_option('cb_mailchimp_api_key', ''));
$is_connected = $screen->get('mailchimp_connected', false);
$audiences = $screen->get('mailchimp_audiences', []);
$selected_audience = $screen->get('mailchimp_selected_audience', get_option('cb_mailchimp_audience', ''));

// Handle form submission
if ($screen->is_post() && $screen->verify_nonce('save_mailchimp_settings')) {
    $api_key = $screen->post('mailchimp_api_key');
    $selected_audience = $screen->post('mailchimp_audience');

    if (!empty($api_key)) {
        update_option('cb_mailchimp_api_key', $api_key);
        update_option('cb_mailchimp_audience', $selected_audience);

        $screen->add_message(__('Mailchimp settings saved!', 'campaignbridge'));
    } else {
        $screen->add_error(__('API Key is required', 'campaignbridge'));
    }
}

// Handle disconnect
if (isset($_GET['action']) && $_GET['action'] === 'disconnect' && $screen->verify_nonce('disconnect_mailchimp')) {
    delete_option('cb_mailchimp_api_key');
    delete_option('cb_mailchimp_audience');
    $screen->add_message(__('Mailchimp disconnected', 'campaignbridge'));
    $api_key = '';
    $is_connected = false;
}
?>

<div class="mailchimp-tab">

    <?php if ($is_connected): ?>
        <div class="notice notice-success inline">
            <p>
                <span class="dashicons dashicons-yes-alt"></span>
                <strong><?php _e('Successfully connected to Mailchimp!', 'campaignbridge'); ?></strong>
            </p>
        </div>
    <?php else: ?>
        <div class="notice notice-warning inline">
            <p>
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Not connected to Mailchimp. Please enter your API key below.', 'campaignbridge'); ?>
            </p>
        </div>
    <?php endif; ?>

    <h2><?php _e('Mailchimp Integration', 'campaignbridge'); ?></h2>

    <form method="post" action="">
        <?php $screen->nonce_field('save_mailchimp_settings'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="mailchimp_api_key">
                            <?php _e('API Key', 'campaignbridge'); ?>
                            <span class="required">*</span>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="mailchimp_api_key"
                            name="mailchimp_api_key"
                            value="<?php echo esc_attr($api_key); ?>"
                            class="regular-text"
                            placeholder="<?php esc_attr_e('Enter your Mailchimp API key', 'campaignbridge'); ?>"
                        >
                        <p class="description">
                            <?php
                            printf(
                                __('Get your API key from <a href="%s" target="_blank">Mailchimp Account Settings</a>', 'campaignbridge'),
                                'https://admin.mailchimp.com/account/api/'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <?php if ($is_connected && !empty($audiences)): ?>
                    <tr>
                        <th scope="row">
                            <label for="mailchimp_audience">
                                <?php _e('Default Audience', 'campaignbridge'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="mailchimp_audience" name="mailchimp_audience" class="regular-text">
                                <option value=""><?php _e('Select an audience', 'campaignbridge'); ?></option>
                                <?php foreach ($audiences as $audience): ?>
                                    <option
                                        value="<?php echo esc_attr($audience['id']); ?>"
                                        <?php selected($selected_audience, $audience['id']); ?>
                                    >
                                        <?php echo esc_html($audience['name']); ?>
                                        (<?php echo number_format($audience['member_count']); ?> <?php _e('members', 'campaignbridge'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Select the default audience for new campaigns', 'campaignbridge'); ?>
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <th scope="row">
                        <?php _e('Connection Status', 'campaignbridge'); ?>
                    </th>
                    <td>
                        <?php if ($is_connected): ?>
                            <span class="status-badge connected">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <strong><?php _e('Connected', 'campaignbridge'); ?></strong>
                            </span>
                        <?php else: ?>
                            <span class="status-badge disconnected">
                                <span class="dashicons dashicons-dismiss"></span>
                                <strong><?php _e('Not Connected', 'campaignbridge'); ?></strong>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <?php submit_button(__('Save Mailchimp Settings', 'campaignbridge'), 'primary', 'submit', false); ?>

            <?php if ($is_connected): ?>
                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('action', 'disconnect'), 'cb_disconnect_mailchimp')); ?>"
                   class="button button-link-delete"
                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to disconnect Mailchimp?', 'campaignbridge'); ?>');">
                    <?php _e('Disconnect', 'campaignbridge'); ?>
                </a>
            <?php endif; ?>
        </p>
    </form>

</div>

<style>
    .mailchimp-tab {
        background: white;
        padding: 20px;
        margin-top: 20px;
        border: 1px solid #ddd;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 3px;
    }

    .status-badge.connected {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.disconnected {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<?php
// Tab-specific assets
$screen->enqueue_style('mailchimp-tab', 'assets/css/admin/screens/settings/mailchimp.css');
$screen->enqueue_script('mailchimp-tab', 'assets/js/admin/screens/settings/mailchimp.js', ['jquery']);

// Localize for AJAX
$screen->localize_script('mailchimp-tab', 'cbMailchimp', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('cb_mailchimp_test'),
    'strings' => [
        'testing' => __('Testing connection...', 'campaignbridge'),
        'success' => __('Connected!', 'campaignbridge'),
        'failed' => __('Connection failed', 'campaignbridge'),
    ],
]);
?>
```

#### Tab 3: Advanced Settings

**File**: `includes/Admin/Screens/settings/advanced.php`

```php
<?php
/**
 * Advanced Settings Tab
 */

$debug_mode = $screen->get('debug_mode', get_option('cb_debug_mode', false));
$rate_limit = $screen->get('rate_limit', get_option('cb_rate_limit', 100));

// Handle form submission
if ($screen->is_post() && $screen->verify_nonce('save_advanced_settings')) {
    $debug_mode = isset($_POST['debug_mode']);
    $rate_limit = absint($screen->post('rate_limit', 100));

    // Validate
    if ($rate_limit < 10 || $rate_limit > 1000) {
        $screen->add_error(__('Rate limit must be between 10 and 1000', 'campaignbridge'));
    } else {
        update_option('cb_debug_mode', $debug_mode);
        update_option('cb_rate_limit', $rate_limit);
        $screen->add_message(__('Advanced settings saved!', 'campaignbridge'));
    }
}
?>

<div class="advanced-settings-tab">

    <h2><?php _e('Advanced Settings', 'campaignbridge'); ?></h2>
    <p class="description">
        <?php _e('Advanced configuration options for developers.', 'campaignbridge'); ?>
    </p>

    <form method="post" action="">
        <?php $screen->nonce_field('save_advanced_settings'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <?php _e('Debug Mode', 'campaignbridge'); ?>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="debug_mode"
                                value="1"
                                <?php checked($debug_mode, true); ?>
                            >
                            <?php _e('Enable debug logging', 'campaignbridge'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Log API requests and errors to wp-content/debug.log', 'campaignbridge'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="rate_limit">
                            <?php _e('API Rate Limit', 'campaignbridge'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="rate_limit"
                            name="rate_limit"
                            value="<?php echo esc_attr($rate_limit); ?>"
                            min="10"
                            max="1000"
                            class="small-text"
                        >
                        <span><?php _e('requests per hour', 'campaignbridge'); ?></span>
                        <p class="description">
                            <?php _e('Maximum API requests per hour (10-1000)', 'campaignbridge'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save Advanced Settings', 'campaignbridge')); ?>
    </form>

    <!-- Reset Section -->
    <div class="reset-section">
        <h3><?php _e('Reset Settings', 'campaignbridge'); ?></h3>
        <p><?php _e('Reset all plugin settings to defaults. This action cannot be undone.', 'campaignbridge'); ?></p>
        <button type="button" class="button button-link-delete" id="reset-settings">
            <?php _e('Reset All Settings', 'campaignbridge'); ?>
        </button>
    </div>

</div>

<style>
    .advanced-settings-tab {
        background: white;
        padding: 20px;
        margin-top: 20px;
        border: 1px solid #ddd;
    }

    .reset-section {
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }
</style>

<?php
$screen->enqueue_style('advanced-tab', 'assets/css/admin/screens/settings/advanced.css');
$screen->enqueue_script('advanced-tab', 'assets/js/admin/screens/settings/advanced.js', ['jquery']);
?>
```

---

## 📊 Final Structure

After creating all files:

```
includes/Admin/Screens/
├── dashboard.php              # Simple screen: Dashboard
├── reports.php                # Simple screen: Reports
└── settings/                  # Tabbed screen: Settings
    ├── _config.php           # Configuration
    ├── general.php           # General tab
    ├── mailchimp.php         # Mailchimp tab
    └── advanced.php          # Advanced tab
```

**Result**:
- 2 simple menu items (Dashboard, Reports)
- 1 tabbed menu item (Settings with 3 tabs)

All auto-discovered and registered automatically! ✅

---

## 🎓 Key Takeaways

1. **Simple screens** = One `.php` file
2. **Tabbed screens** = One folder with multiple `.php` files
3. **`$screen` variable** is available in all screen files
4. **Form handling** = Use `$screen->is_post()` and `$screen->verify_nonce()`
5. **Assets** = Load at bottom of screen file with `$screen->enqueue_*()`
6. **No registration needed** = Just create the file!

---

**Next**: Part 3 - Controllers & Models (optional business logic layer)

# Part 3: Controllers & Models

> **File 3 of 5**: Optional business logic and data layers for complex screens

---

## 🎯 What This Part Covers

- When to use Controllers vs inline code
- When to use Models vs direct WordPress functions
- Creating Controllers with Base_Controller
- Creating Models for data operations
- Complete MVC examples
- Best practices

---

## 🤔 When Do You Need Controllers & Models?

### Use Inline Code When:

✅ **Simple data**: Just getting/setting a few options
✅ **No validation**: Basic form with minimal rules
✅ **No business logic**: Just saving form data
✅ **Single screen**: Logic not reused elsewhere

**Example** (inline code is fine):
```php
// In screen file - totally fine for simple cases
if ($screen->is_post() && $screen->verify_nonce('save')) {
    $name = $screen->post('name');
    update_option('cb_name', $name);
    $screen->add_message('Saved!');
}
```

### Use Controllers When:

✅ **Complex validation**: Multiple rules, dependencies
✅ **Business logic**: Calculations, API calls, workflows
✅ **Multiple forms**: Several actions on same screen
✅ **Reusable logic**: Used across multiple screens
✅ **Testing**: Need to unit test logic

### Use Models When:

✅ **Complex queries**: Custom database operations
✅ **Data transformation**: Processing data before save/display
✅ **Reusable data access**: Used across multiple controllers
✅ **Caching**: Need to cache expensive queries
✅ **Data validation**: Ensuring data integrity

---

## 📝 Creating Controllers

### Step 1: Create Base_Controller

**Location**: `includes/Admin/Core/Base_Controller.php`

```php
<?php
/**
 * Base Controller - Parent class for all controllers
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

abstract class Base_Controller {

    protected array $data = [];
    protected array $errors = [];
    protected array $messages = [];

    /**
     * Constructor - calls init() for child classes
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize controller - override in child classes
     */
    protected function init(): void {
        // Override in child classes to load initial data
    }

    /**
     * Handle incoming requests (form submissions)
     */
    public function handle_request(): void {
        // Only handle POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Verify nonce
        $action = $this->get_nonce_action();
        if (!check_admin_referer($action)) {
            $this->add_error(__('Invalid security token. Please try again.', 'campaignbridge'));
            return;
        }

        // Check capability
        if (!$this->check_capability()) {
            $this->add_error(__('You do not have permission to perform this action.', 'campaignbridge'));
            return;
        }

        // Route to specific handler based on action
        $request_action = $_POST['action'] ?? '';
        $method = 'handle_' . sanitize_key($request_action);

        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->handle_default();
        }
    }

    /**
     * Default handler - override in child classes
     */
    protected function handle_default(): void {
        // Override in child classes if needed
    }

    /**
     * Get nonce action name
     */
    protected function get_nonce_action(): string {
        return 'cb_' . $this->get_controller_name();
    }

    /**
     * Get controller name from class
     */
    protected function get_controller_name(): string {
        $class = get_class($this);
        $parts = explode('\\', $class);
        return strtolower(str_replace('_Controller', '', end($parts)));
    }

    /**
     * Check user capability - override for specific caps
     */
    protected function check_capability(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Add error message
     */
    protected function add_error(string $message): void {
        $this->errors[] = $message;
    }

    /**
     * Add success message
     */
    protected function add_message(string $message): void {
        $this->messages[] = $message;
    }

    /**
     * Set data for view
     */
    public function set_data(string $key, $value): void {
        $this->data[$key] = $value;
    }

    /**
     * Get data for view - returns all data as array
     */
    public function get_data(): array {
        return $this->data;
    }

    /**
     * Get specific data value
     */
    public function get(string $key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    /**
     * Sanitize text input
     */
    protected function sanitize_text(string $input): string {
        return sanitize_text_field($input);
    }

    /**
     * Sanitize textarea input
     */
    protected function sanitize_textarea(string $input): string {
        return sanitize_textarea_field($input);
    }

    /**
     * Sanitize email
     */
    protected function sanitize_email(string $input): string {
        return sanitize_email($input);
    }

    /**
     * Validate required field
     */
    protected function validate_required($value, string $field_name): bool {
        if (empty($value)) {
            $this->add_error(sprintf(__('%s is required.', 'campaignbridge'), ucfirst($field_name)));
            return false;
        }
        return true;
    }

    /**
     * Validate email
     */
    protected function validate_email(string $email, string $field_name): bool {
        if (!is_email($email)) {
            $this->add_error(sprintf(__('%s must be a valid email address.', 'campaignbridge'), ucfirst($field_name)));
            return false;
        }
        return true;
    }

    /**
     * Get nonce field HTML
     */
    public function get_nonce_field(): string {
        return wp_nonce_field($this->get_nonce_action(), '_wpnonce', true, false);
    }
}
```

### Step 2: Create a Specific Controller

**Location**: `includes/Admin/Controllers/Settings_Controller.php`

```php
<?php
/**
 * Settings Controller
 *
 * Handles business logic for Settings screens
 *
 * @package CampaignBridge\Admin\Controllers
 */

namespace CampaignBridge\Admin\Controllers;

use CampaignBridge\Admin\Core\Base_Controller;
use CampaignBridge\Admin\Models\Settings_Model;

class Settings_Controller extends Base_Controller {

    private Settings_Model $model;

    /**
     * Initialize controller
     */
    protected function init(): void {
        // Initialize model
        $this->model = new Settings_Model();

        // Load initial data for views
        $this->load_settings_data();
    }

    /**
     * Load settings data for all views/tabs
     */
    private function load_settings_data(): void {
        // General settings
        $this->set_data('from_name', $this->model->get('from_name', get_bloginfo('name')));
        $this->set_data('from_email', $this->model->get('from_email', get_option('admin_email')));
        $this->set_data('reply_to', $this->model->get('reply_to', ''));

        // Mailchimp settings
        $this->set_data('mailchimp_api_key', $this->model->get('mailchimp_api_key', ''));
        $this->set_data('mailchimp_connected', $this->is_mailchimp_connected());
        $this->set_data('mailchimp_audiences', $this->get_mailchimp_audiences());
        $this->set_data('mailchimp_selected_audience', $this->model->get('mailchimp_audience', ''));

        // Advanced settings
        $this->set_data('debug_mode', $this->model->get('debug_mode', false));
        $this->set_data('rate_limit', $this->model->get('rate_limit', 100));
    }

    /**
     * Handle: Save general settings
     */
    protected function handle_save_general_settings(): void {
        // Get input
        $from_name = $_POST['from_name'] ?? '';
        $from_email = $_POST['from_email'] ?? '';
        $reply_to = $_POST['reply_to'] ?? '';

        // Validate
        if (!$this->validate_required($from_name, 'From Name')) {
            return;
        }

        if (!$this->validate_required($from_email, 'From Email')) {
            return;
        }

        if (!$this->validate_email($from_email, 'From Email')) {
            return;
        }

        if (!empty($reply_to) && !$this->validate_email($reply_to, 'Reply-To')) {
            return;
        }

        // Sanitize
        $from_name = $this->sanitize_text($from_name);
        $from_email = $this->sanitize_email($from_email);
        $reply_to = $this->sanitize_email($reply_to);

        // Save via model
        $this->model->set('from_name', $from_name);
        $this->model->set('from_email', $from_email);
        $this->model->set('reply_to', $reply_to);

        if ($this->model->save()) {
            $this->add_message(__('General settings saved successfully!', 'campaignbridge'));
            $this->load_settings_data(); // Reload
        } else {
            $this->add_error(__('Failed to save settings. Please try again.', 'campaignbridge'));
        }
    }

    /**
     * Handle: Save Mailchimp settings
     */
    protected function handle_save_mailchimp_settings(): void {
        $api_key = $_POST['mailchimp_api_key'] ?? '';
        $audience_id = $_POST['mailchimp_audience'] ?? '';

        // Validate
        if (!$this->validate_required($api_key, 'API Key')) {
            return;
        }

        // Sanitize
        $api_key = $this->sanitize_text($api_key);
        $audience_id = $this->sanitize_text($audience_id);

        // Verify API key works (business logic)
        try {
            $provider = new \CampaignBridge\Providers\Mailchimp_Provider($api_key);

            if ($provider->verify_connection()) {
                // Save
                $this->model->set('mailchimp_api_key', $api_key);
                $this->model->set('mailchimp_audience', $audience_id);

                if ($this->model->save()) {
                    // Clear cache
                    delete_transient('cb_mailchimp_audiences');

                    $this->add_message(__('Mailchimp settings saved and verified!', 'campaignbridge'));
                    $this->load_settings_data(); // Reload
                } else {
                    $this->add_error(__('Failed to save Mailchimp settings.', 'campaignbridge'));
                }
            } else {
                $this->add_error(__('Invalid Mailchimp API key. Please check and try again.', 'campaignbridge'));
            }
        } catch (\Exception $e) {
            $this->add_error(__('Mailchimp connection error: ', 'campaignbridge') . $e->getMessage());
        }
    }

    /**
     * Handle: Save advanced settings
     */
    protected function handle_save_advanced_settings(): void {
        $debug_mode = isset($_POST['debug_mode']);
        $rate_limit = absint($_POST['rate_limit'] ?? 100);

        // Validate
        if ($rate_limit < 10 || $rate_limit > 1000) {
            $this->add_error(__('Rate limit must be between 10 and 1000.', 'campaignbridge'));
            return;
        }

        // Save
        $this->model->set('debug_mode', $debug_mode);
        $this->model->set('rate_limit', $rate_limit);

        if ($this->model->save()) {
            $this->add_message(__('Advanced settings saved!', 'campaignbridge'));
            $this->load_settings_data();
        } else {
            $this->add_error(__('Failed to save advanced settings.', 'campaignbridge'));
        }
    }

    /**
     * Business logic: Check if Mailchimp is connected
     */
    private function is_mailchimp_connected(): bool {
        $api_key = $this->model->get('mailchimp_api_key');

        if (empty($api_key)) {
            return false;
        }

        // Check cache first
        $cached = get_transient('cb_mailchimp_connected');
        if ($cached !== false) {
            return (bool) $cached;
        }

        // Verify connection
        try {
            $provider = new \CampaignBridge\Providers\Mailchimp_Provider($api_key);
            $connected = $provider->verify_connection();

            // Cache for 5 minutes
            set_transient('cb_mailchimp_connected', $connected ? 1 : 0, 300);

            return $connected;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Business logic: Get Mailchimp audiences
     */
    private function get_mailchimp_audiences(): array {
        if (!$this->is_mailchimp_connected()) {
            return [];
        }

        // Check cache
        $cached = get_transient('cb_mailchimp_audiences');
        if ($cached !== false) {
            return $cached;
        }

        try {
            $api_key = $this->model->get('mailchimp_api_key');
            $provider = new \CampaignBridge\Providers\Mailchimp_Provider($api_key);
            $audiences = $provider->get_audiences();

            // Cache for 15 minutes
            set_transient('cb_mailchimp_audiences', $audiences, 900);

            return $audiences;
        } catch (\Exception $e) {
            return [];
        }
    }
}
```

---

## 📦 Creating Models

### Step 1: Create a Basic Model

**Location**: `includes/Admin/Models/Settings_Model.php`

```php
<?php
/**
 * Settings Model
 *
 * Handles all data operations for settings
 *
 * @package CampaignBridge\Admin\Models
 */

namespace CampaignBridge\Admin\Models;

class Settings_Model {

    private const OPTION_NAME = 'campaignbridge_settings';
    private array $settings = [];
    private bool $loaded = false;

    /**
     * Constructor - loads settings
     */
    public function __construct() {
        $this->load();
    }

    /**
     * Load settings from database
     */
    public function load(): void {
        if ($this->loaded) {
            return;
        }

        $this->settings = get_option(self::OPTION_NAME, []);
        $this->loaded = true;
    }

    /**
     * Get a setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public function get(string $key, $default = null) {
        $this->load();
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a setting value (in memory)
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     */
    public function set(string $key, $value): void {
        $this->load();
        $this->settings[$key] = $value;
    }

    /**
     * Delete a setting
     *
     * @param string $key Setting key
     */
    public function delete(string $key): void {
        $this->load();
        unset($this->settings[$key]);
    }

    /**
     * Check if setting exists
     *
     * @param string $key Setting key
     * @return bool
     */
    public function has(string $key): bool {
        $this->load();
        return isset($this->settings[$key]);
    }

    /**
     * Get all settings
     *
     * @return array All settings
     */
    public function get_all(): array {
        $this->load();
        return $this->settings;
    }

    /**
     * Save settings to database
     *
     * @return bool Success status
     */
    public function save(): bool {
        return update_option(self::OPTION_NAME, $this->settings);
    }

    /**
     * Clear all settings
     *
     * @return bool Success status
     */
    public function clear(): bool {
        $this->settings = [];
        return delete_option(self::OPTION_NAME);
    }

    /**
     * Get settings by prefix
     *
     * @param string $prefix Prefix to filter by
     * @return array Filtered settings
     */
    public function get_by_prefix(string $prefix): array {
        $this->load();

        return array_filter(
            $this->settings,
            fn($key) => strpos($key, $prefix) === 0,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Bulk set settings
     *
     * @param array $settings Settings to set
     */
    public function set_many(array $settings): void {
        $this->load();
        $this->settings = array_merge($this->settings, $settings);
    }

    /**
     * Get default settings
     *
     * @return array Default settings
     */
    public static function get_defaults(): array {
        return [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'reply_to' => '',
            'debug_mode' => false,
            'rate_limit' => 100,
            'mailchimp_api_key' => '',
            'mailchimp_audience' => '',
        ];
    }

    /**
     * Reset to defaults
     *
     * @return bool Success status
     */
    public function reset_to_defaults(): bool {
        $this->settings = self::get_defaults();
        return $this->save();
    }
}
```

### Step 2: Create a Custom Post Type Model

**Location**: `includes/Admin/Models/Campaign_Model.php`

```php
<?php
/**
 * Campaign Model
 *
 * Handles campaign data operations
 *
 * @package CampaignBridge\Admin\Models
 */

namespace CampaignBridge\Admin\Models;

class Campaign_Model {

    private const POST_TYPE = 'cb_campaign';

    /**
     * Get all campaigns
     *
     * @param array $args Query arguments
     * @return array Campaigns
     */
    public function get_all(array $args = []): array {
        $defaults = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $args = array_merge($defaults, $args);
        $query = new \WP_Query($args);

        return $query->posts;
    }

    /**
     * Get campaign by ID
     *
     * @param int $campaign_id Campaign ID
     * @return \WP_Post|null Campaign post
     */
    public function get(int $campaign_id): ?\WP_Post {
        $post = get_post($campaign_id);

        if ($post && $post->post_type === self::POST_TYPE) {
            return $post;
        }

        return null;
    }

    /**
     * Create a new campaign
     *
     * @param array $data Campaign data
     * @return int|false Campaign ID or false on failure
     */
    public function create(array $data) {
        $defaults = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'draft',
            'post_title' => '',
            'post_content' => '',
        ];

        $campaign_data = array_merge($defaults, $data);

        return wp_insert_post($campaign_data);
    }

    /**
     * Update a campaign
     *
     * @param int $campaign_id Campaign ID
     * @param array $data Campaign data
     * @return int|false Campaign ID or false on failure
     */
    public function update(int $campaign_id, array $data) {
        $data['ID'] = $campaign_id;
        return wp_update_post($data);
    }

    /**
     * Delete a campaign
     *
     * @param int $campaign_id Campaign ID
     * @param bool $force_delete Whether to bypass trash
     * @return bool Success status
     */
    public function delete(int $campaign_id, bool $force_delete = false): bool {
        $result = wp_delete_post($campaign_id, $force_delete);
        return $result !== false;
    }

    /**
     * Get campaign meta
     *
     * @param int $campaign_id Campaign ID
     * @param string $key Meta key
     * @param mixed $default Default value
     * @return mixed Meta value
     */
    public function get_meta(int $campaign_id, string $key, $default = null) {
        $value = get_post_meta($campaign_id, $key, true);
        return $value !== '' ? $value : $default;
    }

    /**
     * Update campaign meta
     *
     * @param int $campaign_id Campaign ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool Success status
     */
    public function update_meta(int $campaign_id, string $key, $value): bool {
        return update_post_meta($campaign_id, $key, $value) !== false;
    }

    /**
     * Get campaign stats
     *
     * @param string $date_from Start date (Y-m-d)
     * @param string $date_to End date (Y-m-d)
     * @return array Stats
     */
    public function get_stats(string $date_from, string $date_to): array {
        $campaigns = $this->get_all([
            'date_query' => [
                [
                    'after' => $date_from,
                    'before' => $date_to,
                    'inclusive' => true,
                ],
            ],
        ]);

        $total_sent = 0;
        $total_opens = 0;
        $total_clicks = 0;

        foreach ($campaigns as $campaign) {
            $total_sent += (int) $this->get_meta($campaign->ID, 'sent_count', 0);
            $total_opens += (int) $this->get_meta($campaign->ID, 'open_count', 0);
            $total_clicks += (int) $this->get_meta($campaign->ID, 'click_count', 0);
        }

        $open_rate = $total_sent > 0 ? round(($total_opens / $total_sent) * 100, 2) . '%' : '0%';
        $click_rate = $total_sent > 0 ? round(($total_clicks / $total_sent) * 100, 2) . '%' : '0%';

        return [
            'total_campaigns' => count($campaigns),
            'total_sent' => $total_sent,
            'total_opens' => $total_opens,
            'total_clicks' => $total_clicks,
            'open_rate' => $open_rate,
            'click_rate' => $click_rate,
        ];
    }
}
```

---

## 🔗 Using Controller & Model Together

### In _config.php

**File**: `includes/Admin/Screens/settings/_config.php`

```php
<?php
return [
    'menu_title' => __('Settings', 'campaignbridge'),
    'capability' => 'manage_options',

    // Specify controller
    'controller' => \CampaignBridge\Admin\Controllers\Settings_Controller::class,
];
```

### In Screen/Tab Files

The controller is automatically available as `$screen->get_controller()` or you can get data directly:

**File**: `includes/Admin/Screens/settings/general.php`

```php
<?php
// Data is automatically loaded by controller
$from_name = $screen->get('from_name');
$from_email = $screen->get('from_email');

// Form submission is handled by controller automatically
// Just display the form
?>

<form method="post">
    <?php $screen->nonce_field('save_general_settings'); ?>
    <input type="hidden" name="action" value="save_general_settings">

    <table class="form-table">
        <tr>
            <th><label>From Name</label></th>
            <td><input type="text" name="from_name" value="<?php echo esc_attr($from_name); ?>"></td>
        </tr>
        <tr>
            <th><label>From Email</label></th>
            <td><input type="email" name="from_email" value="<?php echo esc_attr($from_email); ?>"></td>
        </tr>
    </table>

    <?php submit_button(); ?>
</form>
```

**That's it!** The controller:
1. ✅ Loads data automatically (via `init()`)
2. ✅ Handles form submission (via `handle_request()`)
3. ✅ Validates and sanitizes (via `handle_save_general_settings()`)
4. ✅ Saves via model (via `$this->model->save()`)
5. ✅ Shows success/error messages

---

## 🎓 Best Practices

### 1. Keep Views Dumb

❌ **Bad** - Business logic in view:
```php
// In screen file
if ($_POST) {
    $api_key = $_POST['api_key'];
    $provider = new Mailchimp_Provider($api_key);
    if ($provider->verify()) {
        // Complex logic here...
    }
}
```

✅ **Good** - Logic in controller:
```php
// In screen file
$is_connected = $screen->get('mailchimp_connected');
echo $is_connected ? 'Connected' : 'Not connected';
```

### 2. Keep Controllers Focused

✅ **Good** - Specific handler methods:
```php
protected function handle_save_general_settings() { }
protected function handle_save_mailchimp_settings() { }
protected function handle_disconnect_mailchimp() { }
```

❌ **Bad** - One giant method:
```php
protected function handle_save() {
    if ($_POST['type'] == 'general') { /* 100 lines */ }
    elseif ($_POST['type'] == 'mailchimp') { /* 100 lines */ }
}
```

### 3. Use Models for Data Only

✅ **Good** - Model handles data:
```php
class Settings_Model {
    public function get($key, $default = null) { }
    public function set($key, $value) { }
    public function save() { }
}
```

❌ **Bad** - Model has business logic:
```php
class Settings_Model {
    public function verify_mailchimp_and_save($api_key) {
        // NO! This is business logic, belongs in controller
    }
}
```

### 4. Name Methods Clearly

✅ **Good**:
```php
handle_save_general_settings()
handle_disconnect_mailchimp()
handle_test_api_connection()
```

❌ **Bad**:
```php
process()
doSave()
action1()
```

---

## 📊 Complete MVC Flow Diagram

```
User submits form
       │
       ▼
Screen file has:
<form method="post">
    <input type="hidden" name="action" value="save_general_settings">
    ...
</form>
       │
       ▼
Controller's handle_request() is called automatically
       │
       ▼
Controller routes to: handle_save_general_settings()
       │
       ├─→ Validates input
       ├─→ Sanitizes data
       ├─→ Calls Model to save
       └─→ Sets success/error message
       │
       ▼
Controller's get_data() provides fresh data
       │
       ▼
Screen displays updated data
```

---

**Next**: Part 4 - Asset Loading (traditional & built assets)

# Part 4: Asset Loading

> **File 4 of 5**: Traditional CSS/JS and modern build tools with .asset.php support

---

## 🎯 What This Part Covers

- Traditional asset loading (simple CSS/JS files)
- Built asset loading (.asset.php files from build tools)
- Setting up @wordpress/scripts
- Webpack configuration
- Asset organization strategies
- Three levels of asset loading
- Real-world examples

---

## 📊 Three Levels of Asset Loading

### Level 1: Global Assets (All Pages)

**Where**: `includes/Admin/Admin.php` → `enqueue_global_assets()`

**Loads on**: Every CampaignBridge admin page

**Use for**:
- Shared layout styles
- Common UI components
- Global JavaScript utilities
- Brand colors/typography

**Example**:
```php
// In Admin.php
public function enqueue_global_assets(string $hook): void {
    if (strpos($hook, 'campaignbridge') === false) return;

    // Global CSS
    wp_enqueue_style('cb-admin-global',
        CAMPAIGNBRIDGE_PLUGIN_URL . 'assets/css/admin/global.css',
        [],
        CAMPAIGNBRIDGE_VERSION
    );

    // Global JS
    wp_enqueue_script('cb-admin-global',
        CAMPAIGNBRIDGE_PLUGIN_URL . 'assets/js/admin/global.js',
        ['jquery'],
        CAMPAIGNBRIDGE_VERSION,
        true
    );
}
```

### Level 2: Screen/Page Assets (Specific Page, All Tabs)

**Where**:
- `_config.php` file (for all tabs)
- At end of screen `.php` file (for single pages)

**Loads on**: Specific page only

**Use for**:
- Page-specific layouts
- Shared tab styles
- Page-level JavaScript

**Example in _config.php**:
```php
return [
    'menu_title' => __('Settings', 'campaignbridge'),

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

### Level 3: Tab-Specific Assets (Only When Tab Active)

**Where**: At end of tab `.php` file

**Loads on**: Only when that specific tab is active

**Use for**:
- Tab-specific styles
- Tab-specific JavaScript
- Libraries needed only for that tab

**Example in tab file**:
```php
<?php
// Tab content here
?>

<?php
// Load only when this tab is active
$screen->enqueue_style('mailchimp-tab', 'assets/css/admin/screens/settings/mailchimp.css');
$screen->enqueue_script('mailchimp-tab', 'assets/js/admin/screens/settings/mailchimp.js', ['jquery']);
?>
```

---

## 🔹 Traditional Asset Loading (Simple CSS/JS)

### Method 1: In Screen Files

**File**: `includes/Admin/Screens/dashboard.php`

```php
<?php
// Dashboard content
?>

<div class="dashboard-screen">
    <h2>Dashboard</h2>
    <!-- Content -->
</div>

<?php
// Load assets at bottom of file
$screen->enqueue_style('dashboard-screen', 'assets/css/admin/screens/dashboard.css');
$screen->enqueue_script('dashboard-screen', 'assets/js/admin/screens/dashboard.js', ['jquery', 'chart-js']);

// Pass data to JavaScript
$screen->localize_script('dashboard-screen', 'dashboardData', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('dashboard_action'),
    'stats' => $screen->get('stats', []),
]);
?>
```

### Method 2: In _config.php

**File**: `includes/Admin/Screens/settings/_config.php`

```php
<?php
return [
    'menu_title' => __('Settings', 'campaignbridge'),

    // Traditional assets
    'assets' => [
        // CSS files
        'styles' => [
            'settings-page' => 'assets/css/admin/screens/settings/page.css',
            'settings-forms' => 'assets/css/admin/screens/settings/forms.css',
        ],

        // JS files
        'scripts' => [
            // Simple (just path)
            'settings-page' => 'assets/js/admin/screens/settings/page.js',

            // With options (dependencies, etc)
            'settings-validation' => [
                'src' => 'assets/js/admin/screens/settings/validation.js',
                'deps' => ['jquery', 'wp-util'],
            ],
        ],
    ],
];
```

### Traditional Asset Directory Structure

```
assets/
├── css/
│   └── admin/
│       ├── global.css                    # Level 1: All pages
│       └── screens/
│           ├── dashboard.css             # Level 2: Dashboard page
│           ├── reports.css               # Level 2: Reports page
│           └── settings/
│               ├── page.css              # Level 2: All Settings tabs
│               ├── general.css           # Level 3: General tab only
│               ├── mailchimp.css         # Level 3: Mailchimp tab only
│               └── advanced.css          # Level 3: Advanced tab only
└── js/
    └── admin/
        ├── global.js
        └── screens/
            ├── dashboard.js
            ├── reports.js
            └── settings/
                ├── page.js
                ├── general.js
                ├── mailchimp.js
                └── advanced.js
```

---

## 🚀 Built Asset Loading (.asset.php files)

### What Are .asset.php Files?

When you use build tools like `@wordpress/scripts`, they automatically generate `.asset.php` files alongside your compiled assets. These files contain:

1. **Dependencies**: All required scripts (React, WordPress packages, etc.)
2. **Version**: Content-based hash for cache busting

**Example** `build/admin/dashboard.asset.php`:
```php
<?php
return array(
    'dependencies' => array(
        'react',
        'react-dom',
        'wp-element',
        'wp-components',
        'wp-data',
        'wp-api-fetch',
    ),
    'version' => '5f8d7e3c2a1b9d4f'
);
```

### Method 1: In Screen Files

**File**: `includes/Admin/Screens/dashboard.php`

```php
<?php
// Dashboard content
?>

<div class="dashboard-screen">
    <div id="dashboard-charts"></div>
</div>

<?php
// Load built assets (auto-manages dependencies and version!)
$screen->asset_enqueue_style('dashboard-screen', 'build/admin/dashboard.asset.php');
$screen->asset_enqueue_script('dashboard-screen', 'build/admin/dashboard.asset.php');

// Pass data to JavaScript
$screen->localize_script('dashboard-screen', 'dashboardData', [
    'stats' => $screen->get('stats', []),
    'apiUrl' => rest_url('campaignbridge/v1/'),
    'nonce' => wp_create_nonce('wp_rest'),
]);
?>
```

### Method 2: In _config.php

**File**: `includes/Admin/Screens/settings/_config.php`

```php
<?php
return [
    'menu_title' => __('Settings', 'campaignbridge'),

    // Built assets with .asset.php
    'assets' => [
        // CSS from .asset.php
        'asset_styles' => [
            'settings-page' => 'build/admin/settings.asset.php',
        ],

        // JS from .asset.php
        'asset_scripts' => [
            // Simple (just path)
            'settings-page' => 'build/admin/settings.asset.php',

            // With options
            'settings-advanced' => [
                'src' => 'build/admin/settings-advanced.asset.php',
                'deps' => ['wp-notices'], // Additional deps
                'in_footer' => true,
            ],
        ],

        // Shorthand: both CSS and JS from same .asset.php
        'asset_both' => [
            'settings-forms' => 'build/admin/settings-forms.asset.php',
        ],
    ],
];
```

### Built Asset Directory Structure

```
src/                                  # Source files (you write)
├── admin/
│   ├── dashboard.js
│   ├── dashboard.scss
│   ├── settings.js
│   ├── settings.scss
│   └── settings/
│       ├── general.js
│       ├── general.scss
│       ├── mailchimp.js
│       └── mailchimp.scss

build/                                # Compiled files (auto-generated)
├── admin/
│   ├── dashboard.asset.php          # Auto-generated
│   ├── dashboard.js                 # Compiled
│   ├── dashboard.css                # Compiled
│   ├── settings.asset.php
│   ├── settings.js
│   ├── settings.css
│   └── settings/
│       ├── general.asset.php
│       ├── general.js
│       ├── general.css
│       ├── mailchimp.asset.php
│       ├── mailchimp.js
│       └── mailchimp.css
```

---

## 🛠️ Setting Up Build Tools

### Step 1: Install @wordpress/scripts

```bash
npm install --save-dev @wordpress/scripts
```

### Step 2: Update package.json

**File**: `package.json`

```json
{
  "name": "campaignbridge",
  "version": "1.0.0",
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start",
    "format": "wp-scripts format",
    "lint:js": "wp-scripts lint-js",
    "lint:css": "wp-scripts lint-style"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27.0.0"
  }
}
```

### Step 3: Create webpack.config.js (Multiple Entry Points)

**File**: `webpack.config.js`

```javascript
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        // Admin pages
        'admin/dashboard': './src/admin/dashboard.js',
        'admin/settings': './src/admin/settings.js',
        'admin/reports': './src/admin/reports.js',

        // Settings tabs
        'admin/settings/general': './src/admin/settings/general.js',
        'admin/settings/mailchimp': './src/admin/settings/mailchimp.js',
        'admin/settings/advanced': './src/admin/settings/advanced.js',
    },
    output: {
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js',
    },
};
```

### Step 4: Create Source Files

#### JavaScript with React

**File**: `src/admin/dashboard.js`

```javascript
import './dashboard.scss';
import { render } from '@wordpress/element';
import { Card, CardHeader, CardBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Dashboard Charts Component
 */
const DashboardCharts = () => {
    const { stats } = window.dashboardData || {};

    return (
        <div className="dashboard-charts">
            <Card>
                <CardHeader>
                    <h2>Campaign Statistics</h2>
                </CardHeader>
                <CardBody>
                    <div className="stats-grid">
                        <div className="stat-item">
                            <span className="stat-label">Total Campaigns</span>
                            <span className="stat-value">{stats?.total_campaigns || 0}</span>
                        </div>
                        <div className="stat-item">
                            <span className="stat-label">Emails Sent</span>
                            <span className="stat-value">{stats?.total_sent || 0}</span>
                        </div>
                    </div>
                </CardBody>
            </Card>
        </div>
    );
};

// Initialize when DOM ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('dashboard-charts');
    if (container) {
        render(<DashboardCharts />, container);
    }
});
```

#### SCSS Styles

**File**: `src/admin/dashboard.scss`

```scss
.dashboard-charts {
    margin-top: 20px;

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        padding: 20px 0;
    }

    .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
    }
}
```

### Step 5: Build Assets

```bash
# Development build (with source maps)
npm run start

# Production build (minified)
npm run build
```

**Output**:
```
build/
├── admin/
│   ├── dashboard.asset.php          # ✅ Auto-generated with dependencies
│   ├── dashboard.js                 # ✅ Compiled with React
│   └── dashboard.css                # ✅ Compiled from SCSS
```

### Step 6: Use Built Assets in Screens

**File**: `includes/Admin/Screens/dashboard.php`

```php
<?php
// Dashboard content
?>

<div class="dashboard-screen">
    <h2><?php _e('Dashboard', 'campaignbridge'); ?></h2>

    <!-- React will render here -->
    <div id="dashboard-charts"></div>
</div>

<?php
// Load built assets - dependencies automatically handled!
$screen->asset_enqueue('dashboard-screen', 'build/admin/dashboard.asset.php');

// Pass data to JavaScript
$screen->localize_script('dashboard-screen', 'dashboardData', [
    'stats' => $screen->get('stats', []),
    'apiUrl' => rest_url('campaignbridge/v1/'),
    'nonce' => wp_create_nonce('wp_rest'),
]);
?>
```

---

## 🎨 Complete Examples

### Example 1: Simple Page with Traditional Assets

**File**: `includes/Admin/Screens/reports.php`

```php
<?php
$reports = $screen->get('reports', []);
?>

<div class="reports-screen">
    <h2><?php _e('Campaign Reports', 'campaignbridge'); ?></h2>

    <table class="wp-list-table widefat">
        <thead>
            <tr>
                <th><?php _e('Campaign', 'campaignbridge'); ?></th>
                <th><?php _e('Sent', 'campaignbridge'); ?></th>
                <th><?php _e('Opens', 'campaignbridge'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo esc_html($report['name']); ?></td>
                    <td><?php echo number_format($report['sent']); ?></td>
                    <td><?php echo number_format($report['opens']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
// Traditional assets - simple CSS/JS files
$screen->enqueue_style('reports-screen', 'assets/css/admin/screens/reports.css');
$screen->enqueue_script('reports-screen', 'assets/js/admin/screens/reports.js', ['jquery']);
?>
```

**File**: `assets/js/admin/screens/reports.js`

```javascript
jQuery(document).ready(function($) {
    // Simple jQuery for reports page
    $('.wp-list-table tbody tr').hover(
        function() {
            $(this).css('background-color', '#f0f0f0');
        },
        function() {
            $(this).css('background-color', '');
        }
    );
});
```

### Example 2: React Dashboard with Built Assets

**File**: `includes/Admin/Screens/dashboard.php`

```php
<?php
$stats = $screen->get('stats', []);
?>

<div class="dashboard-screen">
    <h2><?php _e('Dashboard', 'campaignbridge'); ?></h2>

    <!-- React components will render here -->
    <div id="dashboard-app"></div>
</div>

<?php
// Built assets with React - auto-manages all dependencies!
$screen->asset_enqueue('dashboard-app', 'build/admin/dashboard.asset.php');

$screen->localize_script('dashboard-app', 'dashboardData', [
    'stats' => $stats,
    'apiUrl' => rest_url('campaignbridge/v1/'),
    'nonce' => wp_create_nonce('wp_rest'),
    'strings' => [
        'loading' => __('Loading...', 'campaignbridge'),
        'error' => __('Error loading data', 'campaignbridge'),
    ],
]);
?>
```

**File**: `src/admin/dashboard.js`

```javascript
import './dashboard.scss';
import { render, useState, useEffect } from '@wordpress/element';
import { Card, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Dashboard = () => {
    const [stats, setStats] = useState(window.dashboardData?.stats || {});
    const [loading, setLoading] = useState(false);

    const refreshStats = async () => {
        setLoading(true);
        try {
            const data = await apiFetch({
                path: '/campaignbridge/v1/stats',
            });
            setStats(data);
        } catch (error) {
            console.error('Error:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="dashboard-container">
            {loading && <Spinner />}

            <div className="stats-grid">
                <Card>
                    <h3>Total Campaigns</h3>
                    <p className="stat-number">{stats.total_campaigns || 0}</p>
                </Card>

                <Card>
                    <h3>Emails Sent</h3>
                    <p className="stat-number">{stats.total_sent || 0}</p>
                </Card>

                <Card>
                    <h3>Open Rate</h3>
                    <p className="stat-number">{stats.open_rate || '0%'}</p>
                </Card>
            </div>

            <button onClick={refreshStats} className="button">
                Refresh Stats
            </button>
        </div>
    );
};

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('dashboard-app');
    if (container) {
        render(<Dashboard />, container);
    }
});
```

### Example 3: Mixed Traditional and Built Assets

**File**: `includes/Admin/Screens/settings/_config.php`

```php
<?php
return [
    'menu_title' => __('Settings', 'campaignbridge'),

    'assets' => [
        // Traditional CSS (simple layout, no build needed)
        'styles' => [
            'settings-layout' => 'assets/css/admin/screens/settings/layout.css',
        ],

        // Built JS (uses React for interactive forms)
        'asset_scripts' => [
            'settings-forms' => 'build/admin/settings.asset.php',
        ],
    ],
];
```

---

## 📋 Asset Loading Checklist

### For Traditional Assets:

- [ ] Create CSS file in `assets/css/admin/screens/`
- [ ] Create JS file in `assets/js/admin/screens/`
- [ ] Use `$screen->enqueue_style()` in screen file
- [ ] Use `$screen->enqueue_script()` in screen file
- [ ] Specify dependencies manually (e.g., `['jquery']`)
- [ ] Version updates manually when files change

### For Built Assets:

- [ ] Install `@wordpress/scripts`
- [ ] Configure `webpack.config.js`
- [ ] Create source files in `src/`
- [ ] Run `npm run build`
- [ ] Use `$screen->asset_enqueue_style()` or `asset_enqueue_script()`
- [ ] Dependencies and version handled automatically! ✅

---

## 🎓 Best Practices

### 1. Choose the Right Approach

**Use Traditional Assets** when:
- Simple CSS styling
- Basic jQuery interactions
- No build process desired
- Small, standalone scripts

**Use Built Assets** when:
- Using React or modern JavaScript
- Need TypeScript support
- Using SCSS/SASS preprocessing
- Want automatic dependency management
- Need code splitting and tree shaking

### 2. Organize by Specificity

```
Global (all pages)
    ↓
Page (specific page, all tabs)
    ↓
Tab (specific tab only)
```

### 3. Minimize Asset Size

- ✅ Only enqueue assets where needed
- ✅ Use tab-specific assets to avoid loading unnecessary code
- ✅ Minify production builds
- ✅ Use content hashes for cache busting

### 4. Version Management

**Traditional**:
```php
$screen->enqueue_script('handle', 'path.js', ['jquery'], '1.2.3');
// Must update version manually
```

**Built** (.asset.php):
```php
$screen->asset_enqueue_script('handle', 'build/file.asset.php');
// Version auto-updates based on content hash!
```

---

**Next**: Part 5 - Migration from Legacy System

# Part 5: Migration from Legacy System

> **File 5 of 5**: Step-by-step guide to migrate from Admin_Legacy/ to new file-based system

---

## 🎯 What This Part Covers

- Pre-migration checklist
- Analyzing legacy pages
- Migration strategy for each page type
- Step-by-step migration process
- Testing each migrated page
- Final cleanup and verification

---

## 📋 Pre-Migration Checklist

Before starting migration, ensure:

- [ ] ✅ New system is installed and working
- [ ] ✅ Test screen loads correctly (`test.php`)
- [ ] ✅ `Admin_Legacy/` exists with old system
- [ ] ✅ WordPress is currently using legacy system
- [ ] ✅ You have backups (database + files)
- [ ] ✅ Debug mode enabled: `define('WP_DEBUG', true);`

---

## 🔍 Step 1: Inventory Legacy Pages

List all pages in `includes/Admin_Legacy/Pages/`:

**Example inventory**:
```
Admin_Legacy/Pages/
├── Dashboard_Page.php          # Analyze → Simple or Tabbed?
├── Settings_Page.php           # Analyze → Simple or Tabbed?
├── Templates_Page.php          # Analyze → Simple or Tabbed?
├── Campaigns_Page.php          # Analyze → Simple or Tabbed?
└── Reports_Page.php            # Analyze → Simple or Tabbed?
```

For each page, determine:
1. **Is it simple or tabbed?** (Look for tab navigation in `render()` method)
2. **What does it do?** (Display data, forms, both?)
3. **Does it need a controller?** (Complex logic?) or inline code is fine?

---

## 📊 Step 2: Analyze Each Legacy Page

### Analysis Template

For each page, ask:

```
Page: Dashboard_Page.php
├── Type: [ ] Simple  [ ] Tabbed
├── Has forms: [ ] Yes  [ ] No
├── Complex logic: [ ] Yes (needs controller)  [ ] No (inline fine)
├── Asset files: [ ] CSS file(s)  [ ] JS file(s)  [ ] None
└── Dependencies: [ ] Other classes  [ ] External APIs  [ ] None
```

### Example Analysis: Dashboard_Page.php

**File**: `includes/Admin_Legacy/Pages/Dashboard_Page.php`

```php
<?php
class Dashboard_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu() {
        add_submenu_page(
            'campaignbridge',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'campaignbridge-dashboard',
            [$this, 'render']
        );
    }

    public function render() {
        $stats = $this->get_stats();
        ?>
        <div class="wrap">
            <h1>Dashboard</h1>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Campaigns</h3>
                    <p><?php echo esc_html($stats['campaigns']); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Emails Sent</h3>
                    <p><?php echo number_format($stats['sent']); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_stats() {
        return [
            'campaigns' => get_option('cb_total_campaigns', 0),
            'sent' => get_option('cb_total_sent', 0),
        ];
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'campaignbridge-dashboard') === false) return;

        wp_enqueue_style('cb-dashboard',
            plugins_url('assets/css/dashboard.css', dirname(__FILE__, 2)));
        wp_enqueue_script('cb-dashboard',
            plugins_url('assets/js/dashboard.js', dirname(__FILE__, 2)),
            ['jquery']);
    }
}
```

**Analysis**:
```
✅ Type: Simple (no tabs, single render method)
✅ Has forms: No
✅ Complex logic: No (simple option reads)
✅ Assets: dashboard.css, dashboard.js
✅ Dependencies: None
✅ Controller needed: No (logic is simple)
```

---

## 🔄 Step 3: Migration Process

### Migration Pattern A: Simple Page (No Tabs)

**Legacy**: `Admin_Legacy/Pages/Dashboard_Page.php`

**Steps**:

1. **Create new screen file**: `includes/Admin/Screens/dashboard.php`

2. **Extract HTML from `render()` method**:
```php
<?php
/**
 * Dashboard Screen
 */

// Get data (inline or from controller)
$stats = [
    'campaigns' => get_option('cb_total_campaigns', 0),
    'sent' => get_option('cb_total_sent', 0),
];

// If using controller, get from $screen:
// $stats = $screen->get('stats', []);
?>

<div class="dashboard-screen">
    <h2><?php _e('Dashboard', 'campaignbridge'); ?></h2>

    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php _e('Total Campaigns', 'campaignbridge'); ?></h3>
            <p class="stat-number"><?php echo esc_html($stats['campaigns']); ?></p>
        </div>

        <div class="stat-card">
            <h3><?php _e('Emails Sent', 'campaignbridge'); ?></h3>
            <p class="stat-number"><?php echo number_format($stats['sent']); ?></p>
        </div>
    </div>
</div>

<?php
// Load assets
$screen->enqueue_style('dashboard-screen', 'assets/css/admin/screens/dashboard.css');
$screen->enqueue_script('dashboard-screen', 'assets/js/admin/screens/dashboard.js', ['jquery']);
?>
```

3. **Test**:
   - Switch to new system (if not already)
   - Check admin menu for "Dashboard"
   - Click and verify it loads
   - Check browser console for errors
   - Check assets load in DevTools Network tab

4. **If working, legacy file is no longer needed** (don't delete yet!)

### Migration Pattern B: Tabbed Page

**Legacy**: `Admin_Legacy/Pages/Settings_Page.php`

```php
<?php
class Settings_Page {

    public function render() {
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap">
            <h1>Settings</h1>

            <!-- Tab navigation -->
            <nav class="nav-tab-wrapper">
                <a href="?page=settings&tab=general"
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    General
                </a>
                <a href="?page=settings&tab=mailchimp"
                   class="nav-tab <?php echo $active_tab === 'mailchimp' ? 'nav-tab-active' : ''; ?>">
                    Mailchimp
                </a>
            </nav>

            <?php
            switch ($active_tab) {
                case 'general':
                    $this->render_general_tab();
                    break;
                case 'mailchimp':
                    $this->render_mailchimp_tab();
                    break;
            }
            ?>
        </div>
        <?php
    }

    private function render_general_tab() {
        $from_name = get_option('cb_from_name', '');
        ?>
        <form method="post">
            <?php wp_nonce_field('save_general'); ?>
            <table class="form-table">
                <tr>
                    <th><label>From Name</label></th>
                    <td><input type="text" name="from_name" value="<?php echo esc_attr($from_name); ?>"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_mailchimp_tab() {
        $api_key = get_option('cb_mailchimp_api_key', '');
        ?>
        <form method="post">
            <?php wp_nonce_field('save_mailchimp'); ?>
            <table class="form-table">
                <tr>
                    <th><label>API Key</label></th>
                    <td><input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }
}
```

**Steps**:

1. **Create folder**: `includes/Admin/Screens/settings/`

2. **Create config** (optional): `includes/Admin/Screens/settings/_config.php`
```php
<?php
return [
    'menu_title' => __('Settings', 'campaignbridge'),
    'page_title' => __('CampaignBridge Settings', 'campaignbridge'),
    'capability' => 'manage_options',
];
```

3. **Create tab files**: Extract each tab method to separate file

**File**: `includes/Admin/Screens/settings/general.php`
```php
<?php
/**
 * General Settings Tab
 */

$from_name = get_option('cb_from_name', '');

// Handle form submission
if ($screen->is_post() && $screen->verify_nonce('save_general')) {
    $from_name = $screen->post('from_name');
    update_option('cb_from_name', $from_name);
    $screen->add_message(__('Settings saved!', 'campaignbridge'));
}
?>

<div class="general-settings-tab">
    <h2><?php _e('General Settings', 'campaignbridge'); ?></h2>

    <form method="post">
        <?php $screen->nonce_field('save_general'); ?>

        <table class="form-table">
            <tr>
                <th><label for="from_name"><?php _e('From Name', 'campaignbridge'); ?></label></th>
                <td>
                    <input type="text" id="from_name" name="from_name"
                           value="<?php echo esc_attr($from_name); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save General Settings', 'campaignbridge')); ?>
    </form>
</div>
```

**File**: `includes/Admin/Screens/settings/mailchimp.php`
```php
<?php
/**
 * Mailchimp Settings Tab
 */

$api_key = get_option('cb_mailchimp_api_key', '');

// Handle form submission
if ($screen->is_post() && $screen->verify_nonce('save_mailchimp')) {
    $api_key = $screen->post('api_key');
    update_option('cb_mailchimp_api_key', $api_key);
    $screen->add_message(__('Mailchimp settings saved!', 'campaignbridge'));
}
?>

<div class="mailchimp-settings-tab">
    <h2><?php _e('Mailchimp Integration', 'campaignbridge'); ?></h2>

    <form method="post">
        <?php $screen->nonce_field('save_mailchimp'); ?>

        <table class="form-table">
            <tr>
                <th><label for="api_key"><?php _e('API Key', 'campaignbridge'); ?></label></th>
                <td>
                    <input type="text" id="api_key" name="api_key"
                           value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Mailchimp Settings', 'campaignbridge')); ?>
    </form>
</div>
```

4. **Test**:
   - Check "Settings" appears in menu
   - Check both tabs appear and switch correctly
   - Test each form submission
   - Verify data saves

---

## 🔧 Step 4: Migration Worksheet

Use this for each legacy page:

```
=== MIGRATION WORKSHEET ===

Page: _________________

1. ANALYSIS
   [ ] Simple page (no tabs)
   [ ] Tabbed page (multiple sections)

2. NEW STRUCTURE
   If simple: includes/Admin/Screens/__________.php
   If tabbed: includes/Admin/Screens/__________/
              ├── _config.php (optional)
              ├── __________.php (tab 1)
              ├── __________.php (tab 2)
              └── __________.php (tab 3)

3. EXTRACTED ELEMENTS
   [ ] HTML/render code → New screen file(s)
   [ ] Form handling → Inline or controller
   [ ] Data operations → Inline or model
   [ ] Assets (CSS) → _config.php or screen file
   [ ] Assets (JS) → _config.php or screen file

4. REPLACEMENTS MADE
   [ ] $this-> → $screen->
   [ ] Manual nonce → $screen->nonce_field()
   [ ] Manual nonce check → $screen->verify_nonce()
   [ ] $_POST access → $screen->post()
   [ ] Manual asset enqueue → $screen->enqueue_*()

5. TESTING
   [ ] Page appears in menu
   [ ] Page loads without errors
   [ ] All forms submit correctly
   [ ] Data saves correctly
   [ ] Assets load (check DevTools)
   [ ] No PHP errors (check debug.log)
   [ ] No JS errors (check console)

6. CLEANUP
   [ ] Tested thoroughly
   [ ] Old file marked for deletion: _______________
```

---

## 📝 Step 5: Complete Migration Example

### Before: Legacy Settings Page

**File**: `includes/Admin_Legacy/Pages/Settings_Page.php` (147 lines)

```php
<?php
namespace CampaignBridge\Admin\Pages;

class Settings_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'handle_post']);
    }

    public function add_menu() {
        add_submenu_page(
            'campaignbridge',
            'Settings',
            'Settings',
            'manage_options',
            'campaignbridge-settings',
            [$this, 'render']
        );
    }

    public function handle_post() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        if (isset($_POST['save_general'])) {
            check_admin_referer('save_general');
            $from_name = sanitize_text_field($_POST['from_name'] ?? '');
            update_option('cb_from_name', $from_name);
            add_settings_error('cb_messages', 'cb_message', 'Settings saved', 'success');
        }

        if (isset($_POST['save_mailchimp'])) {
            check_admin_referer('save_mailchimp');
            $api_key = sanitize_text_field($_POST['api_key'] ?? '');
            update_option('cb_mailchimp_api_key', $api_key);
            add_settings_error('cb_messages', 'cb_message', 'Mailchimp settings saved', 'success');
        }
    }

    public function render() {
        $active_tab = $_GET['tab'] ?? 'general';

        settings_errors('cb_messages');
        ?>
        <div class="wrap">
            <h1>CampaignBridge Settings</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=campaignbridge-settings&tab=general"
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    General
                </a>
                <a href="?page=campaignbridge-settings&tab=mailchimp"
                   class="nav-tab <?php echo $active_tab === 'mailchimp' ? 'nav-tab-active' : ''; ?>">
                    Mailchimp
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'general':
                        $this->render_general_tab();
                        break;
                    case 'mailchimp':
                        $this->render_mailchimp_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_general_tab() {
        $from_name = get_option('cb_from_name', get_bloginfo('name'));
        ?>
        <form method="post">
            <?php wp_nonce_field('save_general'); ?>
            <input type="hidden" name="save_general" value="1">

            <table class="form-table">
                <tr>
                    <th><label for="from_name">From Name</label></th>
                    <td>
                        <input type="text" id="from_name" name="from_name"
                               value="<?php echo esc_attr($from_name); ?>" class="regular-text">
                        <p class="description">Name that appears in "From" field</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save General Settings'); ?>
        </form>
        <?php
    }

    private function render_mailchimp_tab() {
        $api_key = get_option('cb_mailchimp_api_key', '');
        ?>
        <form method="post">
            <?php wp_nonce_field('save_mailchimp'); ?>
            <input type="hidden" name="save_mailchimp" value="1">

            <table class="form-table">
                <tr>
                    <th><label for="api_key">API Key</label></th>
                    <td>
                        <input type="text" id="api_key" name="api_key"
                               value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                        <p class="description">Enter your Mailchimp API key</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Mailchimp Settings'); ?>
        </form>
        <?php
    }
}

new Settings_Page();
```

### After: New File-Based System

**Structure**:
```
includes/Admin/Screens/settings/
├── _config.php          # 10 lines
├── general.php          # 35 lines
└── mailchimp.php        # 35 lines
```

**File**: `includes/Admin/Screens/settings/_config.php`

```php
<?php
return [
    'menu_title' => __('Settings', 'campaignbridge'),
    'page_title' => __('CampaignBridge Settings', 'campaignbridge'),
    'capability' => 'manage_options',
];
```

**File**: `includes/Admin/Screens/settings/general.php`

```php
<?php
/**
 * General Settings Tab
 */

$from_name = get_option('cb_from_name', get_bloginfo('name'));

// Handle form submission
if ($screen->is_post() && $screen->verify_nonce('save_general')) {
    $from_name = $screen->post('from_name');
    update_option('cb_from_name', $from_name);
    $screen->add_message(__('General settings saved!', 'campaignbridge'));
}
?>

<div class="general-tab">
    <h2><?php _e('General Settings', 'campaignbridge'); ?></h2>

    <form method="post">
        <?php $screen->nonce_field('save_general'); ?>

        <table class="form-table">
            <tr>
                <th><label for="from_name"><?php _e('From Name', 'campaignbridge'); ?></label></th>
                <td>
                    <input type="text" id="from_name" name="from_name"
                           value="<?php echo esc_attr($from_name); ?>" class="regular-text">
                    <p class="description">
                        <?php _e('Name that appears in "From" field', 'campaignbridge'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save General Settings', 'campaignbridge')); ?>
    </form>
</div>
```

**File**: `includes/Admin/Screens/settings/mailchimp.php`

```php
<?php
/**
 * Mailchimp Settings Tab
 */

$api_key = get_option('cb_mailchimp_api_key', '');

// Handle form submission
if ($screen->is_post() && $screen->verify_nonce('save_mailchimp')) {
    $api_key = $screen->post('api_key');
    update_option('cb_mailchimp_api_key', $api_key);
    $screen->add_message(__('Mailchimp settings saved!', 'campaignbridge'));
}
?>

<div class="mailchimp-tab">
    <h2><?php _e('Mailchimp Integration', 'campaignbridge'); ?></h2>

    <form method="post">
        <?php $screen->nonce_field('save_mailchimp'); ?>

        <table class="form-table">
            <tr>
                <th><label for="api_key"><?php _e('API Key', 'campaignbridge'); ?></label></th>
                <td>
                    <input type="text" id="api_key" name="api_key"
                           value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                    <p class="description">
                        <?php _e('Enter your Mailchimp API key', 'campaignbridge'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Mailchimp Settings', 'campaignbridge')); ?>
    </form>
</div>
```

**Improvements**:
- ✅ 147 lines → 80 lines (46% reduction)
- ✅ No manual menu registration
- ✅ No manual tab navigation code
- ✅ Cleaner form handling with `$screen` helpers
- ✅ Auto-discovered (zero configuration)
- ✅ Easier to maintain (each tab in own file)

---

## ✅ Step 6: Final Verification

### Testing Checklist

Test each migrated page:

```
Page: _________________

Visual Testing:
[ ] Menu item appears in correct position
[ ] Page title displays correctly
[ ] All tabs appear (if tabbed)
[ ] Tab switching works
[ ] Content renders properly
[ ] Forms display correctly
[ ] Buttons work

Functional Testing:
[ ] Forms submit without errors
[ ] Data saves to database
[ ] Success messages display
[ ] Error messages display (test invalid input)
[ ] Data persists after page reload
[ ] AJAX requests work (if any)

Technical Testing:
[ ] No PHP errors in debug.log
[ ] No JavaScript errors in console
[ ] Assets load (check DevTools Network tab)
[ ] Correct assets load for each tab
[ ] No 404 errors for missing files
[ ] Page load time acceptable

Security Testing:
[ ] Nonces verify correctly
[ ] Capability checks work
[ ] Input sanitization works
[ ] Output escaping present
[ ] SQL injection prevented (if custom queries)
```

---

## 🧹 Step 7: Cleanup

### Only After All Pages Migrated and Tested:

1. **Final test of entire plugin**
   - Test every admin page
   - Test every form
   - Test every feature
   - Check for any references to old system

2. **Delete legacy system**:
```bash
rm -rf includes/Admin_Legacy/
```

3. **Update references** (if any external code references old classes)

4. **Update documentation**

5. **Commit changes**:
```bash
git add includes/Admin/
git commit -m "Migrate to file-based admin system"
```

---

## 🎓 Migration Tips

### Tip 1: Migrate One at a Time

Don't migrate everything at once. Do one page, test thoroughly, then next.

### Tip 2: Keep Both Systems Temporarily

The new system and legacy can coexist. Switch between them by changing which `Admin.php` is loaded.

### Tip 3: Document As You Go

Keep notes on what you migrated and any issues encountered.

### Tip 4: Test Edge Cases

- Empty forms
- Invalid input
- Missing data
- Permission errors
- Network errors (for API calls)

### Tip 5: Check Dependencies

If old pages depend on each other, migrate dependent ones together.

---

## 📊 Migration Summary

**Before (Legacy System)**:
- Class-based pages
- Manual menu registration
- Manual tab navigation
- Form handling in class methods
- Mixing presentation and logic
- Hard to test
- Verbose

**After (File-Based System)**:
- File-based routing
- Auto-discovery (zero config)
- Auto tab navigation
- Clean separation of concerns
- Easy to test
- Concise
- Modern PHP

**Result**: Cleaner, more maintainable, and easier to extend! 🎉

---

## ✅ You're Done!

Congratulations! You've successfully:

1. ✅ Set up the new file-based admin system
2. ✅ Created screens (simple and tabbed)
3. ✅ Implemented controllers and models
4. ✅ Set up asset loading (traditional and built)
5. ✅ Migrated from the legacy system

Your CampaignBridge plugin now has a modern, maintainable admin architecture!

---

**Questions or issues?** Refer back to Parts 1-4 for specific implementation details.