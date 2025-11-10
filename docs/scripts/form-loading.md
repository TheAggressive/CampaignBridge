# Form Loading States Manager

A comprehensive form loading states management system for WordPress forms, providing visual feedback, accessibility support, and memory management.

## Features

### ✅ **Memory Management**
- **Event Listener Tracking**: Automatically tracks and removes event listeners to prevent memory leaks
- **DOM Caching**: Caches DOM elements for performance optimization
- **Timeout Management**: Properly manages and clears all timeouts
- **Cleanup Methods**: `destroy()` method for complete resource cleanup

### ✅ **Accessibility Support**
- **ARIA Attributes**: Adds `aria-busy` to buttons during loading
- **Screen Reader Announcements**: Live region announcements for loading state changes
- **Keyboard Navigation**: Maintains accessibility during loading states
- **Focus Management**: Proper focus handling during state transitions

### ✅ **Visual Feedback**
- **Button State Changes**: Updates button text and disables submission
- **Loading Spinner**: Optional visual spinner during loading
- **CSS Classes**: Adds/removes loading classes for styling
- **Form State Indication**: Visual indicators for form loading status

### ✅ **Error Handling & Recovery**
- **Timeout Protection**: Auto-reset after configurable timeout (30s default)
- **State Validation**: Prevents multiple loading states
- **Graceful Degradation**: Works even if DOM elements are missing
- **Error Recovery**: Methods to manually reset loading states

### ✅ **Configurable Options**
- **Loading Text**: Customizable loading button text
- **Submit Text**: Configurable submit button text
- **Spinner Display**: Enable/disable loading spinner
- **Accessibility**: Toggle accessibility features
- **Timeout Duration**: Configurable auto-reset timeout

## Usage

### Basic Usage

```typescript
import { FormLoadingManager } from './form-loading';

// Initialize with configuration
const loadingManager = new FormLoadingManager({
  formId: 'my-form',
  loadingText: 'Submitting...',
  submitText: 'Submit Form',
  timeout: 30000, // 30 seconds
  enableAccessibility: true
});

// The manager automatically handles form submission
// and shows loading states
```

### Programmatic Control

```typescript
// Start loading manually
loadingManager.startLoading();

// Check if currently loading
if (loadingManager.isLoading()) {
  console.log('Form is submitting...');
}

// Get loading duration
const duration = loadingManager.getLoadingDuration();

// Reset loading state manually
loadingManager.resetLoading();

// Clean up when done
loadingManager.destroy();
```

### Legacy Function (Backward Compatibility)

```typescript
import { initFormLoading } from './form-loading';

// Legacy function still works
initFormLoading({
  formId: 'legacy-form',
  loadingText: 'Processing...'
});
```

### Auto-Initialization

The system automatically initializes if configuration is provided on the window object:

```javascript
// In your PHP template or script
wp_localize_script('your-script', 'campaignbridgeFormLoading', {
  formId: 'auto-form',
  loadingText: 'Loading...',
  timeout: 45000
});
```

## Configuration Options

| Option                | Type      | Default        | Description                              |
| --------------------- | --------- | -------------- | ---------------------------------------- |
| `formId`              | `string`  | **required**   | ID of the form element                   |
| `loadingText`         | `string`  | `'Loading...'` | Text shown on button during loading      |
| `submitText`          | `string`  | `'Submit'`     | Default submit button text               |
| `disableOnSubmit`     | `boolean` | `true`         | Whether to disable button during loading |
| `showSpinner`         | `boolean` | `true`         | Whether to show loading spinner          |
| `timeout`             | `number`  | `30000`        | Auto-reset timeout in milliseconds       |
| `enableAccessibility` | `boolean` | `true`         | Enable accessibility features            |

## CSS Classes Added

The system automatically adds CSS classes for styling:

```css
/* Form loading states */
.campaignbridge-form--loading-enabled {
  /* Form has loading functionality enabled */
}

.campaignbridge-form--loading {
  /* Form is currently in loading state */
}

/* Button loading states */
.campaignbridge-btn--loading {
  /* Button is in loading state */
  position: relative;
}

/* Loading spinner */
.campaignbridge-spinner {
  /* Loading spinner styles */
  display: inline-block;
  width: 1em;
  height: 1em;
  border: 2px solid #f3f3f3;
  border-top: 2px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Screen reader announcements */
.campaignbridge-form__status {
  /* Live region for announcements */
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}
```

## Accessibility Features

### ARIA Support
- **`aria-busy`**: Set to `true` on submit buttons during loading
- **`aria-live`**: Live region announces loading state changes
- **`aria-atomic`**: Ensures screen readers get complete status updates

### Screen Reader Announcements
- **Loading Start**: "Form submission in progress, please wait..."
- **Loading End**: "Form ready for submission."

### Keyboard Navigation
- Maintains focus management during loading states
- Buttons remain keyboard accessible
- No interference with tab order or keyboard events

## Memory Management

### Automatic Cleanup
- **Event Listeners**: Tracked and removed on destroy
- **Timeouts**: Cleared to prevent memory leaks
- **DOM Cache**: Cleared when component is destroyed
- **State Maps**: All internal state cleared

### Manual Cleanup
```typescript
// Always call destroy when component is no longer needed
loadingManager.destroy();
```

## Error Handling

### Graceful Degradation
- **Missing Elements**: Continues working if DOM elements are missing
- **Configuration Errors**: Uses sensible defaults for invalid config
- **Timeout Recovery**: Automatically resets after timeout
- **State Corruption**: Validates state before operations

### Error Recovery
```typescript
try {
  loadingManager.startLoading();
} catch (error) {
  console.error('Loading failed:', error);
  // Manual reset if needed
  loadingManager.resetLoading();
}
```

## Button Detection

The system automatically finds submit buttons using multiple selectors:

1. `input[type="submit"]` - Standard submit inputs
2. `button[type="submit"]` - Standard submit buttons
3. `.campaignbridge-submit-btn` - Custom class buttons
4. `button:not([type]), input[type="button"]` - Fallback for untyped buttons

## Browser Support

- **Modern Browsers**: Full feature support
- **IE11+**: Core functionality with graceful degradation
- **Mobile Browsers**: Touch-friendly loading states

## Performance Considerations

- **DOM Caching**: Elements cached to avoid repeated queries
- **Event Delegation**: Minimal event listeners
- **Timeout Management**: Efficient timeout handling
- **Memory Cleanup**: Prevents memory leaks in long-running apps

## Migration from Legacy Version

### Before (Legacy)
```typescript
initFormLoading({
  formId: 'my-form',
  loadingText: 'Loading...',
  submitText: 'Submit'
});
// No cleanup, basic functionality
```

### After (New)
```typescript
const loadingManager = new FormLoadingManager({
  formId: 'my-form',
  loadingText: 'Loading...',
  submitText: 'Submit',
  timeout: 30000,
  enableAccessibility: true
});

// Programmatic control
loadingManager.startLoading();

// Proper cleanup
loadingManager.destroy();
```

## Testing

### Unit Testing
```typescript
describe('FormLoadingManager', () => {
  let manager: FormLoadingManager;

  beforeEach(() => {
    manager = new FormLoadingManager({
      formId: 'test-form'
    });
  });

  afterEach(() => {
    manager.destroy();
  });

  test('starts loading state', () => {
    manager.startLoading();
    expect(manager.isLoading()).toBe(true);
  });

  test('resets loading state', () => {
    manager.startLoading();
    manager.resetLoading();
    expect(manager.isLoading()).toBe(false);
  });
});
```

### Integration Testing
```typescript
test('handles form submission', () => {
  // Create test form
  const form = document.createElement('form');
  form.id = 'test-form';
  const button = document.createElement('input');
  button.type = 'submit';
  button.value = 'Submit';
  form.appendChild(button);
  document.body.appendChild(form);

  const manager = new FormLoadingManager({
    formId: 'test-form'
  });

  // Simulate form submission
  form.dispatchEvent(new Event('submit'));

  expect(manager.isLoading()).toBe(true);
  expect(button.disabled).toBe(true);
  expect(button.value).toBe('Loading...');

  // Cleanup
  manager.destroy();
  document.body.removeChild(form);
});
```

## Changelog

### v2.0.0 (Current)
- **Complete Rewrite**: Class-based architecture with memory management
- **Accessibility**: Full ARIA support and screen reader announcements
- **State Management**: Programmatic control over loading states
- **Error Handling**: Comprehensive error handling and recovery
- **Performance**: DOM caching and efficient event management
- **Backward Compatibility**: Legacy function still supported

### v1.0.0 (Legacy)
- Basic form loading functionality
- Simple button text changes
- No cleanup or memory management
- Limited accessibility support
