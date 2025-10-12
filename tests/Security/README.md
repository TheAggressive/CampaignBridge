# Security Tests

This directory contains comprehensive security tests for the CampaignBridge plugin. These tests verify that all security mechanisms are properly implemented and functioning correctly.

## Test Categories

### 🔐 Authorization & Authentication
- API key decryption requires admin permissions
- REST API endpoints enforce proper authentication
- Admin screens require appropriate capabilities
- User session isolation and access controls

### 🛡️ Input Validation & Sanitization
- Form submissions validate nonces correctly
- Input sanitization prevents XSS attacks
- File uploads are properly validated
- SQL injection prevention through prepared statements

### 🔒 Data Protection
- Sensitive data encryption/decryption works correctly
- Error messages don't expose sensitive information
- Data isolation between users is enforced
- Settings persistence maintains security boundaries

### 🚫 Access Control
- Capability checks prevent unauthorized access
- Rate limiting protects against abuse
- Cross-site request forgery (CSRF) protection
- File upload security and validation

## Running Security Tests

### Run All Security Tests
```bash
pnpm test:integration -- --testsuite=security
```

### Run Specific Security Test
```bash
pnpm test:integration -- --filter=Security_Test::test_api_key_decryption_for_display_requires_admin_permissions
```

### Run Security Tests in CI/CD
```bash
# In CI pipeline
vendor/bin/phpunit --testsuite=security --coverage-html=security-coverage
```

## Test Philosophy

Security tests follow these principles:

1. **Real Code Testing**: All tests verify actual security mechanisms in the codebase, not hypothetical features
2. **Comprehensive Coverage**: Tests span authentication, authorization, input validation, and data protection
3. **Integration Focus**: Tests verify security works across component boundaries
4. **Regression Prevention**: Tests ensure security fixes remain effective over time

## Security Test Helpers

The security tests use specialized helper methods for common security testing scenarios:

- `create_test_user()` - Creates users with specific roles for permission testing
- `simulate_admin_screen_load()` - Sets up WordPress admin context for screen testing
- `wp_set_current_user()` - Switches user context for access control testing

## Compliance & Standards

These tests help ensure compliance with:
- WordPress security best practices
- OWASP Top 10 vulnerabilities
- GDPR data protection requirements
- PCI DSS for payment processing (if applicable)

## Maintenance

When adding new security features:
1. Add corresponding security tests to this directory
2. Update this README with new test categories
3. Ensure tests run in CI/CD pipeline
4. Review test coverage for new security mechanisms
