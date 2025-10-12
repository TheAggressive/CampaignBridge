# Performance Tests

This directory contains performance tests for the CampaignBridge plugin. These tests measure and enforce performance budgets for critical code paths to ensure the plugin remains fast and responsive.

## Performance Budgets

### Execution Time Limits (milliseconds)
- **Email Generation (Simple)**: ≤500ms
- **Email Generation (Complex)**: ≤2000ms
- **REST API (Small Dataset)**: ≤100ms
- **REST API (Large Dataset)**: ≤500ms
- **Form Processing (Small)**: ≤200ms
- **Form Processing (Large)**: ≤1000ms
- **Block Registration**: ≤300ms

### Memory Usage Limits (MB)
- **Email Generation (Simple)**: ≤16MB
- **Email Generation (Complex)**: ≤32MB
- **REST API (Large Dataset)**: ≤8MB
- **Form Processing (Large)**: ≤16MB

## Test Categories

### 📧 Email Generation Performance
- **Simple templates**: Basic blocks (paragraphs, headings)
- **Complex templates**: Nested blocks, CampaignBridge components, images, buttons
- **CSS inlining**: Email-safe style processing
- **Responsive design**: Mobile optimization

### 🔌 REST API Performance
- **Small datasets**: 10-50 posts
- **Large datasets**: 100+ posts
- **Query optimization**: Database performance
- **Response formatting**: JSON serialization

### 📝 Form Processing Performance
- **Small forms**: 3-5 fields with basic validation
- **Large forms**: 20+ fields with complex validation
- **Data saving**: Options/meta storage performance
- **Security validation**: Nonce checking and capability validation

### 🧱 Block Registration Performance
- **Asset loading**: Script/style enqueueing
- **Block type registration**: WordPress block registry
- **Initialization time**: Plugin bootstrap performance

## Running Performance Tests

### Run All Performance Tests
```bash
pnpm test:any -- --testsuite=performance --verbose
```

### Run Specific Performance Test
```bash
pnpm test:any -- --filter=Performance_Test::test_email_generation_performance_simple
```

### Run Performance Tests in CI/CD
```bash
# In CI pipeline with performance monitoring
pnpm test:any -- --testsuite=performance --log-junit=performance-results.xml
```

### Compare Performance Over Time
```bash
# Generate performance baseline
pnpm test:any -- --testsuite=performance --coverage-php=performance-coverage.php

# Compare against baseline in future runs
# (Performance degradation will cause test failures)
```

## Test Data & Scenarios

### Email Generation Test Data
- **Simple**: 2-3 core blocks (paragraphs, headings)
- **Complex**: CampaignBridge blocks (container, post-card, buttons) with nested structures

### REST API Test Data
- **Small**: 10 published posts with standard metadata
- **Large**: 100+ posts with realistic content and metadata

### Form Processing Test Data
- **Small**: Contact form with name, email, message
- **Large**: Complex form with 20+ fields, validation rules, and file uploads

## Performance Monitoring

### Metrics Collected
- **Execution Time**: Wall-clock time in milliseconds
- **Memory Usage**: Peak memory consumption in MB
- **Database Queries**: Query count and execution time
- **HTTP Requests**: External API call performance

### Failure Conditions
Tests fail when performance exceeds budgets:
- **Execution time** exceeds millisecond limits
- **Memory usage** exceeds MB limits
- **Database queries** show N+1 problems
- **HTTP requests** timeout or are too slow

## Optimization Guidelines

### Email Generation
- Minimize DOM manipulation in CSS inlining
- Cache block processing results
- Use streaming for large HTML generation
- Optimize regex patterns in CSS processing

### REST API
- Use proper WordPress query optimization
- Implement result caching
- Limit response sizes
- Use efficient serialization

### Form Processing
- Validate early to avoid unnecessary processing
- Use batch database operations
- Cache validation rules
- Optimize file upload handling

## Maintenance

When performance degrades:
1. **Identify bottleneck** using performance test failures
2. **Profile code** with Xdebug or similar tools
3. **Implement optimization** (caching, query optimization, etc.)
4. **Update budgets** if legitimate improvements require it
5. **Add regression tests** for the specific optimization

When adding new features:
1. **Estimate performance impact** before implementation
2. **Add performance tests** for new critical paths
3. **Set appropriate budgets** based on use case
4. **Monitor in CI/CD** to catch regressions early

## Integration with CI/CD

Performance tests should be:
- **Run regularly** in CI pipeline
- **Monitored for trends** using historical data
- **Alerted on** when budgets are exceeded
- **Gated on PRs** to prevent performance regressions

Example CI configuration:
```yaml
performance-tests:
  stage: test
  script:
    - pnpm test:any -- --testsuite=performance
  artifacts:
    reports:
      junit: performance-results.xml
    expire_in: 1 week
  allow_failure: false  # Performance regressions should block merges
```
