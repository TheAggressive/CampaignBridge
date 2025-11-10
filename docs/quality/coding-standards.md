# CampaignBridge PHPCS Rules Documentation

This document outlines all the PHPCS (PHP CodeSniffer) rules and custom sniffs implemented for the CampaignBridge WordPress plugin.

## Overview

Our PHPCS configuration enforces WordPress coding standards, security best practices, and plugin-specific requirements. The rules are organized into categories and include both built-in WordPress standards and custom sniffs.

## Rule Categories

### 🔒 **Security Rules** (`WordPress.Security`)
- Enforces WordPress security best practices
- Checks for proper nonce validation, escaping, and sanitization
- **Severity**: Error

### 📚 **WordPress Core Standards** (`WordPress`, `WordPress-Extra`, `WordPress-Docs`)
- WordPress Coding Standards (WPCS)
- Includes naming conventions, function usage, and documentation
- **Severity**: Error/Warning (varies by rule)

### 🔧 **Custom CampaignBridge Sniffs**

#### 1. **StorageUsageSniff** (`CampaignBridge.Standard.Sniffs.Database.StorageUsage.ForbiddenStorageFunction`)
**Location**: `phpcs/CampaignBridge/Sniffs/Database/StorageUsageSniff.php`
**Severity**: Error

Enforces usage of CampaignBridge Storage wrapper instead of direct WordPress functions:

**❌ Forbidden (Direct WordPress calls):**
```php
get_option('key')
update_option('key', 'value')
get_transient('key')
set_transient('key', 'value', 3600)
get_post_meta($id, 'key', true)
update_post_meta($id, 'key', 'value')
get_user_meta($id, 'key', true)
wp_cache_get('key', 'group')
```

**✅ Required (Storage wrapper calls):**
```php
\CampaignBridge\Core\Storage::get_option('key')
\CampaignBridge\Core\Storage::update_option('key', 'value')
\CampaignBridge\Core\Storage::get_transient('key')
\CampaignBridge\Core\Storage::set_transient('key', 'value', 3600)
\CampaignBridge\Core\Storage::get_post_meta($id, 'key', true)
\CampaignBridge\Core\Storage::update_post_meta($id, 'key', 'value')
\CampaignBridge\Core\Storage::get_user_meta($id, 'key', true)
\CampaignBridge\Core\Storage::wp_cache_get('key', 'group')
```

#### 2. **HookUsageSniff** (`CampaignBridge.Standard.Sniffs.Hooks.HookUsage.InvalidHookParameters`)
**Location**: `phpcs/CampaignBridge/Sniffs/Hooks/HookUsageSniff.php`
**Severity**: Warning

Validates proper WordPress hook usage:
- Ensures hook functions have proper parameters
- Validates hook parameter structure
- Checks for proper hook registration patterns

**Monitored Functions:**
- `add_action()`, `add_filter()`
- `do_action()`, `apply_filters()`
- `remove_action()`, `remove_filter()`
- `remove_all_actions()`, `remove_all_filters()`

#### 3. **SecurityValidationSniff** (`CampaignBridge.Standard.Sniffs.Security.SecurityValidation.*`)
**Location**: `phpcs/CampaignBridge/Sniffs/Security/SecurityValidationSniff.php`
**Severity**: Warning

Enforces WordPress security best practices:

**Nonce Validation** (`MissingNonceVerification`):
- Requires `wp_verify_nonce()`, `check_ajax_referer()`, or `check_admin_referer()` before:
  - `wp_insert_post()`, `wp_update_post()`, `wp_delete_post()`
  - `update_option()`, `delete_option()`
  - `wp_create_user()`, `wp_update_user()`, `wp_delete_user()`

**Capability Checks** (`MissingCapabilityCheck`):
- Requires `current_user_can()` or `user_can()` before privileged operations
- Applies to post/user management and email sending functions

**Input Sanitization** (`UnsanitizedInput`):
- Warns about direct use of `$_POST`, `$_GET`, `$_REQUEST` without sanitization
- Recommends: `sanitize_text_field()`, `sanitize_email()`, `intval()`, `wp_kses()`, etc.

#### 4. **DatabaseOperationSniff** (`CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.*`)
**Location**: `phpcs/CampaignBridge/Sniffs/Database/DatabaseOperationSniff.php`
**Severity**: Error

Enforces proper database operations:

**❌ Forbidden Direct SQL** (`DirectSQLFunction`):
```php
mysql_query($sql)      // Forbidden
mysqli_query($link, $sql)  // Forbidden
PDO::query($sql)       // Forbidden
```

**✅ Required WordPress API** (`InvalidWpdbUsage`):
```php
$wpdb->get_var($sql)   // ✅ Correct
$wpdb->get_results($sql)  // ✅ Correct
$wpdb->prepare($sql, $param)  // ✅ Correct
```

**SQL Injection Prevention** (`PotentialSQLInjection`):
- Warns about string concatenation in SQL: `$sql = "SELECT * FROM table WHERE id = " . $id;`
- Warns about variable interpolation: `$sql = "SELECT * FROM table WHERE id = $id";`
- Recommends prepared statements: `$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id)`

### 🎯 **Performance & Best Practices**

#### **WordPress.Performance.SlowMetaQuery** (Warning)
- Detects inefficient meta queries that could impact performance
- Suggests using more efficient query patterns

#### **WordPress.DB.RestrictedClasses** (Error)
- Prevents use of restricted database classes
- Enforces WordPress database API usage

#### **WordPress.DB.RestrictedFunctions** (Error)
- Prevents use of restricted database functions
- Ensures proper WordPress database abstraction

### 🧹 **Code Quality Rules**

#### **Generic.Files.LineLength.TooLong** (Warning)
- Limits line length to maintain readability
- Configured as warning instead of error

#### **Generic.CodeAnalysis.UnusedFunctionParameter** (Warning)
- Detects unused function parameters
- Helps maintain clean, intentional code

#### **Generic.CodeAnalysis.UnconditionalIfStatement** (Warning)
- Identifies unnecessary if statements
- Promotes cleaner conditional logic

#### **Generic.PHP.NoSilencedErrors** (Warning)
- Discourages use of error suppression operators (`@`)
- Encourages proper error handling

#### **WordPress.WP.I18n.MissingTranslatorsComment** (Warning)
- Ensures translator comments for complex strings
- Improves WordPress internationalization

## Usage

### Running PHPCS
```bash
# Check PHP files
pnpm lint:php

# Auto-fix issues where possible
pnpm lint:php:fix

# Run full QA suite (includes PHPCS)
pnpm qa
```

### Error Code Format
All custom sniff violations use the format:
```
CampaignBridge.Standard.Sniffs.{Category}.{SniffName}.{ErrorCode}
```

Examples:
- `CampaignBridge.Standard.Sniffs.Database.StorageUsage.ForbiddenStorageFunction`
- `CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification`
- `CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectSQLFunction`

### VS Code Integration
PHPCS errors appear in VS Code with:
- Red squiggly underlines for violations
- Detailed error messages with fix suggestions
- Error codes for filtering and reference

## Configuration

### Files
- **Ruleset**: `phpcs.xml.dist`
- **Custom Sniffs**: `phpcs/CampaignBridge/Sniffs/{Category}/`
- **Package Commands**: `package.json`

### Severity Levels
- **Error**: Must be fixed (breaks builds)
- **Warning**: Should be addressed (code quality)

### Exclusions
Automatically excludes:
- `vendor/` - Third-party dependencies
- `node_modules/` - Node.js dependencies
- `dist/` - Build artifacts
- `assets/` - Static assets

## Benefits

### 🔒 **Security**
- Prevents SQL injection vulnerabilities
- Enforces nonce validation
- Ensures proper input sanitization
- Validates capability checks

### 🚀 **Performance**
- Optimizes database queries
- Prevents slow meta queries
- Encourages efficient WordPress API usage

### 🧹 **Code Quality**
- Maintains consistent WordPress coding standards
- Enforces plugin-specific patterns
- Prevents common mistakes
- Improves maintainability

### 🛡️ **Reliability**
- Catches storage operation misuse
- Validates hook usage patterns
- Ensures database operation safety
- Prevents runtime errors

## Continuous Integration

All PHPCS rules run automatically in CI/CD pipelines via:
```bash
pnpm qa  # Runs linting, static analysis, and tests
```

This ensures code quality standards are maintained across all development and deployment processes.
