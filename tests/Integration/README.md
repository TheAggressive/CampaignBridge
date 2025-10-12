# Integration Tests

This directory contains integration tests for the CampaignBridge plugin. Integration tests verify that different components work together correctly in a real WordPress environment, testing complete workflows and interactions between multiple systems.

## Test Categories

### 🖥️ Admin Interface Integration
- **Admin_Screens_Test.php**: Tests admin screen rendering and functionality
- **Settings_Persistence_Test.php**: Tests settings forms with real data persistence

### 🔧 WordPress Integration
- **Block_Registration_Test.php**: Tests WordPress block registration and editor integration
- **Form_Submission_Test.php**: Tests complete form submission workflows

### 🌐 API Integration
- **REST_API_Test.php**: Tests REST API endpoints with authentication and data processing

## Test Characteristics

### Real Environment Testing
All integration tests run in a fully functional WordPress environment with:
- Real WordPress core functions and APIs
- Actual database operations
- WordPress admin interface simulation
- Plugin hooks and filters

### End-to-End Workflows
Tests complete user workflows rather than isolated units:
- Form submission from start to finish
- Admin screen loading with real data
- API requests with authentication
- Block registration in editor context

### Component Interaction
Verifies that different plugin components work together:
- Admin screens with controllers and data
- Forms with validation, security, and persistence
- REST APIs with authentication and rate limiting
- Blocks with editor and rendering systems

## Running Integration Tests

### Run All Integration Tests
```bash
pnpm test:any -- --testsuite=integration --verbose
```

### Run Specific Integration Test
```bash
pnpm test:any -- --filter=Admin_Screens_Test::test_status_screen_loads_with_real_data
```

### Run Integration Tests in Isolation
```bash
# Test only integration functionality
pnpm test:any -- --testsuite=integration
```

## Test Data & Setup

### WordPress Environment
- Full WordPress installation with admin user
- Plugin activated and initialized
- Admin screens and menus registered
- REST API endpoints available

### Test Fixtures
- Realistic post content and metadata
- Complete form configurations
- Valid admin user sessions
- Proper WordPress nonces

### Cleanup
All tests include proper cleanup to prevent interference:
- Test posts and data removed
- Options and transients cleared
- User sessions reset

## Integration Test Guidelines

### When to Write Integration Tests
Write integration tests for:
- Complete user workflows
- Component interactions
- External API integrations
- WordPress core integrations
- Data persistence across requests

### When to Use Unit Tests Instead
Use unit tests for:
- Isolated class methods
- Pure logic without dependencies
- Complex algorithms
- Data transformation functions

## Test Organization

### File Naming Convention
- `{Component}_Test.php` - Tests for specific components
- Integration tests focus on how components work together
- Security tests moved to dedicated `tests/Security/` directory
- Performance tests in dedicated `tests/Performance/` directory

### Test Structure
Each integration test includes:
- **Setup**: WordPress environment and test data
- **Execution**: Real component interaction
- **Verification**: End-to-end functionality validation
- **Cleanup**: Test data removal

## Dependencies & Requirements

### WordPress Integration
- WordPress test environment (`wp-env`)
- Admin user capabilities
- Plugin activation hooks
- WordPress REST API

### External Dependencies
- File system access for block assets
- Database access for persistence
- HTTP client for API testing
- WordPress admin interface

## Troubleshooting

### Common Issues
- **Permission errors**: Ensure proper admin user setup
- **Hook timing**: Verify plugin initialization order
- **Data persistence**: Check database cleanup between tests
- **Asset loading**: Confirm block assets are properly enqueued

### Debug Tips
- Use `--verbose` flag for detailed output
- Check WordPress debug logs
- Verify test data cleanup
- Confirm plugin activation status

## Maintenance

When adding new integration tests:
1. Follow existing naming conventions
2. Include proper setup and cleanup
3. Test complete workflows, not isolated units
4. Document any special requirements
5. Update this README with new test categories

When modifying existing tests:
1. Maintain backward compatibility
2. Update documentation if workflows change
3. Verify all dependencies are still valid
4. Test in full WordPress environment
