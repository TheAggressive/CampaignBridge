# Form Builder Usage Guide - All Ways to Use It

## 🎯 Quick Start (3 Ways)

### 1. Basic Form (Simplest)
```php
$form = new Form_Builder('my_form', [
    'fields' => [
        'name' => ['type' => 'text', 'label' => 'Name', 'required' => true],
        'email' => ['type' => 'email', 'label' => 'Email', 'required' => true]
    ]
]);
$form->render(); // Done!
```

### 2. Advanced Form (Full Featured)
```php
$form = new Form_Builder('advanced_form', [
    'layout' => 'table',
    'data_source' => 'options',
    'prefix' => 'my_plugin_',
    'success_message' => 'Saved!',
    'fields' => [
        'setting' => ['type' => 'text', 'label' => 'Setting', 'required' => true]
    ],
    'hooks' => [
        'before_save' => function($data) { return $data; },
        'after_save' => function($data, $result) { /* handle result */ }
    ]
]);
$form->render();
```

### 3. Custom Everything (Full Control)
```php
$form = new Form_Builder('custom_form', [
    'layout' => 'custom',
    'save_method' => 'custom',
    'classes' => ['my-custom-form'],
    'attributes' => ['data-form-type' => 'special'],
    'fields' => [/* field config */],
    'hooks' => [
        'load_data' => function($fields) { /* custom loading */ },
        'save_data' => function($data) { /* custom saving */ },
        'render_layout' => function($fields, $data) { /* custom rendering */ }
    ]
]);
```

## 📋 Configuration Methods

### Form Configuration (7 Ways)

1. **Minimal Config**
```php
$form = new Form_Builder('id', ['fields' => [...]]);
```

2. **Full HTTP Config**
```php
$form = new Form_Builder('id', [
    'method' => 'POST',
    'action' => '/custom-endpoint',
    'enctype' => 'multipart/form-data'
]);
```

3. **Styling Config**
```php
$form = new Form_Builder('id', [
    'classes' => ['form-class'],
    'attributes' => ['data-type' => 'settings']
]);
```

4. **Data Management Config**
```php
$form = new Form_Builder('id', [
    'data_source' => 'options', // options, post_meta, custom
    'prefix' => 'plugin_',
    'suffix' => '_setting'
]);
```

5. **Layout Config**
```php
$form = new Form_Builder('id', [
    'layout' => 'table', // table, div, custom
]);
```

6. **Messages Config**
```php
$form = new Form_Builder('id', [
    'success_message' => 'Saved!',
    'error_message' => 'Error!'
]);
```

7. **Button Config**
```php
$form = new Form_Builder('id', [
    'submit_button' => [
        'text' => 'Save',
        'type' => 'primary',
        'classes' => ['custom-btn']
    ]
]);
```

## 🎯 Field Types (17 Types)

### Text Inputs (6 Types)
```php
'text' => ['type' => 'text', 'label' => 'Text'],
'email' => ['type' => 'email', 'label' => 'Email'],
'url' => ['type' => 'url', 'label' => 'URL'],
'password' => ['type' => 'password', 'label' => 'Password'],
'number' => ['type' => 'number', 'min' => 0, 'max' => 100],
'tel' => ['type' => 'tel', 'label' => 'Phone']
```

### Date/Time Inputs (4 Types)
```php
'date' => ['type' => 'date', 'label' => 'Date'],
'time' => ['type' => 'time', 'label' => 'Time'],
'datetime-local' => ['type' => 'datetime-local', 'label' => 'DateTime'],
'color' => ['type' => 'color', 'label' => 'Color']
```

### Content Fields (3 Types)
```php
'textarea' => ['type' => 'textarea', 'rows' => 5],
'wysiwyg' => ['type' => 'wysiwyg', 'editor_settings' => [...]],
'file' => ['type' => 'file', 'accept' => 'image/*']
```

### Choice Fields (4 Types)
```php
'select' => ['type' => 'select', 'options' => ['a' => 'A', 'b' => 'B']],
'multiselect' => ['type' => 'multiselect', 'options' => [...], 'multiple' => true],
'radio' => ['type' => 'radio', 'options' => ['yes' => 'Yes', 'no' => 'No']],
'checkbox' => ['type' => 'checkbox', 'options' => [...]] // single or multiple
```

## 🪝 Lifecycle Hooks (8 Hooks)

### Data Hooks (2)
```php
'load_data' => function($fields) { /* custom data loading */ },
'save_data' => function($data) { /* custom data saving */ }
```

### Validation Hooks (2)
```php
'before_validate' => function($data) { /* pre-validation */ },
'after_validate' => function($data, $errors) { /* post-validation */ }
```

### Save Hooks (3)
```php
'before_save' => function($data) { /* pre-save */ },
'after_save' => function($data, $result) { /* post-save */ },
'on_success' => function($data) { /* success callback */ },
'on_error' => function($data) { /* error callback */ }
```

### Render Hook (1)
```php
'render_layout' => function($fields, $data) { /* custom layout */ }
```

## 🔧 Advanced Usage Patterns

### 1. Conditional Fields
```php
$form = new Form_Builder('conditional', [
    'fields' => [
        'type' => ['type' => 'select', 'options' => ['basic' => 'Basic', 'premium' => 'Premium']],
        'premium_field' => [
            'type' => 'text',
            'attributes' => ['data-conditional-field' => 'type', 'data-conditional-value' => 'premium'],
            'wrapper_class' => 'conditional-field'
        ]
    ]
]);
```

### 2. Custom Field Types
```php
// Register custom field
add_filter('campaignbridge_form_custom_field', function($field, $type, $config) {
    if ($type === 'color_picker') {
        return new My_Color_Picker_Field($config);
    }
    return $field;
}, 10, 3);

// Use custom field
'color' => ['type' => 'color_picker', 'label' => 'Pick Color']
```

### 3. Multi-Step Forms
```php
// Step 1
$form1 = new Form_Builder('step1', [
    'fields' => ['name' => ['type' => 'text']],
    'hooks' => ['after_save' => function() { wp_redirect('?step=2'); }]
]);

// Step 2
$form2 = new Form_Builder('step2', [
    'fields' => ['email' => ['type' => 'email']],
    'hooks' => ['after_save' => function() { wp_redirect('?step=3'); }]
]);
```

### 4. AJAX Forms
```php
$form = new Form_Builder('ajax_form', [
    'attributes' => ['data-ajax-submit' => 'true'],
    'classes' => ['ajax-form']
]);

// JavaScript handling
jQuery('.ajax-form').on('submit', function(e) {
    e.preventDefault();
    // AJAX submit logic
});
```

### 5. Bulk Operations
```php
$form = new Form_Builder('bulk', [
    'fields' => [
        'items' => ['type' => 'multiselect', 'options' => $all_items],
        'action' => ['type' => 'select', 'options' => ['delete' => 'Delete', 'activate' => 'Activate']]
    ],
    'hooks' => [
        'after_save' => function($data) {
            $this->perform_bulk_action($data['action'], $data['items']);
        }
    ]
]);
```

### 6. External API Integration
```php
$form = new Form_Builder('api_form', [
    'fields' => ['api_key' => ['type' => 'password']],
    'hooks' => [
        'before_save' => function($data) {
            // Validate API key with external service
            $response = wp_remote_post('api.example.com/validate', [
                'body' => ['key' => $data['api_key']]
            ]);
            if (wp_remote_retrieve_response_code($response) !== 200) {
                throw new Exception('Invalid API key');
            }
            return $data;
        }
    ]
]);
```

### 7. File Upload with Validation
```php
$form = new Form_Builder('upload_form', [
    'enctype' => 'multipart/form-data',
    'fields' => [
        'avatar' => [
            'type' => 'file',
            'accept' => 'image/*',
            'allowed_types' => ['image/jpeg', 'image/png'],
            'max_size' => 2 * 1024 * 1024, // 2MB
            'multiple' => false
        ]
    ]
]);
```

## 🎨 Layout Methods (3 Ways)

### 1. Table Layout (WordPress Default)
```php
'layout' => 'table' // Traditional form-table
```

### 2. Div Layout (Modern)
```php
'layout' => 'div' // Modern flexbox layout
```

### 3. Custom Layout (Full Control)
```php
'layout' => 'custom',
'hooks' => [
    'render_layout' => function($fields, $data) {
        echo '<div class="my-layout">';
        foreach ($fields as $id => $field) {
            echo '<div class="field">';
            // Custom rendering
            echo '</div>';
        }
        echo '</div>';
    }
]
```

## 💾 Data Storage Methods (3 Ways)

### 1. WordPress Options (Default)
```php
'save_method' => 'options',
'prefix' => 'my_plugin_',
'suffix' => '_setting'
```

### 2. Post Meta
```php
'save_method' => 'post_meta',
'post_id' => get_the_ID()
```

### 3. Custom Storage
```php
'save_method' => 'custom',
'hooks' => [
    'save_data' => function($data) {
        // Save to database, API, file, etc.
        return my_custom_save_function($data);
    }
]
```

## 🔒 Security Features (Automatic)

- ✅ **CSRF Protection**: Nonces generated automatically
- ✅ **Input Sanitization**: Field-type specific sanitization
- ✅ **User Permissions**: Capability checking
- ✅ **Rate Limiting**: Optional request throttling
- ✅ **File Security**: Malware scanning and validation
- ✅ **XSS Prevention**: Automatic output escaping

## 📊 Integration Patterns (4 Ways)

### 1. Screen Context Integration
```php
// In screen file
$form = new Form_Builder('settings', $config);
if ($form->is_submitted() && $form->is_valid()) {
    $screen->add_message('Saved!');
}
$form->render();
```

### 2. Controller Integration
```php
// In controller
public function handle_request() {
    $form = new Form_Builder('action_form', $config);
    if ($form->is_submitted() && $form->is_valid()) {
        // Handle form
    }
}
```

### 3. AJAX Integration
```php
// Form with AJAX
$form = new Form_Builder('ajax', [
    'attributes' => ['data-ajax' => 'true']
]);

// JavaScript
jQuery('[data-ajax]').on('submit', function(e) {
    e.preventDefault();
    // AJAX handling
});
```

### 4. Shortcode Integration
```php
// Register shortcode
add_shortcode('my_form', function() {
    ob_start();
    $form = new Form_Builder('shortcode_form', $config);
    $form->render();
    return ob_get_clean();
});
```

## 🚀 Real-World Examples

### Plugin Settings Form
```php
$form = new Form_Builder('plugin_settings', [
    'prefix' => 'my_plugin_',
    'fields' => [
        'api_key' => ['type' => 'password', 'required' => true],
        'debug' => ['type' => 'checkbox', 'default' => false],
        'cache_time' => ['type' => 'number', 'min' => 60, 'max' => 3600]
    ]
]);
```

### Contact Form
```php
$form = new Form_Builder('contact', [
    'fields' => [
        'name' => ['type' => 'text', 'required' => true],
        'email' => ['type' => 'email', 'required' => true],
        'message' => ['type' => 'textarea', 'required' => true]
    ],
    'hooks' => [
        'after_save' => function($data) {
            wp_mail('admin@example.com', 'Contact Form', $data['message']);
        }
    ]
]);
```

### User Profile Form
```php
$form = new Form_Builder('profile', [
    'data_source' => 'custom',
    'save_method' => 'custom',
    'fields' => [
        'display_name' => ['type' => 'text'],
        'bio' => ['type' => 'textarea'],
        'avatar' => ['type' => 'file', 'accept' => 'image/*']
    ],
    'hooks' => [
        'load_data' => function() {
            $user = wp_get_current_user();
            return [
                'display_name' => $user->display_name,
                'bio' => get_user_meta($user->ID, 'bio', true)
            ];
        },
        'save_data' => function($data) {
            $user_id = get_current_user_id();
            wp_update_user(['ID' => $user_id, 'display_name' => $data['display_name']]);
            update_user_meta($user_id, 'bio', $data['bio']);
        }
    ]
]);
```

## 📚 Best Practices

1. **Use meaningful field IDs** - `user_email` not `field1`
2. **Always validate** - Rely on built-in + add custom rules
3. **Provide feedback** - Use hooks for user messaging
4. **Handle file uploads carefully** - Validate types and sizes
5. **Choose right field types** - Better UX with correct inputs
6. **Test thoroughly** - All field types and edge cases
7. **Keep it simple** - Start basic, add complexity as needed
8. **Use hooks for extensibility** - Don't modify core code

---

**The Form Builder gives you complete control with minimal code. From simple contact forms to complex multi-step wizards, it's all possible with the same simple API!** 🎉
