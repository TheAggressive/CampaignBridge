# Changelog

All notable changes to CampaignBridge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
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
- N/A

## [1.0.0] - Initial Release

### Added
- Initial release of CampaignBridge plugin
- Form API with builder pattern
- Admin screens and controllers
- Post types integration
- Settings management

---
