# CampaignBridge

A comprehensive WordPress plugin for creating and managing professional email campaigns with dynamic content integration. Features Mailchimp API integration, custom email templates with block-based design, automated campaign generation, and seamless WordPress post type management.

## ✨ Features

### 🎨 Email Template System
- **Block-Based Email Design**: Create beautiful email templates using WordPress block editor
- **Custom Post Type**: Dedicated `cb_templates` post type for template management
- **Template Categories**: Organize templates by purpose (newsletter, promotional, welcome, etc.)
- **Responsive Design**: Email-safe HTML generation with CSS inlining

### 📧 Email Service Provider Integration
- **Mailchimp API Integration**: Full integration with Mailchimp's powerful email platform
- **Campaign Management**: Create and update campaigns directly from WordPress
- **Audience Management**: Sync and manage Mailchimp audiences
- **Template Mapping**: Map WordPress content to Mailchimp email templates
- **Automated Campaign Generation**: Create campaigns from WordPress content automatically

### 🔧 Advanced Form System (Trait-Based Architecture)
- **Trait-Based Fluent API**: Modern composition-based form builder with automatic field management
- **Comprehensive Security**: AES-256-GCM encryption, CSRF protection, input sanitization, and capability validation
- **Dynamic Form Builder**: Type-safe fluent API with automatic field closing and context-aware methods
- **Conditional Logic**: Advanced show/hide fields with AJAX evaluation and dependency management
- **Secure File Uploads**: MIME validation, size limits, and suspicious-content signature checks with progress tracking
- **Form Factory Pattern**: Pre-built form templates with security integration (contact, registration, settings)
- **Data Preservation**: Form state maintained across submissions with encrypted sensitive data storage
- **Accessibility Compliant**: WCAG 2.1 AA compliance with ARIA attributes and full keyboard navigation

### 🛠️ Professional Architecture
- **Service Container**: Dependency injection for clean code organization
- **Provider Interface**: Extensible architecture for multiple email providers
- **REST API**: Complete REST API for all plugin operations
- **Admin Interface**: Professional WordPress admin integration

### 🔒 Enterprise Security & Compliance
- **AES-256-GCM Encryption**: Military-grade encryption for sensitive data with context-aware permissions
- **CSRF Protection**: Unique nonces per form with comprehensive request validation
- **Input Sanitization**: Type-aware sanitization for all user inputs with field-specific validation
- **SQL Injection Prevention**: WordPress prepared statements with automatic parameter binding
- **XSS Prevention**: Comprehensive output escaping and content filtering
- **Access Control**: Capability-based permissions with admin-only sensitive data access
- **Security Logging**: Centralized, redacted diagnostic logging for security-relevant failures
- **File Security**: MIME validation, size limits, and suspicious-content signature checks for uploads

### 📊 Advanced Features & Performance
- **Rate Limiting**: Intelligent API rate limiting with exponential backoff
- **Error Handling**: Comprehensive error handling with user-friendly feedback and admin logging
- **Debug Logging**: Structured debug-mode logging with performance metrics
- **Performance Optimization**: Critical resource preloading, lazy loading, and intelligent caching
- **Automatic Validation**: PHPCS/PHPStan integration with custom security rules
- **Trait-Based Extension**: Zero-configuration API extension with automatic type safety

## 📋 Requirements

### System Requirements
- **WordPress**: 6.5.0 or higher
- **PHP**: 8.2 or higher
- **MySQL**: 5.6 or higher
- **Memory**: 128MB RAM minimum (256MB recommended)
- **HTTPS**: Required for secure API communications

### Browser Support
CampaignBridge targets current evergreen Chrome, Firefox, Safari, and Edge releases. Browser compatibility is enforced incrementally through the end-to-end test roadmap in `docs/testing-strategy.md`.

## 🚀 Installation

### Automatic Installation
1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "CampaignBridge"
3. Click **Install Now**
4. Click **Activate**

### Manual Installation
1. Download the plugin from the WordPress plugin repository
2. Upload to `/wp-content/plugins/` directory
3. Activate through **Plugins > Installed Plugins**
4. Navigate to **CampaignBridge > Settings**

## ⚙️ Configuration

### Initial Setup
1. Go to **CampaignBridge > Settings** in your admin menu
2. Configure your email service provider (Mailchimp)
3. Enter your API credentials
4. Select your audience/list
5. Save your configuration

### Post Type Configuration
1. Navigate to **CampaignBridge > Post Types**
2. Select which post types should be available for campaigns
3. Configure content inclusion rules
4. Save your configuration

### Template Setup
1. Go to **CampaignBridge > Email Templates**
2. Create new templates using the block editor
3. Configure template settings (width, category, status)
4. Preview and publish templates

## 📖 Usage

### Creating Email Campaigns
1. **Select Content**: Choose posts from configured post types
2. **Choose Template**: Select from available email templates
3. **Configure Campaign**: Set campaign parameters and audience
4. **Send Campaign**: Send immediately or schedule for later

### Managing Templates
1. **Template Editor**: Use WordPress block editor for design
2. **Template Categories**: Organize templates by purpose
3. **Template Preview**: Live preview of email appearance
4. **Template Settings**: Configure width, category, and status

### API Usage
All plugin functionality is available via REST API:
- `GET /wp-json/campaignbridge/v1/mailchimp/audiences`
- `GET /wp-json/campaignbridge/v1/mailchimp/templates`
- `POST /wp-json/campaignbridge/v1/mailchimp/verify`
- Plus many more endpoints for full functionality

## 🏗️ Technical Architecture

### Plugin Structure
```
campaignbridge/
├── includes/                    # Core PHP classes
│   ├── Admin/                   # Admin interface classes
│   │   ├── Pages/               # Admin page classes
│   │   └── Asset_Manager.php     # Asset management
│   ├── Core/                    # Core functionality
│   │   ├── Service_Container.php # Dependency injection
│   ├── Post_Types/               # Custom post type classes
│   │   └── Post_Type_Email_Template.php    # Email template management
│   ├── Providers/               # Email service providers
│   │   ├── Provider_Interface.php # Provider contract
│   │   ├── Mailchimp_Provider.php # Mailchimp integration
│   │   └── Html_Provider.php     # HTML export provider
│   ├── REST/                    # REST API endpoints
│   │   ├── Routes.php           # General REST routes
│   │   ├── MailchimpRoutes.php  # Mailchimp-specific routes
│   │   └── Editor_Settings_Routes.php # Editor settings
│   └── Services/                # Business logic services
│       └── Email_Generator.php   # Email HTML generation
├── src/blocks/                  # WordPress block definitions
│   ├── post/                    # Post content blocks
│   ├── post-cta/               # Call-to-action blocks
│   ├── post-excerpt/           # Post excerpt blocks
│   ├── post-image/             # Featured image blocks
│   └── post-title/             # Post title blocks
├── languages/                   # Translation files
├── assets/                      # Static assets
└── uninstall.php               # Comprehensive uninstall script
```

### 🔧 WordPress Integration

The plugin leverages official WordPress packages for consistent behavior:

- **@wordpress/data** - State management and data persistence
- **@wordpress/components** - UI components with accessibility support
- **@wordpress/block-editor** - Block-based email template editor
- **@wordpress/i18n** - Internationalization support

### Key Classes and Components

#### Service Container Pattern
The plugin uses a service container for dependency injection:
```php
$container = new Service_Container();
$container->initialize();
$mailchimp = $container->get('mailchimp_provider');
```

#### Provider Interface
All email service providers implement a common interface:
```php
interface ProviderInterface {
    public function slug(): string;
    public function label(): string;
    public function send_campaign(array $blocks, array $settings);
    // ... other methods
}
```

#### REST API Architecture
Complete REST API for all operations:
- Rate limiting protection
- Permission-based access control
- Comprehensive error handling
- JSON response formatting

### File-Based Admin System

CampaignBridge uses a modern file-based admin system that auto-discovers screens and provides a clean, maintainable architecture for WordPress admin pages.

**Key Features:**
- **Zero Configuration**: Just create files, everything works automatically
- **Auto-Discovery**: Controllers, tabs, and assets auto-detected
- **Convention Over Configuration**: Naming determines behavior
- **Progressive Enhancement**: Start simple, add complexity when needed

**Directory Structure:**
```
includes/Admin/
├── Core/                    # System core files
├── Screens/                 # Auto-discovered admin pages
│   ├── dashboard.php       # Simple screen
│   └── settings/           # Tabbed screen
│       ├── general.php     # Tab 1
│       └── mailchimp.php   # Tab 2
├── Controllers/             # Optional business logic
└── Models/                  # Optional data layer
```

**For complete developer documentation including:**
- Screen types and creation
- Controller auto-discovery
- Configuration overrides
- Screen context helper methods
- Best practices and migration guide

## 📖 Documentation

- **[Developer Guide](DEV_README.md)** - Comprehensive technical documentation for developers
- **[API Documentation](API_DOCUMENTATION.md)** - Complete REST API reference
- **[Admin System Guide](docs/admin/admin-interface.md)** - Admin interface documentation
- **[Form System Guide](docs/admin/form-system.md)** - Form building and validation

## 🔒 Security Features

- **Input Sanitization**: Comprehensive input validation with WordPress sanitization functions
- **CSRF Protection**: Nonce verification for all form submissions and AJAX requests
- **Rate Limiting**: Sliding window rate limiting (60 requests/minute per user)
- **Request Origin Validation**: CSRF protection for AJAX endpoints
- **File Upload Security**: MIME type validation, size limits, and malware content scanning
- **Encryption System**: AES-256-GCM encryption for sensitive data with context-aware permissions
- **Capability Checks**: Proper user capability validation throughout the application
- **SQL Injection Prevention**: Prepared statements and WordPress database API
- **XSS Protection**: Output escaping, Content Security Policy headers, and input filtering
- **Security Headers**: X-Frame-Options, X-Content-Type-Options, X-XSS-Protection
- **Secure Uninstall**: Complete data cleanup and encrypted data removal

## 🐛 Troubleshooting

### Common Issues

#### Plugin Not Loading
1. Check PHP version (requires 8.2+)
2. Verify WordPress version (requires 6.5.0+)
3. Check for plugin conflicts
4. Enable debug logging for detailed errors

#### API Connection Issues
1. Verify API credentials in settings
2. Check network connectivity
3. Review rate limiting settings
4. Enable debug mode for API errors

#### Template Editor Issues
1. Clear browser cache
2. Check WordPress block editor compatibility
3. Verify PHP memory limits
4. Check for JavaScript conflicts

### Debug Mode
Enable WordPress debug mode to see detailed error information:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Support
For technical support and bug reports, please:
1. Check the WordPress error logs
2. Verify plugin compatibility
3. Test with default WordPress theme
4. Contact the development team with detailed error information

## 📝 Changelog

### Version 0.2.0
- ✅ **Major Refactoring**: Complete code organization improvements
- ✅ **Enhanced Security**: Comprehensive security enhancements
- ✅ **Professional Architecture**: Service container and interface patterns
- ✅ **Better Performance**: Optimized database queries and caching
- ✅ **Improved Documentation**: Comprehensive code documentation
- ✅ **Standards Compliance**: WordPress coding standards throughout

### Version 0.1.0
- Initial release with basic functionality

## 🛠️ Development

### Environment Setup
```bash
# WordPress development environment
# Requires: WordPress 6.5.0+, PHP 8.2+, MySQL 5.6+

# Plugin uses standard WordPress development practices
# No external build tools required for core functionality
```

### Code Organization
- **Service Container**: Dependency injection for clean architecture
- **Provider Pattern**: Extensible email service provider system
- **Interface Contracts**: Clear contracts for all major components
- **REST API**: Modern API design with proper error handling

### Testing
- Compatible with WordPress testing framework
- Unit tests for core functionality
- Integration tests for API endpoints
- Security testing for user input handling

### Development Automation

CampaignBridge includes modern development automation to ensure code quality and streamline releases:

#### Pre-commit Hooks
Automatic code quality checks run before every commit:
```bash
# Automatic linting and formatting
✅ ESLint + Prettier for JavaScript/TypeScript
✅ PHP CodeSniffer + PHPStan for PHP
✅ CSS linting for stylesheets

# Conventional commit validation
✅ Commit message format enforcement
✅ Semantic versioning compliance
```

#### Automated Version Management
Semantic versioning with automated releases:
```bash
# Commit types determine version bumps:
feat: add new feature     → MINOR version (1.2.3 → 1.3.0)
fix: bug fix              → PATCH version (1.2.3 → 1.2.4)
feat!: breaking change    → MAJOR version (1.2.3 → 2.0.0)

# Automatic WordPress plugin updates:
✅ package.json version sync
✅ campaignbridge.php VERSION constant
✅ campaignbridge.php header version
✅ CHANGELOG.md generation
✅ GitHub release creation
```

#### CI/CD Pipeline
GitHub Actions automation for every pull request and push:
```bash
# Quality Assurance Suite:
✅ PHP CodeSniffer linting
✅ PHPStan static analysis
✅ ESLint code quality
✅ PHPUnit unit tests
✅ Security checks
✅ WordPress compatibility
```

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Aggressive Network, LLC

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Follow WordPress coding standards
4. Add tests for new functionality
5. Submit a pull request

## 📞 Support

For issues and feature requests:
- Check the WordPress plugin repository
- Review the troubleshooting section above
- Contact the development team with detailed information

---

**CampaignBridge** - Professional Email Campaign Management for WordPress
# Test commit to verify Husky v9 works without warnings
