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
