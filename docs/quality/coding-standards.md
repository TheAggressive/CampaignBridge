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

Custom sniffs exist only for CampaignBridge architectural boundaries that the
standard rulesets do not know about. Nonce verification, input sanitization,
and output escaping are enforced by the maintained `WordPress.Security` sniffs
above, not by custom heuristics. Repository persistence boundaries are also
checked by `bin/ci/check-repository-boundary.sh`.

| Sniff | Severity | Boundary enforced |
|---|---|---|
| `Database/DatabaseOperationSniff` | Error | No raw `mysql*`/PDO SQL; `$wpdb` writes and interpolated SQL stay in repository/Core storage classes |
| `Database/DirectDatabaseQuerySniff` | Warning | Direct `$wpdb` methods and properties stay in repository/Core storage classes |
| `Http/DirectHttpRequestSniff` | Warning | Outbound HTTP goes through `Core\Http_Client`, which enforces the declared trusted origin and redirect policy |
| `Logging/DirectLoggingSniff` | Warning | Logging goes through `Core\Error_Handler`, which sanitizes context |
| `Assets/AssetEnqueueSniff` | Warning | Admin assets are enqueued through the asset manager |

Removed: `SecurityValidationSniff` looked for `wp_verify_nonce()` lexically
inside the same function as `update_option()` and similar calls, so it flagged
correct code whose nonce was verified by the caller, and it accepted a nonce as
a capability check. `HookUsageSniff` only checked that `add_action` was
followed by `(`. Both produced suppressions without catching defects.

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
- `CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectSQLFunction`
- `CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction`

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
