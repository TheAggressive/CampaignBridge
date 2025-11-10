# Conditional Fields Module

A high-performance, accessible, and enterprise-grade conditional fields system for WordPress forms. Built with TypeScript, featuring advanced caching, WCAG 2.1 AA accessibility, comprehensive security, and extensive testing interfaces.

## 🚀 Features

- **⚡ High Performance**: LRU caching with hash-based keys (10-100x faster)
- **♿ WCAG 2.1 AA Compliant**: Full accessibility with screen reader support
- **🛡️ Enterprise Security**: Input validation, XSS prevention, custom error handling
- **📊 Performance Monitoring**: Real-time metrics and memory tracking
- **🧪 Testing Ready**: Comprehensive interfaces and mock implementations
- **🔧 Highly Configurable**: Centralized configuration management
- **🎯 Type Safe**: Full TypeScript support with strict typing

## 📦 Installation

### Automatic (WordPress Plugin)

The conditional fields module is automatically included when you install the CampaignBridge plugin:

```bash
# Install via Composer
composer require vendor/campaignbridge-plugin

# Or install via WordPress admin
# Upload campaignbridge.zip and activate
```

### Manual Installation

```bash
# Clone or download the plugin
git clone https://github.com/your-org/campaignbridge.git
cd campaignbridge

# Install dependencies
npm install
composer install

# Build assets
npm run build
```

## 🎯 Quick Start

### Basic HTML Setup

```html
<form id="my-form" data-conditional>
    <!-- Regular fields -->
    <input type="text" name="my-form[username]" />
    <input type="email" name="my-form[email]" />

    <!-- Conditional fields -->
    <div class="campaignbridge-conditional-field">
        <input type="text" name="my-form[api_key]" />
    </div>

    <div class="campaignbridge-conditional-field">
        <select name="my-form[api_provider]">
            <option value="openai">OpenAI</option>
            <option value="anthropic">Anthropic</option>
        </select>
    </div>
</form>
```

### JavaScript Initialization

```javascript
// Automatic initialization (default)
document.addEventListener('DOMContentLoaded', () => {
    // Finds all forms with data-conditional attribute
    // No manual initialization needed!
});

// Manual initialization (advanced)
import { ConditionalEngine } from 'campaignbridge/conditional-fields';

const engine = new ConditionalEngine('my-form');
```

## ⚙️ Configuration

### Global Configuration

```javascript
import { configManager } from 'campaignbridge/conditional-fields';

// Update global settings
configManager.updateConfig({
    debounceDelay: 200,        // Form change debounce (ms)
    requestTimeout: 10000,     // API timeout (ms)
    cacheSize: 50,             // Cache size limit
    maxRetries: 3,             // Max API retries
    enableDebugLogging: true,  // Debug mode
    enablePerformanceMonitoring: true, // Performance tracking
});
```

### Per-Form Configuration

```html
<!-- Enable debug logging for specific form -->
<form id="debug-form" data-conditional data-conditional-engine="v2">
    <!-- Form fields -->
</form>
```

## 🔧 Advanced Usage

### Custom Validation Rules

```javascript
import { configManager } from 'campaignbridge/conditional-fields';

// Add custom validation rules
configManager.updateConfig({
    validationRules: {
        'api_key': {
            required: true,
            minLength: 20,
            pattern: /^[A-Za-z0-9_-]+$/,
            customValidator: (value) => {
                // Custom validation logic
                return value.startsWith('sk-');
            },
            errorMessage: 'API key must start with "sk-"'
        }
    }
});
```

### Performance Monitoring

```javascript
import { performanceMonitor } from 'campaignbridge/conditional-fields';

// Get performance stats
const stats = performanceMonitor.getStats();
console.log('Cache hits:', stats.totalMetrics);

// Generate performance report
const report = performanceMonitor.generateReport();
console.log('Average response time:', report.summary.averageResponseTime);
```

### Cache Management

```javascript
import { conditionalCache } from 'campaignbridge/conditional-fields';

// Get cache statistics
const cacheStats = conditionalCache.getStats();
console.log('Hit rate:', cacheStats.hitRate);

// Clear cache programmatically
conditionalCache.clear();
```

## ♿ Accessibility Features

The module provides comprehensive WCAG 2.1 AA accessibility:

### Screen Reader Support

```javascript
// Screen readers automatically announce:
// - Field visibility changes
// - Validation errors
// - Loading states
// - Form submission status
```

### Keyboard Navigation

- **Tab Navigation**: Full keyboard support through all fields
- **Skip Links**: Quick navigation to main content areas
- **Focus Management**: Proper focus restoration after field changes
- **ARIA Labels**: Comprehensive ARIA attributes for screen readers

### Semantic HTML

```html
<!-- Automatic landmark creation -->
<form role="main" aria-labelledby="form-title">
    <fieldset aria-describedby="conditional-fields-info">
        <!-- Conditional fields with proper ARIA -->
    </fieldset>
</form>
```

## 🧪 Testing

### Using Testing Interfaces

```typescript
import {
    IConditionalEngine,
    IConditionalCache,
    MockConditionalCache,
    MockPerformanceMonitor
} from 'campaignbridge/conditional-fields';

// Create mock implementations
const mockCache = new MockConditionalCache();
const mockMonitor = new MockPerformanceMonitor();

// Use in tests
describe('ConditionalEngine', () => {
    it('should handle form changes', async () => {
        const engine = new ConditionalEngine('test-form', {
            cache: mockCache,
            monitor: mockMonitor
        });

        // Test form interactions
        await engine.evaluateConditions();
    });
});
```

### Testing with Jest/Vitest

```typescript
import { describe, it, expect, beforeEach } from 'vitest';
import { ConditionalEngine, MockConditionalCache } from '../src';

describe('Conditional Fields', () => {
    let mockCache: MockConditionalCache;

    beforeEach(() => {
        mockCache = new MockConditionalCache();
    });

    it('caches evaluation results', () => {
        const formData = { field1: 'value1' };
        const result = { success: true, fields: {} };

        mockCache.set(formData, result);

        expect(mockCache.get(formData)).toEqual(result);
    });
});
```

## 🔒 Security

### Input Sanitization

All form inputs are automatically sanitized:

```javascript
import { DataSanitizer } from 'campaignbridge/conditional-fields';

// Manual sanitization (rarely needed)
const cleanHtml = DataSanitizer.sanitizeHtml('<script>alert("xss")</script>');
const cleanSql = DataSanitizer.sanitizeSqlLike('user_input%');
```

### Error Handling

Custom error types for different scenarios:

```typescript
import {
    ApiTimeoutError,
    ApiValidationError,
    NetworkError,
    RateLimitError
} from 'campaignbridge/conditional-fields';

try {
    await engine.evaluateConditions();
} catch (error) {
    if (error instanceof ApiTimeoutError) {
        console.log('Request timed out:', error.message);
    } else if (error instanceof RateLimitError) {
        console.log('Too many requests');
    }
}
```

## 📊 API Reference

### ConditionalEngine

```typescript
class ConditionalEngine {
    constructor(formId: string, config?: Partial<ConditionalEngineConfig>);

    evaluateConditions(): Promise<void>;
    destroy(): void;
    getCacheStats(): CacheStats;
}
```

### Configuration Options

```typescript
interface ConditionalEngineConfig {
    formId: string;
    apiEndpoint?: string;
    ajaxAction?: string;
    debounceDelay?: number;      // Default: 100ms
    requestTimeout?: number;     // Default: 30000ms
    cacheSize?: number;          // Default: 10 items
    maxRetries?: number;         // Default: 3
    enableDebugLogging?: boolean;
    enablePerformanceMonitoring?: boolean;
    validationRules?: Record<string, ValidationRule>;
}
```

### Cache Statistics

```typescript
interface CacheStats {
    hits: number;
    misses: number;
    evictions: number;
    totalSize: number;
    itemCount: number;
    hitRate: number;
}
```

## 🎨 Customization Examples

### Custom Styling

```css
/* Custom conditional field styles */
.campaignbridge-conditional-field {
    transition: opacity 0.3s ease;
}

.campaignbridge-conditional-hidden {
    opacity: 0;
    pointer-events: none;
}

/* Loading indicator */
.campaignbridge-loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
```

### Custom Validation

```typescript
import { FormValidator } from 'campaignbridge/conditional-fields';

// Add custom validation rules
const validator = new FormValidator();

validator.addRule('custom_field', {
    required: true,
    customValidator: (value, context) => {
        // Your custom validation logic
        return value.length > 5 && /^[A-Z]/.test(value);
    },
    errorMessage: 'Field must start with uppercase letter and be > 5 chars'
});
```

### Event Listeners

```javascript
// Listen for conditional field events
document.addEventListener('campaignbridge:fieldShown', (event) => {
    console.log('Field shown:', event.detail.fieldId);
});

document.addEventListener('campaignbridge:fieldHidden', (event) => {
    console.log('Field hidden:', event.detail.fieldId);
});

document.addEventListener('campaignbridge:validationError', (event) => {
    console.log('Validation error:', event.detail.errors);
});
```

## 🚨 Troubleshooting

### Common Issues

**Fields not updating**
```javascript
// Check if form has correct data attribute
<form id="my-form" data-conditional>
//                    ^^^^^^^^^^^^^^^^ Required!
```

**Performance issues**
```javascript
// Enable performance monitoring
configManager.updateConfig({
    enablePerformanceMonitoring: true
});

// Check cache hit rate
const stats = conditionalCache.getStats();
console.log('Cache hit rate:', stats.hitRate);
```

**Accessibility issues**
```javascript
// Enable debug logging
configManager.updateConfig({
    enableDebugLogging: true
});

// Check accessibility status
const engine = new ConditionalEngine('my-form');
const status = engine.getAccessibilityStatus();
console.log('Accessibility status:', status);
```

## 📈 Performance Optimization

### Cache Tuning

```typescript
// Optimize for high-traffic sites
configManager.updateConfig({
    cacheSize: 100,           // Larger cache
    debounceDelay: 50,        // Faster response
    requestTimeout: 5000,     // Shorter timeout
});
```

### Memory Management

```typescript
// Monitor memory usage
performanceMonitor.recordMemoryUsage();

// Clean up when done
engine.destroy(); // Properly cleans up event listeners
```

### Bundle Optimization

```javascript
// Tree-shaking for smaller bundles
import { ConditionalEngine } from 'campaignbridge/conditional-fields/core';
// Only imports core functionality, excludes optional features
```

## 🤝 Contributing

### Development Setup

```bash
# Install dependencies
npm install
composer install

# Run tests
npm test

# Build for production
npm run build

# Development watch mode
npm run dev
```

### Code Standards

- **TypeScript**: Strict mode enabled
- **ESLint**: WordPress coding standards
- **Prettier**: Consistent formatting
- **Testing**: 100% coverage required

### Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Update documentation
6. Submit pull request

## 📄 License

GPL-2.0-or-later - See LICENSE file for details.

## 🆘 Support

- **Documentation**: [Full API Docs](./api-docs.md)
- **Issues**: [GitHub Issues](https://github.com/your-org/campaignbridge/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-org/campaignbridge/discussions)

---

**Built with ❤️ for WordPress developers who demand performance, accessibility, and security.**
