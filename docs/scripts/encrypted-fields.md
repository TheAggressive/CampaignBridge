# Encrypted Fields System

A comprehensive, secure, and accessible system for handling encrypted form fields in WordPress admin interfaces. Provides temporary reveal functionality, secure editing, and proper cleanup for sensitive data.

## Overview

The Encrypted Fields system manages sensitive form data that needs to be:
- **Stored encrypted** in the database
- **Temporarily revealed** for user review
- **Securely edited** when needed
- **Automatically hidden** after timeout or form actions

## Architecture

### Core Components

#### 🏗️ **EncryptedFieldsHandler** (Main Orchestrator)
- Coordinates all managers and handlers
- Manages the complete lifecycle of encrypted fields
- Provides public API for external integration
- Handles cleanup and resource management

#### 🎨 **UIManager** (User Interface)
- Manages visual states (masked/revealed/editing)
- Handles button states and loading indicators
- Updates form field appearances
- Provides smooth transitions between states

#### 🎯 **StateManager** (Application State)
- Tracks field values, timeouts, and states
- Manages reveal timeouts and auto-hiding
- Stores original and revealed values securely
- Provides state summary for debugging

#### ♿ **AccessibilityManager** (A11y Support)
- ARIA attributes for screen readers
- Live region announcements for state changes
- Keyboard navigation support
- Focus management during state transitions

#### 🔧 **EventHandler** (User Interactions)
- Event delegation for performance
- Handles reveal, hide, edit, save, cancel actions
- Prevents double-submissions and race conditions
- Manages form submission cleanup

#### 🌐 **ApiClient** (Server Communication)
- Secure API calls with nonce validation
- Retry logic with exponential backoff
- Error handling and sanitization
- WordPress notices integration

#### ✅ **ValidationManager** (Data Validation)
- Input validation for encrypted fields
- Security checks and sanitization
- Type validation and constraints
- Error state management

#### 📋 **FieldElementsManager** (DOM Management)
- Caches and retrieves field elements
- Manages complex field structures
- Provides type-safe element access
- Handles dynamic field updates

## Features

### ✅ **Security First**
- **Encrypted Storage**: Data stored encrypted in database
- **Temporary Reveal**: Values auto-hide after configurable timeout
- **Secure Editing**: Values cleared from memory after operations
- **Nonce Validation**: All API calls protected with WordPress nonces

### ✅ **Accessibility Compliant**
- **WCAG 2.1 AA**: Full accessibility support
- **Screen Reader**: Live announcements for state changes
- **Keyboard Navigation**: Full keyboard support
- **ARIA Labels**: Proper labeling and descriptions

### ✅ **Memory Safe**
- **Event Cleanup**: All event listeners removed on destroy
- **Timeout Clearing**: All timeouts cleared to prevent leaks
- **State Management**: Proper cleanup of all internal state
- **Resource Management**: Comprehensive cleanup methods

### ✅ **Error Handling**
- **Graceful Degradation**: Works even with partial failures
- **User Feedback**: Clear error messages via WordPress notices
- **Retry Logic**: Automatic retry with backoff for network issues
- **Security Sanitization**: Sensitive data redacted from error messages

### ✅ **Performance Optimized**
- **Event Delegation**: Single event listener for all interactions
- **DOM Caching**: Elements cached to avoid repeated queries
- **Lazy Loading**: Components initialized only when needed
- **Efficient Updates**: Minimal DOM manipulation

## Usage

### Basic Setup

```typescript
import { EncryptedFieldsHandler } from './encrypted-fields';

// Initialize the system
const handler = new EncryptedFieldsHandler();

// The system automatically:
// 1. Finds all encrypted fields on the page
// 2. Sets up event handlers
// 3. Initializes accessibility features
// 4. Manages state and security
```

### HTML Structure

```html
<div class="campaignbridge-encrypted-field" data-field-id="api_key">
  <!-- Display Mode -->
  <div class="campaignbridge-encrypted-field__display">
    <input type="text" value="••••••••••••••••" readonly
           class="campaignbridge-encrypted-field__input--display">
    <div class="campaignbridge-encrypted-field__controls">
      <button class="campaignbridge-encrypted-field__reveal-btn">
        Reveal
      </button>
      <button class="campaignbridge-encrypted-field__edit-btn">
        Edit
      </button>
    </div>
  </div>

  <!-- Edit Mode (hidden by default) -->
  <div class="campaignbridge-encrypted-field__edit" style="display: none;">
    <input type="password" class="campaignbridge-encrypted-field__input--edit">
    <div class="campaignbridge-encrypted-field__controls">
      <button class="campaignbridge-encrypted-field__save-btn">
        Save
      </button>
      <button class="campaignbridge-encrypted-field__cancel-btn">
        Cancel
      </button>
    </div>
  </div>
</div>
```

### PHP Integration

```php
// In your form rendering
$field_id = 'api_key';
$masked_value = '••••••••••••••••'; // From database

echo '<div class="campaignbridge-encrypted-field" data-field-id="' . esc_attr($field_id) . '">';
echo '<div class="campaignbridge-encrypted-field__display">';
echo '<input type="text" value="' . esc_attr($masked_value) . '" readonly>';
echo '<div class="campaignbridge-encrypted-field__controls">';
echo '<button class="campaignbridge-encrypted-field__reveal-btn">Reveal</button>';
echo '<button class="campaignbridge-encrypted-field__edit-btn">Edit</button>';
echo '</div></div></div>';

// Hidden input for form submission (encrypted value)
echo '<input type="hidden" name="api_key" value="' . esc_attr($encrypted_value) . '">';
```

### API Endpoints

The system expects these WordPress REST API endpoints:

```php
// Decrypt endpoint
register_rest_route('campaignbridge/v1', '/decrypt', [
  'methods' => 'POST',
  'callback' => 'handle_decrypt_request',
  'permission_callback' => 'current_user_can_manage_options'
]);

// Encrypt endpoint
register_rest_route('campaignbridge/v1', '/encrypt', [
  'methods' => 'POST',
  'callback' => 'handle_encrypt_request',
  'permission_callback' => 'current_user_can_manage_options'
]);
```

## Configuration

### JavaScript Configuration

```javascript
// wp_localize_script configuration
wp_localize_script('campaignbridge-encrypted-fields', 'campaignbridgeAdmin', {
  security: {
    revealTimeout: 10000,    // 10 seconds
    maxRetries: 3,          // API retry attempts
    requestTimeout: 30000    // 30 seconds
  },
  nonce: wp_create_nonce('wp_rest'),
  i18n: {
    loading: 'Loading...',
    saving: 'Saving...',
    revealing: 'Revealing...'
  }
});
```

### CSS Classes

```css
/* Field States */
.campaignbridge-encrypted-field {
  position: relative;
}

.campaignbridge-encrypted-field--revealed {
  /* Field is temporarily revealed */
}

.campaignbridge-encrypted-field--editing {
  /* Field is in edit mode */
}

.campaignbridge-encrypted-field--loading {
  /* Field is processing */
}

/* Buttons */
.campaignbridge-encrypted-field__reveal-btn,
.campaignbridge-encrypted-field__hide-btn,
.campaignbridge-encrypted-field__edit-btn,
.campaignbridge-encrypted-field__save-btn,
.campaignbridge-encrypted-field__cancel-btn {
  /* Button styling */
}

.campaignbridge-btn--loading {
  position: relative;
  opacity: 0.7;
}

.campaignbridge-btn--loading::after {
  content: '';
  position: absolute;
  width: 1em;
  height: 1em;
  border: 2px solid #f3f3f3;
  border-top: 2px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

/* Screen Reader Support */
#encrypted-fields-live-region {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}
```

## Security Features

### Data Protection
- **Server-Side Encryption**: Data encrypted before database storage
- **Temporary Reveal**: Values only visible for limited time
- **Memory Clearing**: Values removed from memory after use
- **Input Sanitization**: All inputs validated and sanitized

### API Security
- **Nonce Validation**: WordPress nonces for CSRF protection
- **Permission Checks**: Proper capability validation
- **Error Sanitization**: Sensitive data removed from error messages
- **Rate Limiting**: Built-in retry limits and timeouts

### User Experience Security
- **Auto-Hide**: Revealed values automatically hidden
- **Secure Editing**: Values cleared when editing cancelled
- **Form Submission**: All sensitive data cleared on submit
- **Session Management**: Proper cleanup on page unload

## Accessibility Features

### ARIA Support
- **`aria-expanded`**: Indicates if field is revealed/expanded
- **`aria-busy`**: Shows loading states
- **`aria-live`**: Live region for announcements
- **`aria-describedby`**: Links fields to help text

### Keyboard Navigation
- **Tab Order**: Proper tab sequence maintained
- **Enter/Space**: Button activation
- **Escape**: Cancel operations
- **Arrow Keys**: Navigate between controls

### Screen Reader Announcements
- **Reveal Actions**: "Sensitive data revealed for 10 seconds"
- **Hide Actions**: "Sensitive data hidden for security"
- **Edit Mode**: "Edit mode activated"
- **Save/Cancel**: "Changes saved" / "Changes cancelled"
- **Errors**: "Error: [specific error message]"

## Error Handling

### Client-Side Errors
```typescript
// Automatic error handling with user feedback
try {
  await handler.revealField('api_key');
} catch (error) {
  // Error automatically shown to user via WordPress notices
  // Screen reader announcement made
  // Proper logging with context
}
```

### Server-Side Errors
```php
// API endpoints return structured errors
return new WP_Error(
  'decryption_failed',
  __('Failed to decrypt field data', 'campaignbridge'),
  ['status' => 500]
);
```

### Network Errors
- **Automatic Retry**: Failed requests retried with backoff
- **Timeout Handling**: Requests timeout after configured period
- **Offline Support**: Graceful degradation when offline
- **Error Recovery**: Clear error states and user guidance

## Performance Considerations

### Memory Management
- **Event Cleanup**: All listeners removed on destroy
- **State Clearing**: Maps and objects cleared properly
- **Timeout Management**: All timers cleared to prevent leaks
- **DOM References**: Weak references where possible

### Optimization Techniques
- **Event Delegation**: Single listener for all interactions
- **DOM Caching**: Elements cached to avoid repeated queries
- **Lazy Initialization**: Components created only when needed
- **Minimal Updates**: Only changed elements updated

## Browser Support

- **Modern Browsers**: Full feature support (Chrome, Firefox, Safari, Edge)
- **IE11+**: Core functionality with graceful degradation
- **Mobile Browsers**: Touch-friendly interactions
- **Screen Readers**: Full JAWS, NVDA, VoiceOver support

## Testing

### Unit Testing
```typescript
describe('EncryptedFieldsHandler', () => {
  let handler: EncryptedFieldsHandler;

  beforeEach(() => {
    handler = new EncryptedFieldsHandler();
  });

  afterEach(() => {
    handler.destroy(); // Critical for test isolation
  });

  test('should reveal field temporarily', async () => {
    const fieldId = 'test-field';
    await handler.revealField(fieldId);

    expect(handler.isFieldRevealed(fieldId)).toBe(true);

    // Wait for timeout
    await new Promise(resolve => setTimeout(resolve, 11000));
    expect(handler.isFieldRevealed(fieldId)).toBe(false);
  });
});
```

### Integration Testing
```typescript
test('should handle form submission', () => {
  // Create test form with encrypted field
  const form = createTestForm();
  const handler = new EncryptedFieldsHandler();

  // Simulate form submission
  form.dispatchEvent(new Event('submit'));

  // Verify all sensitive data cleared
  expect(handler.getStateSummary().revealedValues.size).toBe(0);
  expect(handler.getStateSummary().timeouts.size).toBe(0);

  handler.destroy();
});
```

## API Reference

### Public Methods

#### `EncryptedFieldsHandler`
- `revealField(fieldId: string): Promise<void>` - Temporarily reveal field
- `hideField(fieldId: string): void` - Immediately hide field
- `editField(fieldId: string): void` - Enter edit mode
- `saveField(fieldId: string): Promise<void>` - Save field changes
- `cancelEdit(fieldId: string): void` - Cancel editing
- `isFieldRevealed(fieldId: string): boolean` - Check reveal status
- `getStateSummary(): object` - Get debugging state info
- `destroy(): void` - Clean up all resources

#### `StateManager`
- `clearTimeout(fieldId: string): void` - Clear reveal timeout
- `clearAll(): void` - Clear all state and timeouts
- `getRevealedValue(fieldId: string): string | undefined` - Get revealed value

#### `UIManager`
- `setButtonLoading(button: HTMLElement, loading: boolean): void` - Set button loading state
- `showFieldState(fieldId: string, state: 'masked' | 'revealed' | 'editing'): void` - Change field state

## Migration Guide

### From Basic Implementation

**Before:**
```javascript
// Basic reveal/hide without security
function revealField(fieldId) {
  const field = document.getElementById(fieldId);
  field.type = 'text';
  setTimeout(() => {
    field.type = 'password';
  }, 10000);
}
```

**After:**
```typescript
// Secure, accessible, memory-safe
const handler = new EncryptedFieldsHandler();
// Automatic security, accessibility, and cleanup
```

## Troubleshooting

### Common Issues

#### Fields Not Revealing
```typescript
// Check console for errors
console.log(handler.getStateSummary());

// Verify HTML structure
const field = document.querySelector('[data-field-id="your-field"]');
console.log('Field found:', !!field);
```

#### Memory Leaks
```typescript
// Always call destroy when component unmounts
useEffect(() => {
  const handler = new EncryptedFieldsHandler();
  return () => handler.destroy(); // Cleanup
}, []);
```

#### Accessibility Issues
```typescript
// Test with screen reader
handler.revealField('test-field');
// Listen for live region announcements
```

## Changelog

### v1.0.0 (Current)
- **Complete Architecture**: Modular, secure, accessible system
- **Memory Management**: Comprehensive cleanup and resource management
- **Security Features**: Encryption, timeouts, auto-hiding
- **Accessibility**: Full WCAG 2.1 AA compliance
- **Error Handling**: Robust error recovery and user feedback
- **Performance**: Optimized DOM operations and event handling
- **TypeScript**: Full type safety and modern development practices

## Contributing

### Development Setup
```bash
# Install dependencies
npm install

# Run TypeScript compiler
npm run build

# Run tests
npm test

# Lint code
npm run lint
```

### Code Standards
- **TypeScript**: Strict type checking enabled
- **ES6+**: Modern JavaScript features
- **Accessibility**: WCAG 2.1 AA compliance
- **Security**: Input validation and sanitization
- **Performance**: Memory leak prevention
- **Testing**: Unit and integration test coverage
