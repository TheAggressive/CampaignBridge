# CampaignBridge Real-Time Form Validation

This document explains how to use the real-time form validation system for instant user feedback across all CampaignBridge form fields.

## Features

- **Real-time validation** with debounced input checking and result caching
- **Comprehensive rule system** supporting all common validation types
- **Validation groups** for targeted validation of field subsets
- **Memory management** with automatic cleanup of event listeners and caches
- **Specific error types** with detailed validation error classes
- **Enhanced accessibility** with ARIA attributes, screen reader announcements, and keyboard navigation
- **Performance optimized** with DOM caching and validation result caching
- **TypeScript-first** with full type safety
- **Framework agnostic** works with any form field implementation

## Quick Start

### 1. Include the validation system

```javascript
// In your admin script
import { FormValidator, initializeFormValidation } from '../validation';

// Initialize validation on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeFormValidation();
});
```

### 2. Use validation groups for targeted validation

```javascript
import { FormValidator } from '../validation';

// Create validator instance
const validator = FormValidator.getInstance();

// Validate only login-related fields
const loginResults = await validator.validateGroup('login');

// Validate multiple groups
const results = await validator.validateGroup(['personal', 'contact']);

// Get all available groups
const groups = validator.getValidationGroups();

// Get fields in specific group
const fields = validator.getFieldsByGroup('billing');
```

### 3. Add validation rules to your form fields

```php
// In your PHP form field configuration
$field = new Form_Field_Input([
    'id' => 'email_field',
    'name' => 'email',
    'type' => 'email',
    'label' => 'Email Address',
    'validation' => [
        [
            'name' => 'required',
            'type' => 'required',
            'message' => 'Email address is required'
        ],
        [
            'name' => 'email',
            'type' => 'email',
            'message' => 'Please enter a valid email address',
            'groups' => ['contact', 'login']  // Optional: assign to validation groups
        ]
    ]
]);
```

### 3. Manual validation (optional)

```javascript
// Programmatically validate fields
const validator = FormValidator.getInstance();

document.getElementById('validate-btn').addEventListener('click', async () => {
    const isValid = await validator.validateForm('my-form');
    if (isValid) {
        console.log('Form is valid!');
    } else {
        console.log('Form has errors');
    }
});
```

## Advanced Features

### Memory Management

The validator includes comprehensive memory management to prevent memory leaks:

```javascript
import { FormValidator } from '../validation';

const validator = FormValidator.getInstance();

// Clean up a specific field
validator.removeField('email-field');

// Clean up the entire validator instance
validator.destroy();

// Reset the singleton instance (useful for testing)
FormValidator.resetInstance();
```

### Specific Error Types

The system provides detailed error types for better error handling:

```javascript
import {
    ValidationError,
    RequiredFieldError,
    InvalidFormatError,
    LengthError,
    NumericRangeError,
    CustomValidationError
} from '../validation/types';

try {
    // Validation logic
} catch (error) {
    if (error instanceof RequiredFieldError) {
        console.log('Field is required:', error.fieldId);
    } else if (error instanceof InvalidFormatError) {
        console.log('Invalid format for field:', error.fieldId, error.expectedFormat);
    } else if (error instanceof ValidationError) {
        console.log('General validation error:', error.message);
    }
}
```

### Performance Optimizations

The validator includes several performance optimizations:

- **DOM Caching**: Element references are cached to avoid repeated DOM queries
- **Validation Result Caching**: Recent validation results are cached for 5 seconds
- **Debounced Validation**: Input validation is debounced to reduce validation frequency
- **Event Listener Management**: All event listeners are tracked and can be cleaned up

### Enhanced Accessibility

The system provides comprehensive accessibility support:

- **ARIA Attributes**: Proper `aria-invalid`, `aria-describedby`, and `aria-live` attributes
- **Screen Reader Announcements**: Success and error states are announced to screen readers
- **Keyboard Navigation**: Enhanced keyboard support with Enter/Escape key handling
- **Field Type Hints**: Automatic `autocomplete` and `aria-label` attributes for different field types
- **Live Regions**: Form-level validation status announcements

## Validation Rules

### Built-in Rules

| Rule Type   | Description                | Options               | Example                                       |
| ----------- | -------------------------- | --------------------- | --------------------------------------------- |
| `required`  | Field must have a value    | -                     | `{'type': 'required'}`                        |
| `email`     | Must be valid email format | -                     | `{'type': 'email'}`                           |
| `url`       | Must be valid URL format   | -                     | `{'type': 'url'}`                             |
| `minLength` | Minimum character count    | `value: number`       | `{'type': 'minLength', 'value': 5}`           |
| `maxLength` | Maximum character count    | `value: number`       | `{'type': 'maxLength', 'value': 100}`         |
| `pattern`   | Must match regex pattern   | `pattern: string`     | `{'type': 'pattern', 'pattern': '^[A-Z]+$'} ` |
| `numeric`   | Must be a valid number     | -                     | `{'type': 'numeric'}`                         |
| `min`       | Minimum numeric value      | `value: number`       | `{'type': 'min', 'value': 0}`                 |
| `max`       | Maximum numeric value      | `value: number`       | `{'type': 'max', 'value': 100}`               |
| `custom`    | Custom validation function | `validator: function` | See custom validation example                 |

### Custom Validation

```javascript
// Custom validation function
const passwordValidator = async (value: string, field: HTMLInputElement): Promise<{isValid: boolean; error?: string}> => {
    if (value.length < 8) {
        return { isValid: false, error: 'Password must be at least 8 characters' };
    }

    if (!/[A-Z]/.test(value)) {
        return { isValid: false, error: 'Password must contain uppercase letter' };
    }

    if (!/[a-z]/.test(value)) {
        return { isValid: false, error: 'Password must contain lowercase letter' };
    }

    if (!/\d/.test(value)) {
        return { isValid: false, error: 'Password must contain number' };
    }

    return { isValid: true };
};

// Use in field configuration
'validation' => [
    [
        'name' => 'password_strength',
        'type' => 'custom',
        'validator' => $passwordValidator,
        'message' => 'Password does not meet requirements'
    ]
]
```

## Validation Presets

Use pre-built validation rule sets for common scenarios:

```php
use CampaignBridge\Admin\Validation\ValidationPresets;

// Email field with standard validation
$email_field = new Form_Field_Input([
    'type' => 'email',
    'validation' => [
        ValidationPresets::required('Email is required'),
        ValidationPresets::email('Please enter a valid email')
    ]
]);

// Password field with security requirements
$password_field = new Form_Field_Input([
    'type' => 'password',
    'validation' => [
        ValidationPresets::required('Password is required'),
        ValidationPresets::minLength(8, 'Password must be at least 8 characters'),
        ValidationPresets::pattern(
            '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$',
            'Password must contain uppercase, lowercase, and number'
        )
    ]
]);
```

## Configuration Options

### Global Configuration

```javascript
const validator = FormValidator.getInstance({
    debounceDelay: 500,        // Delay before validation (ms)
    showSuccessStates: true,   // Show green checkmarks for valid fields
    enableAccessibility: true  // Add ARIA attributes for screen readers
});
```

### Field-specific Options

```javascript
// Initialize field with custom options
validator.initializeField('my-field', rules, {
    validateOnInput: true,     // Validate on every keystroke
    validateOnBlur: true,      // Validate when field loses focus
    validateOnChange: true     // Validate when value changes programmatically
});
```

## Visual Feedback

The validation system provides comprehensive visual feedback:

### States
- **Valid**: Green border, checkmark icon, success message
- **Invalid**: Red border, error icon, error message
- **Validating**: Blue border, loading animation
- **Neutral**: Default styling, no feedback

### Animations
- Shake animation for invalid fields
- Smooth slide-in for error messages
- Pulse animation for successful validation
- Loading shimmer for validation in progress

### Accessibility
- ARIA attributes for screen readers
- Live regions for dynamic announcements
- Focus management for error navigation
- High contrast mode support

## Advanced Usage

### Conditional Validation

```javascript
// Validation rules that depend on other fields
const conditionalRules = [
    {
        name: 'conditional_required',
        type: 'custom',
        validator: (value, field) => {
            const otherField = document.getElementById('other_field');
            if (otherField && otherField.value === 'yes') {
                return value ? {isValid: true} : {isValid: false, error: 'Required when other field is yes'};
            }
            return {isValid: true};
        }
    }
];
```

### Async Validation

```javascript
// Server-side validation (e.g., check if username exists)
const asyncUsernameValidator = async (value, field) => {
    try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=check_username&username=${encodeURIComponent(value)}`
        });

        const result = await response.json();

        return result.available
            ? {isValid: true}
            : {isValid: false, error: 'Username already taken'};
    } catch (error) {
        return {isValid: false, error: 'Unable to validate username'};
    }
};
```

### Form Integration

```javascript
// Prevent form submission if validation fails
document.getElementById('my-form').addEventListener('submit', async (e) => {
    const validator = FormValidator.getInstance();
    const isValid = await validator.validateForm('my-form');

    if (!isValid) {
        e.preventDefault();

        // Scroll to first error
        const firstError = document.querySelector('.campaignbridge-field--invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
```

## Styling Customization

The validation system uses CSS custom properties for easy theming:

```css
/* Custom validation colors */
.campaignbridge-field {
    --validation-color-error: #dc2626;
    --validation-color-success: #16a34a;
    --validation-color-validating: #3b82f6;
}

/* Adjust animation timings */
.campaignbridge-field {
    --validation-animation-duration-shake: 0.5s;
    --validation-animation-duration-slide-in: 0.3s;
}
```

## Performance Considerations

- Validation is debounced to prevent excessive processing
- CSS animations use `transform` for optimal performance
- Validation rules are cached and reused
- Large forms automatically batch validation operations

## Browser Support

- Modern browsers with ES6+ support
- Graceful degradation for older browsers
- Accessibility features work in all screen readers
- Responsive design for mobile devices

## Troubleshooting

### Common Issues

**Validation not triggering:**
- Ensure `initializeFormValidation()` is called on page load
- Check that fields have `data-validation` attributes
- Verify field IDs match validation initialization

**Styling not applied:**
- Confirm CSS imports are in the correct order
- Check for CSS specificity conflicts
- Ensure field containers have `campaignbridge-field` class

**Accessibility issues:**
- Verify ARIA attributes are present
- Test with screen readers
- Check focus management

### Debug Mode

Enable debug logging for development:

```javascript
const validator = FormValidator.getInstance({
    debug: true  // Enable console logging
});
```

## Migration Guide

### From Basic HTML Validation

Replace basic HTML validation attributes:

```html
<!-- Before -->
<input type="email" required pattern="[a-z]+@[a-z]+\.[a-z]+" />

<!-- After -->
<input type="email" data-validation='[{"type": "required"}, {"type": "email"}]' />
```

### From jQuery Validation

Migrate from jQuery validation plugins:

```javascript
// Before (jQuery)
$('#my-form').validate({
    rules: { email: 'required' },
    messages: { email: 'Required field' }
});

// After
const validator = FormValidator.getInstance();
validator.initializeField('email', [
    { type: 'required', message: 'Required field' },
    { type: 'email', message: 'Invalid email' }
]);
```



