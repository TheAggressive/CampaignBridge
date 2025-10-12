# Changelog

All notable changes to CampaignBridge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.2] - File Upload System & API Enhancements

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

- **Accessibility Improvements**: WCAG 2.1 AA compliance enhancements
  - ARIA attributes for form fields and error states
  - Proper `<fieldset>` and `<legend>` for radio button groups
  - Screen reader compatible error messages
  - Keyboard navigation support
  - Unique IDs for form elements

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

### Documentation
- **Updated FORM.md**: Complete documentation refresh
  - All new method names and examples
  - File upload documentation with both syntax options
  - Enhanced examples and best practices

- **Developer Guidance**: Added comments and warnings
  - Version sync documentation in plugin header
  - Clear instructions for manual vs automatic version updates
  - Security best practices and warnings

### Testing
- **File Upload Testing**: Comprehensive test coverage
  - Security validation testing for file uploads
  - Integration tests for complete upload workflows
  - Accessibility testing for file input fields

- **Enhanced Test Suites**: Improved test organization
  - Dedicated accessibility test class
  - Better mocking with Brain Monkey
  - Improved test data management

## [Unreleased]

## [0.2.0] - Initial Release

### Added
- Initial release of CampaignBridge plugin
- Form API with builder pattern
- Admin screens and controllers
- Post types integration
- Settings management

---
