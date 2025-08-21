# CampaignBridge

A WordPress plugin for creating and managing email templates using the native WordPress block editor (Gutenberg).

## Features

### Template Manager
- **Native Block Editor Integration**: Full WordPress block editor embedded directly in the admin interface
- **Template Management**: Create, edit, save, and delete email templates
- **Custom Post Type**: Uses `cb_template` post type for storing templates
- **REST API Integration**: Modern WordPress REST API for all operations
- **Responsive Design**: Clean, modern UI with TailwindCSS-inspired styling

### Email Blocks
- **Email Post Slot**: Dynamic content insertion for posts
- **Email Post Title**: Post title display
- **Email Post Excerpt**: Post excerpt display
- **Email Post Image**: Post featured image
- **Email Post Button**: Call-to-action buttons

## Installation

1. Upload the plugin to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to CampaignBridge > Template Manager in your admin menu

## Usage

### Creating Templates
1. Go to CampaignBridge > Template Manager
2. Click "New Template"
3. Enter a template name
4. Use the block editor to design your email template
5. Click "Save Template" to store your work

### Managing Templates
- **Load Template**: Select an existing template from the dropdown
- **Save Template**: Save changes to the current template
- **Delete Template**: Remove unwanted templates
- **Refresh List**: Update the template list from the database

## Technical Details

- **WordPress Version**: Requires WordPress 5.0+ (Gutenberg)
- **PHP Version**: PHP 7.4+
- **JavaScript**: Modern ES6+ with WordPress block editor integration
- **Styling**: Custom CSS with WordPress admin theme compatibility
- **REST API**: Full REST API support for template operations

## Development

### Building Assets
```bash
# Install dependencies
pnpm install

# Build all assets
pnpm build

# Build specific components
pnpm build:blocks
pnpm build:assets
pnpm build:interactivity

# Development mode
pnpm start
```

### File Structure
```
src/
├── scripts/
│   ├── template-manager.js    # Main template manager functionality
│   ├── core/                  # Core services and utilities
│   ├── managers/              # Feature managers
│   └── services/              # Business logic services
├── blocks/                    # Custom Gutenberg blocks
└── styles/                    # CSS and styling
```

## Support

For issues and feature requests, please check the plugin's GitHub repository or contact the development team.
