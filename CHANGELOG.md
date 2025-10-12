# Changelog

All notable changes to CampaignBridge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - Testing & Architecture Overhaul

### Added
- **Class-Based Autoloader**: Complete refactor from procedural to object-oriented autoloader
  - Enhanced security with directory traversal prevention
  - Performance optimizations with class map caching
  - Better error handling and logging
  - PSR-4 compliant namespace mapping
  - File path validation and security checks

- **Comprehensive Testing Suite**: Enterprise-grade test coverage across multiple dimensions
  - **Unit Tests**: 4 test classes covering autoloader, form API, and core components
  - **Integration Tests**: 5 test classes for form submissions, REST API, admin screens, settings persistence, and block registration
  - **Security Tests**: Dedicated security test suite with API key encryption, authentication, authorization, input validation, and access control
  - **Performance Tests**: Performance benchmarks with budgets for email generation, REST API calls, form processing, and block registration

- **Test Organization & Documentation**: Professional test structure with comprehensive documentation
  - Separate test directories: `tests/Unit/`, `tests/Integration/`, `tests/Security/`, `tests/Performance/`
  - Detailed README files for each test suite explaining purpose, organization, and usage
  - Test helpers and factories for consistent test data generation
  - CI/CD-ready test configuration with PHPUnit suites

- **Security Testing Framework**: Built-in security validation for all critical operations
  - API key encryption/decryption access control (admin-only)
  - REST API endpoint authentication and authorization
  - Form submission nonce validation and security checks
  - Input sanitization and XSS prevention validation
  - Admin screen capability requirements
  - User data isolation and access control
  - Error message security (no sensitive data leakage)

- **Performance Monitoring**: Automated performance testing and monitoring
  - Performance budgets for critical operations (<2s email generation, <100ms form processing)
  - Memory usage tracking and leak detection
  - Execution time monitoring with thresholds
  - Caching behavior validation
  - Scalability testing for large datasets

- **New `repeater()` method** for Form API with smart state management
  - Creates multiple fields of the same type with intuitive fluent interface
  - Supports stateless mode (2 arguments) and state-based mode (3 arguments)
  - Automatically compares persistent data with available choices
  - Intelligently handles stale data (removed options)
  - Supports `->default('key')` modifier for setting default checked choice
  - Selection-based field types: `->switch()`, `->checkbox()`, `->radio()`, `->select()`
  - Comprehensive input validation with helpful error messages
  - Full PHPUnit test coverage (20 tests, 69 assertions)

### Changed
- **Autoloader Architecture**: Converted from procedural functions to `CampaignBridge_Autoloader` class
  - Renamed `includes/autoload.php` → `includes/Autoload.php`
  - Added static methods for registration, unregistration, and cache management
  - Enhanced security validation and path checking
  - Improved error logging and debugging capabilities

- **Test Infrastructure**: Complete overhaul of testing approach
  - Migrated from basic unit tests to comprehensive multi-suite testing
  - Added test organization with dedicated directories and documentation
  - Implemented test helpers and factories for better test maintainability
  - Added performance and security testing frameworks

- Migrated post-types.php screen from `multiple()` to `repeater()` method
- Updated FORM.md documentation with comprehensive repeater examples
- Repeater fields use `field___key` naming convention instead of `field[key]` to avoid PHP array parsing issues
- **Improved method naming for data storage:**
  - `->options('prefix')` → `->save_to_options('prefix')` (more descriptive)
  - `->meta($post_id)` → `->save_to_post_meta($post_id)` (more descriptive)
  - `->settings('group')` → `->save_to_settings_api('group')` (more descriptive)
  - **New:** `->save_to_custom($callback)` for external APIs and custom storage
  - **New:** `->render_custom($renderer)` for advanced custom layouts (renamed from `custom()`)

### Removed
- **Procedural Autoloader**: Replaced with class-based implementation
  - Removed `campaignbridge_autoloader()` function
  - Removed `campaignbridge_validate_class_path()` function
  - Removed `campaignbridge_validate_file_path()` function
  - Removed `campaignbridge_log_autoload_error()` function

- **`multiple()` method** has been completely removed from Form_Builder
  - **BREAKING CHANGE**: All code using `multiple()` must migrate to `repeater()`
  - Migration: `->multiple('field', 'switch', $choices, $defaults)` becomes `->repeater('field', $choices, $defaults)->switch()`
- **Deprecated method aliases** have been removed after full migration:
  - `->options('prefix')` - replaced with `->save_to_options('prefix')`
  - `->meta($post_id)` - replaced with `->save_to_post_meta($post_id)`
  - `->settings('group')` - replaced with `->save_to_settings_api('group')`
  - `->custom($renderer)` - replaced with `->render_custom($renderer)`
  - Legacy `field[key]` naming support removed from Form_Handler
- Text-based input types removed from repeater (text, email, password, textarea)
  - Repeater is now focused on selection-only fields (switch, checkbox, radio, select)

### Fixed
- **Radio and select repeater fields** now correctly create ONE field with options instead of multiple individual fields
  - Radio fields: Create a single radio group where one option can be selected
  - Select fields: Create a single dropdown where one option can be selected
  - Fixes "No options configured" error when using `->radio()` or `->select()` in repeaters

### Security
- **Enhanced Security Testing**: Comprehensive security validation framework
- **API Key Protection**: Admin-only access to encryption/decryption operations
- **Input Validation**: XSS prevention and sanitization testing
- **Access Control**: Capability-based security testing for all operations
- **Data Isolation**: User data isolation and access control validation

### Performance
- **Performance Testing Framework**: Automated performance monitoring with budgets
- **Caching Validation**: Cache behavior testing and optimization
- **Memory Usage Monitoring**: Memory leak detection and optimization
- **Execution Time Tracking**: Performance regression detection

### Testing
- **Test Coverage**: Added 50+ tests across unit, integration, security, and performance suites
- **Test Organization**: Structured test directories with comprehensive documentation
- **CI/CD Ready**: PHPUnit configuration with test suites and coverage reporting
- **Test Helpers**: Reusable test utilities and data factories

## [Unreleased]

## [0.2.0] - Initial Release

### Added
- Initial release of CampaignBridge plugin
- Form API with builder pattern
- Admin screens and controllers
- Post types integration
- Settings management

---
