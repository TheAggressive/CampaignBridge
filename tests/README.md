# CampaignBridge Test Suite

## Overview

This directory contains the PHPUnit test suite for the CampaignBridge WordPress plugin. The testing environment uses WP-Env for WordPress integration and follows WordPress coding standards.

## Directory Structure

```
tests/
├── Bootstrap.php           # PHPUnit bootstrap with Bootstrap class
├── helpers/                # Test utility classes
│   ├── test_case.php      # Base test class (Test_Case)
│   └── test_factory.php   # Test data factory (Test_Factory)
├── Unit/                   # Unit tests
│   └── Example_Test.php   # Example test (replace with real tests)
├── Integration/            # Integration tests
└── Legacy/                 # Legacy/compatibility tests
```

## Running Tests

### Using pnpm (Recommended)

```bash
# Run all tests
pnpm test

# Run specific test suites
pnpm test:unit              # Unit tests only
pnpm test:integration       # Integration tests only

# Generate coverage report
pnpm test:coverage

# Run quality assurance (lint + tests)
pnpm qa
```

### Direct PHPUnit

```bash
# Inside WP-Env container
wp-env run tests-cli bash -c 'cd wp-content/plugins/campaignbridge && ./vendor/bin/phpunit'
```

## Writing Tests

### 1. Unit Tests

Create test files in `tests/Unit/` that extend `Test_Case`:

```php
<?php
namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Tests\Helpers\Test_Factory;

class My_Feature_Test extends Test_Case {

    public function test_my_feature(): void {
        // Your test code here
        $this->assertTrue( true );
    }
}
```

### 2. Integration Tests

Create test files in `tests/Integration/` for testing plugin integrations:

```php
<?php
namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Tests\Helpers\Test_Case;

class My_Integration_Test extends Test_Case {

    public function test_wordpress_integration(): void {
        // Test WordPress hooks, database, etc.
    }
}
```

### 3. Test Helpers

#### Test_Case Methods

- `create_test_post( $args )` - Create test posts
- `create_test_user( $args )` - Create test users
- `assert_option_equals( $name, $value )` - Assert option values
- `assert_post_meta_equals( $id, $key, $value )` - Assert post meta
- `mock_remote_request( $response, $status )` - Mock HTTP requests
- `get_reflection_method( $class, $method )` - Access private methods
- `get_reflection_property( $class, $property )` - Access private properties

#### Test_Factory Methods

- `Test_Factory::create_email_template_data( $overrides )`
- `Test_Factory::create_campaign_data( $overrides )`
- `Test_Factory::create_settings_data( $overrides )`
- `Test_Factory::create_subscriber_data( $overrides )`
- `Test_Factory::create_mailchimp_response( $endpoint, $overrides )`
- `Test_Factory::create_rest_response( $data, $status )`
- `Test_Factory::create_block_content( $name, $attributes, $content )`

## Test Naming Conventions

### Files
- Use `snake_case` for file names: `my_feature_test.php`
- Place in appropriate directory: `Unit/`, `Integration/`, `Legacy/`

### Classes
- Use `PascalCase_With_Underscores`: `My_Feature_Test`
- Always extend `Test_Case`
- Add namespace: `CampaignBridge\Tests\Unit`

### Methods
- Use `snake_case`: `test_my_feature_works()`
- Prefix with `test_`
- Be descriptive: `test_user_registration_sends_email()`

## Environment Setup

The test environment is automatically configured by `Bootstrap.php`:

1. **WordPress Test Suite**: Loads WordPress testing framework
2. **Plugin Loading**: Automatically loads CampaignBridge plugin
3. **Test Helpers**: Loads Test_Case and Test_Factory classes
4. **Database**: Clean database for each test run
5. **Hooks**: WordPress hooks are available for testing

## Best Practices

### 1. Test Organization
- Group related tests in the same class
- Use descriptive test method names
- Add PHPDoc comments explaining complex tests

### 2. Test Data
- Use `Test_Factory` for creating consistent test data
- Clean up after tests (done automatically by `Test_Case`)
- Use unique values to avoid conflicts

### 3. Assertions
- Use specific assertions (`assertEquals` vs `assertTrue`)
- Test both positive and negative cases
- Include edge cases and error conditions

### 4. Performance
- Keep unit tests fast (< 1 second each)
- Use mocking for external dependencies
- Group slow tests in Integration suite

## Debugging Tests

### Enable Debug Output
```php
// In test methods
error_log( 'Debug info: ' . print_r( $data, true ) );
```

### Run Single Test
```bash
wp-env run tests-cli bash -c 'cd wp-content/plugins/campaignbridge && ./vendor/bin/phpunit tests/Unit/My_Feature_Test.php'
```

### Verbose Output
```bash
wp-env run tests-cli bash -c 'cd wp-content/plugins/campaignbridge && ./vendor/bin/phpunit --verbose'
```

## Coverage Reports

Generate HTML coverage reports:

```bash
pnpm test:coverage
```

Reports are saved to `coverage/html/index.html`

## Continuous Integration

Tests run automatically in CI/CD pipelines. Ensure all tests pass before committing code:

```bash
pnpm qa  # Run linting + tests
```

---

**Note**: Remove `tests/Unit/Example_Test.php` when you add real tests.

