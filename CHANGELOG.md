## [1.0.1](https://github.com/TheAggressive/CampaignBridge/compare/v1.0.0...v1.0.1) (2025-11-12)


### Bug Fixes

* enhance semantic-release configuration and CI workflow ([6fa78b7](https://github.com/TheAggressive/CampaignBridge/commit/6fa78b74d29b105f783694649ce99a7490ff447a))
* streamline CI workflow for plugin packaging and artifact verification ([7c5becb](https://github.com/TheAggressive/CampaignBridge/commit/7c5becb22d6dbb22b7691d2760a1886dd20b5724))
* update semantic-release configuration and CI environment ([aec3eb5](https://github.com/TheAggressive/CampaignBridge/commit/aec3eb5892523b458e44968393da8b60caf80f9d))
* update semantic-release configuration to include git commit options ([dc24bfd](https://github.com/TheAggressive/CampaignBridge/commit/dc24bfd80b44bdcc2f88af3751c472c565f20b62))

# 1.0.0 (2025-11-12)


### Bug Fixes

* add debug step to CI workflow for plugins directory listing ([7f830ff](https://github.com/TheAggressive/CampaignBridge/commit/7f830ffe3cf5cb6862488446ec445477b96f59ae))
* add fallback creation of wp-tests-config.php ([b50990c](https://github.com/TheAggressive/CampaignBridge/commit/b50990cb73ef0fbd785e45d2118a0743df1e90e5))
* add missing path: campaignbridge to all checkout steps ([6711eb6](https://github.com/TheAggressive/CampaignBridge/commit/6711eb69f008816d71d80cf2177c881c5a2b1660))
* add wordpress-core directory to ESLint ignores ([5aa6f12](https://github.com/TheAggressive/CampaignBridge/commit/5aa6f12e1df8251ac98d996b8b678cb16978128a))
* CI runs ESLint on src/ directory explicitly ([130d8d3](https://github.com/TheAggressive/CampaignBridge/commit/130d8d313c4cd75169ea0a2d5f5e215764c56150))
* ensure wp-tests-config.php is available for PHPUnit ([0eac2a8](https://github.com/TheAggressive/CampaignBridge/commit/0eac2a8e3d61ad41677fa024533b562ad18bef2d))
* ensure wp-tests-config.php is copied to project root ([04170f7](https://github.com/TheAggressive/CampaignBridge/commit/04170f78b500c6495373707cd6907706a0e4d068))
* install subversion for WordPress test suite setup ([621107e](https://github.com/TheAggressive/CampaignBridge/commit/621107e0b1b49711db6fbd6fcce5f3bc04bd0fb5))
* override working-directory for wp-env stop cleanup steps ([d026a35](https://github.com/TheAggressive/CampaignBridge/commit/d026a3521cdb8ff93c5e194ba21ba8ae4fabdfe6))
* remove cd commands from wp-env scripts since CI runs from repository root ([8302c81](https://github.com/TheAggressive/CampaignBridge/commit/8302c81a766e9fe5377824c7dde529e55d41dfb1))
* remove duplicate database creation and fix MySQL security warning ([b47aaf0](https://github.com/TheAggressive/CampaignBridge/commit/b47aaf04ad139d027d2989afc2a3c0b55b3ec15a))
* remove remaining path: campaignbridge from checkout steps ([70915b1](https://github.com/TheAggressive/CampaignBridge/commit/70915b17e65222d4a8cebbd8b7c2d93a451f0b72))
* resolve STORE_PATH context access error in GitHub Actions ([d02e0c6](https://github.com/TheAggressive/CampaignBridge/commit/d02e0c649f70d9799561858ae9d2e059fa214a00))
* resolve wp-env plugin mounting issue in CI ([047a3d5](https://github.com/TheAggressive/CampaignBridge/commit/047a3d58410071fb8af9ed70b7f0b0c044f99f2c))
* restore cd commands in wp-env scripts for proper directory navigation ([6e9633f](https://github.com/TheAggressive/CampaignBridge/commit/6e9633f830dc03a313d168e64ff6f2ffad01600c))
* set WP_TESTS_CONFIG_FILE environment variable for PHPUnit ([7406bca](https://github.com/TheAggressive/CampaignBridge/commit/7406bca448c2205bc94f172b6166cee43e5b58f0))
* update CI workflow to correct directory for dependency installation ([a85f052](https://github.com/TheAggressive/CampaignBridge/commit/a85f0528eabdcc7ea2ea868fe19d92bfe91eb1fc))
* update CI workflow to run PHPUnit tests with correct commands ([5ae2c64](https://github.com/TheAggressive/CampaignBridge/commit/5ae2c649ba76e2db8f6d57c0cdab374265a0226d))
* update CI workflow to use absolute path for dependency installation ([c78f8d5](https://github.com/TheAggressive/CampaignBridge/commit/c78f8d5afee8248aadacacdbbee1b451f60aca48))
* update CI workflow to use correct command for dependency installation ([b3a102a](https://github.com/TheAggressive/CampaignBridge/commit/b3a102af180f84e95b39a50a9a45cac27cfb4006))
* update CI workflows and ESLint configuration for improved coverage and directory structure ([a4597c6](https://github.com/TheAggressive/CampaignBridge/commit/a4597c65bb95a34a801dcac689c4e68c426b0914))
* update coverage.yml to ensure correct paths for coverage reports ([ec87310](https://github.com/TheAggressive/CampaignBridge/commit/ec87310b00dd77f71049fe1aaabe79c58db5c028))
* update coverage.yml to use xdebug for coverage reporting ([d786436](https://github.com/TheAggressive/CampaignBridge/commit/d786436dc2d47908f7d52ea295a98a0fec0addce))
* update parameter and return type annotations in Form_Rest_Controller ([3bc1391](https://github.com/TheAggressive/CampaignBridge/commit/3bc139153b4f459145270e39d1ffe76f9599ce3c))
* use htmlspecialchars instead of esc_html in test bootstrap validation ([316d173](https://github.com/TheAggressive/CampaignBridge/commit/316d17353e39dc5b01351faae24f7f748f65508f))
* use standard WordPress testing setup script ([60a49df](https://github.com/TheAggressive/CampaignBridge/commit/60a49df058901007119a3ea6d80e5afbc39b2ab4))


### Features

* add MySQL service to CI workflow for database testing ([6af6c3b](https://github.com/TheAggressive/CampaignBridge/commit/6af6c3bbeba5d37fc06f3647d25efd87fe524b97))
* clean repository version without secret history ([8885c4a](https://github.com/TheAggressive/CampaignBridge/commit/8885c4a1e84ceb5273402cf64ce7cb5466c78659))
* introduce comprehensive CI/CD documentation and reorganization ([6fd9d2c](https://github.com/TheAggressive/CampaignBridge/commit/6fd9d2c1d1c5f7d73d21e09bb5a1a2514e2f2194))


### Performance Improvements

* replace wp-env with WordPress core testing suite for speed ([c3bc3d7](https://github.com/TheAggressive/CampaignBridge/commit/c3bc3d744185a20ebc11e1da26669f798a4633a0))

# Changelog

All notable changes to CampaignBridge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Version Management Guide

- **Unreleased**: Changes for upcoming releases go here
- **Version Numbers**: Use semantic versioning (MAJOR.MINOR.PATCH)
- **Release Process**:
  1. Move `[Unreleased]` section to `[new.version] - Release Date`
  2. Add new empty `[Unreleased]` section at top
  3. Update version in `package.json` and plugin header
  4. Create git tag and GitHub release

## [Unreleased]

### Added
- **Complete File Upload System**: Full WordPress file upload integration
  - Secure file processing with `wp_handle_upload()` and WordPress media library
  - Automatic WordPress attachment creation with metadata
  - Comprehensive security validation (MIME types, file size, malicious content detection)
  - Multiple file upload support with `multiple_files()` method
  - File type restrictions with `accept()` parameter and method chaining

- **Form Field Enhancements**: Improved developer experience for form fields
  - Direct `accept` parameter in `file()` method: `->file('field', 'Label', 'image/*')`
  - Enhanced file validation with configurable rules
  - Better error messages for file upload failures
  - Field-specific error handling and display

- **Version Sync System**: Automated version management
  - `pnpm version:sync` script automatically updates plugin header and PHP constant
  - Single source of truth in `package.json`
  - Prevents manual version update mistakes
  - Integrates with npm/pnpm version commands

- **Form Factory Pattern**: Extracted static factory methods
  - `Form_Factory::contact()`, `Form_Factory::register()`, `Form_Factory::settings_api()`
  - Cleaner separation of concerns from main Form class
  - Consistent API for common form patterns

- **Enhanced Security**: Built-in security checks throughout the codebase
  - Admin-only access to sensitive operations (API key decryption, file uploads)
  - Comprehensive input validation and sanitization
  - Nonce verification for all form submissions
  - File upload security with MIME type validation and malicious content detection

- **Form Data Preservation**: Fixed critical data preservation issues
  - Switch fields now use hidden inputs for reliable checked/unchecked state
  - Fixed array concatenation bug in form data merging (array_merge → array_replace)
  - Switch fields now properly sanitize as booleans like checkboxes
  - Repeater fields with switches maintain state correctly across form submissions

- **Accessibility Improvements**: WCAG 2.1 AA compliance enhancements
  - ARIA attributes for form fields and error states
  - Proper `<fieldset>` and `<legend>` for radio button groups
  - Screen reader compatible error messages
  - Keyboard navigation support
  - Unique IDs for form elements

- **Complete Form Styling System**: Professional, consistent styling for all form components
  - CSS variables-based design system with Tailwind CSS v4 integration
  - Global design tokens in `variables.css` for consistent theming
  - Modular component styling with separate CSS files for maintainability
  - BEM CSS class naming conventions throughout

- **Comprehensive Form Component Styling**:
  - **Text inputs**: Professional styling with focus states and validation
  - **Buttons**: Multiple variants (default, primary, outline, ghost, destructive)
  - **Radio buttons**: Native browser styling with accent-color theming
  - **Checkboxes**: Native browser styling with accent-color theming
  - **Switches/Toggles**: Custom styled toggle switches with smooth animations
  - **File inputs**: Button-like styling with file selection feedback
  - **Select dropdowns**: Enhanced styling with custom arrow icons
  - **Textareas**: Proper sizing and focus states
  - **Date/Time inputs**: Consistent styling across browsers

- **Design System Features**:
  - **Color theming**: Primary/secondary colors, semantic colors (success/warning/error)
  - **Spacing system**: Consistent spacing using CSS variables
  - **Typography**: Standardized font sizes, weights, and line heights
  - **Shadows**: Multiple shadow levels for depth
  - **Border radius**: Consistent corner rounding
  - **Focus states**: Accessible focus rings and keyboard navigation

- **Advanced Asset Optimization System**: Performance-enhancing asset loading optimizations
  - **Critical Resource Preloading**: Automatic preload link generation for critical CSS/JS
  - **Dynamic Path Discovery**: Intelligent multi-path search for critical resources
  - **HTTP Header Preloading**: Server-side preload headers for optimal loading priority
  - **Configurable Search Paths**: Customizable asset location discovery with `set_critical_paths()`
  - **WordPress Filter Integration**: `campaignbridge_critical_css_paths` and `campaignbridge_critical_js_paths` filters
  - **Fallback Support**: Multiple fallback locations (dist/, assets/) for maximum compatibility

- **Performance Optimizations**:
  - Single compiled CSS file instead of multiple individual files
  - Optimized CSS loading with proper dependencies
  - Removed hardcoded jQuery dependencies for better performance
  - Critical Resource Preloading: Faster page loads with automatic preload headers
  - Asset Loading Optimization: Intelligent dependency management and conditional loading

- **Accessibility Enhancements**:
  - Proper focus indicators for keyboard navigation
  - High contrast support with `prefers-contrast: high` media queries
  - Reduced motion support with `prefers-reduced-motion` queries
  - ARIA-compatible color schemes and focus states

### Changed
- **Method Naming Standardization**: Improved API consistency and clarity
  - `multipart()` → `enable_file_uploads()` (more descriptive)
  - `multiple()` → `multiple_files()` (file-specific context)
  - `options('prefix')` → `save_to_options('prefix')` (consistent with other save methods)
  - `meta($post_id)` → `save_to_post_meta($post_id)` (consistent naming)
  - `settings('group')` → `save_to_settings_api('group')` (clearer intent)
  - `custom($renderer)` → `render_custom($renderer)` (consistent with save methods)

- **Form Architecture Refactoring**: Better separation of concerns
  - Extracted factory methods into dedicated `Form_Factory` class
  - Improved error handling and validation flow
  - Enhanced form submission processing
  - Better field-specific error handling

- **File Upload Processing**: Leveraged WordPress native functions
  - Replaced custom upload logic with `wp_handle_upload()`
  - Integrated with WordPress media library via `wp_insert_attachment()`
  - Proper cleanup with `wp_delete_file()` instead of direct filesystem operations

- **Testing Framework Enhancements**: Improved test coverage and organization
  - Added dedicated accessibility test suite
  - Enhanced security testing with file upload scenarios
  - Better Brain Monkey integration for mocking WordPress functions
  - Improved test data management and cleanup

- **CSS Architecture**: Complete refactor from monolithic styles to modular, maintainable system
  - Split `components.css` into individual component files
  - Implemented nested BEM structure for better organization
  - Added `@reference "tailwindcss"` for proper utility access

- **Asset Management**: Optimized CSS enqueuing system
  - Consolidated multiple CSS files into single compiled output
  - Removed jQuery dependency from asset manager
  - Better dependency management with `.asset.php` files
  - Enhanced Form_Asset_Optimizer with dynamic critical resource preloading

### Fixed
- **Plugin Version Display**: Version now correctly shows in WordPress admin
  - Added missing `Version:` header in plugin file
  - Implemented automatic version sync system

- **Form Error Handling**: Better user experience for validation errors
  - Success messages no longer show when errors are present
  - Field-specific error messages with proper ARIA attributes
  - Improved error message accessibility

- **File Upload Issues**: Resolved multiple file upload problems
  - Fixed nested `$_FILES` array processing
  - Proper handling of single vs multiple file uploads
  - Better error reporting for upload failures

- **Accessibility Compliance**: Fixed WCAG 2.1 AA violations
  - Proper `for` attributes on labels matching input IDs
  - Correct `fieldset` and `legend` usage for radio groups
  - ARIA attributes for error states and required fields

- **Switch Styling**: Fixed background colors by properly importing global variables
- **File Input Appearance**: Transformed ugly browser inputs into professional buttons
- **Form Consistency**: All form elements now follow unified design language
- **Color Theming**: Fixed issues with accent colors and semantic color usage

### Security
- **Enhanced File Upload Security**: Comprehensive validation for uploaded files
  - MIME type verification against WordPress allowed types
  - File size limits and malicious content detection
  - Proper file permission handling
  - Admin-only access to sensitive file operations

- **Input Validation**: Improved sanitization throughout the application
  - All user inputs properly sanitized
  - SQL injection prevention with prepared statements
  - XSS prevention with output escaping

- **Access Control**: Strengthened permission checks
  - Admin-only access to sensitive operations
  - Proper capability verification
  - Secure nonce handling

### Performance
- **Optimized File Processing**: Efficient file upload handling
  - WordPress native upload functions for better performance
  - Proper cleanup and resource management
  - Reduced memory usage for large file operations

- **CSS Optimization**: Single compiled stylesheet reduces HTTP requests
- **Build Process**: Efficient asset compilation with PostCSS and Tailwind
- **Bundle Size**: Optimized CSS output with only used styles included
- **Critical Resource Preloading**: Faster page loads with automatic preload headers
- **Asset Loading Optimization**: Intelligent dependency management and conditional loading

### Documentation
- **Updated FORM.md**: Complete documentation refresh
  - All new method names and examples
  - File upload documentation with both syntax options
  - Enhanced examples and best practices

- **Developer Guidance**: Added comments and warnings
  - Version sync documentation in plugin header
  - Clear instructions for manual vs automatic version updates
  - Security best practices and warnings

- **Styling Guidelines**: Comprehensive CSS structure and naming conventions
- **Component Documentation**: Individual component styling and usage
- **Design Tokens**: Complete design system documentation

### Testing
- **File Upload Testing**: Comprehensive test coverage
  - Security validation testing for file uploads
  - Integration tests for complete upload workflows
  - Accessibility testing for file input fields

- **Enhanced Test Suites**: Improved test organization
  - Dedicated accessibility test class
  - Better mocking with Brain Monkey
  - Improved test data management

---
