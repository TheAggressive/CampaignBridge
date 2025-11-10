# CampaignBridge Unit Tests

Unit tests for CampaignBridge plugin components, focusing on isolated functionality testing without external dependencies.

## Overview

Unit tests validate individual components and methods in isolation, ensuring core functionality works correctly. These tests use mocking and stubbing to avoid external dependencies like database calls, file system operations, or network requests.

## Test Structure

```
tests/Unit/
├── Autoloader_Test.php          # Custom autoloader functionality
├── Form_Test.php               # Form API core functionality
├── Form_Builder_Test.php       # Form builder methods
├── Form_Field_Repeater_Test.php # Repeater field component
└── README.md                   # This file
```

## What Unit Tests Cover

### 🔧 Autoloader Tests (`Autoloader_Test.php`)
- **Registration/Unregistration**: SPL autoloader registration
- **Class Loading**: PSR-4 mapping for `CampaignBridge\` namespace
- **Security**: Directory traversal prevention and path validation
- **Caching**: Performance optimization with class map caching
- **Error Handling**: Graceful handling of invalid classes/files

### 📝 Form API Tests
- **Form_Test.php**: Core Form class functionality
  - Custom save callbacks
  - Method chaining and fluent interface
  - Error handling and warnings

- **Form_Builder_Test.php**: Form builder methods
  - Field method existence and chaining
  - Configuration validation
  - Save method setup

- **Form_Field_Repeater_Test.php**: Repeater field component
  - Stateless mode (2 arguments)
  - State-based mode (3 arguments)
  - Data persistence and normalization
  - Field type creation (switch, checkbox, radio, select)

## Running Unit Tests

### All Unit Tests
```bash
# Run all unit tests
pnpm test:any -- --testsuite=unit

# With verbose output
pnpm test:any -- --testsuite=unit --verbose
```

### Specific Test Classes
```bash
# Run only autoloader tests
pnpm test:any -- --filter=Autoloader_Test

# Run only form-related tests
pnpm test:any -- --filter="Form.*Test"

# Run specific test method
pnpm test:any -- --filter="test_autoloader_registration"
```

### With Coverage
```bash
# Generate coverage report for unit tests
pnpm test:any -- --testsuite=unit --coverage-html=coverage-unit
```

## Testing Patterns Used

### Mocking and Stubbing

Unit tests use PHPUnit mocking for external dependencies:

```php
// Example from Form_Test.php
$mock_container = $this->createMock(Form_Container::class);
$mock_container->method('get')
    ->willReturn($mock_service);

$form = new Form('test', [], $mock_container);
```

### Test Data Factories

Use test helpers for consistent test data:

```php
// Using Test_Case helper methods
$user_id = $this->create_test_user(['role' => 'administrator']);
$post_id = $this->create_test_post(['post_title' => 'Test Post']);
```

### Reflection for Private Methods

Test private methods using reflection when necessary:

```php
$reflection = new ReflectionClass(CampaignBridge_Autoloader::class);
$method = $reflection->getMethod('validate_class_path');
$method->setAccessible(true);

$result = $method->invoke(null, $test_path);
```

## Guidelines for Writing Unit Tests

### 1. Test Isolation
- Each test should be independent
- No shared state between tests
- Use `setUp()` and `tearDown()` for test preparation/cleanup

### 2. Descriptive Test Names
```php
// ✅ Good
public function test_autoloader_rejects_directory_traversal_attempts(): void

// ❌ Bad
public function test_autoloader_1(): void
```

### 3. Arrange-Act-Assert Pattern
```php
public function test_something_works(): void {
    // Arrange
    $input = 'test data';
    $expected = 'expected result';

    // Act
    $result = $this->system_under_test->process($input);

    // Assert
    $this->assertEquals($expected, $result);
}
```

### 4. Test Edge Cases
- Empty inputs
- Invalid data types
- Boundary conditions
- Error scenarios

### 5. Mock External Dependencies
```php
$mock_service = $this->createMock(Some_Service::class);
$mock_service->method('external_call')
    ->willReturn('mocked_response');

// Inject mock into system under test
$unit = new Unit_Under_Test($mock_service);
```

## Test Categories

### ✅ Functionality Tests
- Core business logic
- Method return values
- State changes
- Error conditions

### ✅ Security Tests
- Input validation
- Path traversal prevention
- Access control (integrated with security test suite)

### ✅ Performance Tests
- Caching behavior
- Memory usage (integrated with performance test suite)

## Integration with Other Test Suites

Unit tests complement other testing approaches:

- **Integration Tests** (`tests/Integration/`): Test component interactions
- **Security Tests** (`tests/Security/`): Security-specific validations
- **Performance Tests** (`tests/Performance/`): Performance benchmarks

## Common Test Helpers

### Test_Case Base Class
All unit tests extend `Test_Case` which provides:

- `create_test_user()` - Create test users
- `create_test_post()` - Create test posts
- `assert_hook_registered()` - Verify hooks are registered
- `mock_remote_request()` - Mock HTTP requests

### Test Factory
`Test_Factory` provides data generation:

```php
$template_data = Test_Factory::create_email_template_data([
    'post_title' => 'Custom Template'
]);
```

## Debugging Failed Tests

### Common Issues

1. **Class not found errors**
   - Ensure autoloader is registered in `setUp()`
   - Check namespace and file path mapping

2. **Mock expectations not met**
   - Verify mock setup matches actual calls
   - Use `->with()` to specify expected parameters

3. **Reflection errors**
   - Ensure class/method exists
   - Check method visibility (private methods need `setAccessible(true)`)

### Debugging Tools

```php
// Debug autoloader behavior
$autoloader = new CampaignBridge_Autoloader();
$reflection = new ReflectionClass($autoloader);
$property = $reflection->getProperty('class_map');
$property->setAccessible(true);
$map = $property->getValue($autoloader);

// Debug form configuration
$form = Form::make('test');
$config = $form->get_config(); // Use reflection if method is private
```

## Maintenance

### Adding New Unit Tests

1. **Identify testable unit**: Choose isolated functionality
2. **Create test file**: `ClassName_Test.php` in `tests/Unit/`
3. **Extend Test_Case**: Use base class for helpers
4. **Write tests**: Follow naming and structure conventions
5. **Run tests**: Ensure they pass and integrate with CI/CD

### Updating Existing Tests

- Keep tests in sync with code changes
- Update mocks when interfaces change
- Maintain test data when schemas change
- Review test coverage after refactoring

## Performance Considerations

- Unit tests should run quickly (< 100ms per test)
- Avoid expensive operations (database, network calls)
- Use mocks/stubs for external dependencies
- Parallel test execution is encouraged

## Related Documentation

- [Integration Tests](../Integration/README.md)
- [Security Tests](../Security/README.md)
- [Performance Tests](../Performance/README.md)
- [Testing Overview](../README.md)

---

**Unit tests ensure the building blocks of CampaignBridge work correctly in isolation.**
